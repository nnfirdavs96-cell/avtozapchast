<?php
/**
 * Массовая достройка библиотеки каталога — ТОЛЬКО CLI.
 *
 * Две фазы, обе возобновляемые (можно прерывать и запускать снова — продолжит):
 *
 *   1) recount — ПЕРЕСЧЁТ ДЕРЕВЬЕВ. Авто, сохранённые со старым жёстким потолком,
 *      имеют обрезанное дерево (напр. ровно 120 узлов, хотя реально 229).
 *      Сохранённое дерево отдаётся из библиотеки раньше запроса к API, поэтому
 *      само не обновится: чистим узлы + kv-кэш и обходим заново с текущим
 *      `catalog_nodes_limit`. Собранные схемы НЕ трогаем.
 *
 *   2) schemes — ДОБОР СХЕМ. Тянет недостающие схемы у авто с неполным набором
 *      (как catalog_library_cron.php, но с большим бюджетом за один запуск).
 *
 * ТАРИФ: обе фазы используют groups2/parts2 — это НЕ расшифровка VIN,
 * тарифный VIN-лимит они не тратят. Ограничение только по нагрузке на API,
 * поэтому есть бюджеты и паузы.
 *
 * Запуск:
 *   php superadmin/catalog_library_rebuild.php [что] [бюджет_авто] [бюджет_схем] [пауза_мс]
 *
 *   что           — all (по умолчанию) | recount | schemes | plan
 *                   plan = ничего не делать, только показать объём работ
 *   бюджет_авто   — сколько авто пересчитать за запуск (по умолчанию 10)
 *   бюджет_схем   — сколько схем дотянуть за запуск (по умолчанию 300)
 *   пауза_мс      — пауза между запросами (по умолчанию 300)
 *
 * Примеры:
 *   php superadmin/catalog_library_rebuild.php plan            # оценить объём
 *   php superadmin/catalog_library_rebuild.php recount 10      # пересчитать 10 авто
 *   php superadmin/catalog_library_rebuild.php schemes 0 500   # добрать 500 схем
 *   nohup php superadmin/catalog_library_rebuild.php all 100 5000 300 > rebuild.log 2>&1 &
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из консоли (CLI).\n"); }

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/catalog.php';

function rlog(string $m): void { echo '[' . date('H:i:s') . '] ' . $m . "\n"; @flush(); }

$what        = strtolower(trim((string)($argv[1] ?? 'all')));
$carsBudget  = (int)($argv[2] ?? 10);
$schemeBudget= (int)($argv[3] ?? 300);
$pauseMs     = max(0, (int)($argv[4] ?? 300));
if (!in_array($what, ['all', 'recount', 'schemes', 'plan'], true)) $what = 'all';

// ── Защита от параллельного запуска ──────────────────────────────────────────
// Два прогона одновременно = двойная нагрузка на API Tradesoft и гонка за одни и
// те же авто: список кандидатов у них одинаковый, поэтому один удаляет дерево,
// пока другой его обходит. Держим файловую блокировку на время работы; режим
// plan только читает, ему блокировка не нужна.
$lockFh = null;
if ($what !== 'plan') {
    $lockPath = sys_get_temp_dir() . '/autodoc_catalog_rebuild.lock';
    $lockFh   = @fopen($lockPath, 'c');
    if ($lockFh === false || !flock($lockFh, LOCK_EX | LOCK_NB)) {
        exit("Другой прогон уже запущен (блокировка $lockPath).\n"
           . "Проверьте:  ps aux | grep catalog_library_rebuild | grep -v grep\n"
           . "Дождитесь окончания или остановите его, затем запустите снова.\n");
    }
    ftruncate($lockFh, 0);
    fwrite($lockFh, (string)getmypid());
    // Блокировка снимается автоматически при завершении процесса (в т.ч. по kill).
    register_shutdown_function(function () use ($lockFh, $lockPath) {
        @flock($lockFh, LOCK_UN);
        @fclose($lockFh);
        @unlink($lockPath);
    });
}

$prov = Catalog::provider();
if (!($prov instanceof PartsCatalogsAdapter) || !$prov->enabled()) {
    exit("Провайдер Parts-Catalogs выключен или не выбран (Суперадмин → VIN-поиск).\n");
}

$db    = getDB();
$limit = PartsCatalogsAdapter::nodesLimit();
@set_time_limit(0);

// ── Оценка объёма ────────────────────────────────────────────────────────────
// «Подозрительные» деревья: их размер упёрся в какой-то потолок (старый 120 или текущий).
// LEFT JOIN, а не JOIN: авто, у которого дерева НЕТ вовсе (например, прошлый
// обход вернул пусто и дерево не восстановилось), обязано попадать в очередь —
// иначе оно навсегда выпадает из пересчёта и чинить его пришлось бы вручную.
// Кандидаты на пересчёт:
//   • дерева нет вовсе (NULL) — восстанавливаем, идут первыми;
//   • дерево стоит ровно на ОДНОМ ИЗ СТАРЫХ потолков (120/300/500/1000/2000)
//     И этот потолок НИЖЕ текущего лимита — значит его тогда обрезало, а сейчас
//     можно собрать больше.
// Авто, у которого узлов ровно = ТЕКУЩЕМУ лимиту, НЕ трогаем: повторный обход даст
// тот же результат и сожжёт впустую сотни запросов (у Volvo это 1000 запросов и
// ~15 минут на каждый прогон). Такие показываем отдельной строкой-подсказкой.
$suspect = $db->prepare(
    "SELECT c.catalog_id, c.car_id, c.brand, c.criteria,
            COALESCE(n.nodes_count, 0) AS nodes_count
       FROM catalog_library_cars c
       LEFT JOIN catalog_library_nodes n ON n.catalog_id=c.catalog_id AND n.car_id=c.car_id
      WHERE n.nodes_count IS NULL
         OR (n.nodes_count IN (120, 300, 500, 1000, 2000) AND n.nodes_count < ?)
   ORDER BY (n.nodes_count IS NULL) DESC, c.updated_at DESC"
);
$suspect->execute([$limit]);
$suspectCars = $suspect->fetchAll();

// Упёрлись в текущий лимит — переобход не поможет, нужно поднять «узлов макс.».
$atCeil = (int)$db->query(
    "SELECT COUNT(*) FROM catalog_library_nodes WHERE nodes_count >= " . (int)$limit
)->fetchColumn();

$missing = $db->query(
    "SELECT COUNT(*) FROM (
        SELECT c.catalog_id, c.car_id, n.nodes_count,
               (SELECT COUNT(*) FROM catalog_library_schemes s
                 WHERE s.catalog_id=c.catalog_id AND s.car_id=c.car_id) AS have
          FROM catalog_library_cars c
          JOIN catalog_library_nodes n ON n.catalog_id=c.catalog_id AND n.car_id=c.car_id
        HAVING have < n.nodes_count
     ) t"
)->fetchColumn();

$missingSchemes = (int)$db->query(
    "SELECT COALESCE(SUM(n.nodes_count - (
            SELECT COUNT(*) FROM catalog_library_schemes s
             WHERE s.catalog_id=c.catalog_id AND s.car_id=c.car_id)),0)
       FROM catalog_library_cars c
       JOIN catalog_library_nodes n ON n.catalog_id=c.catalog_id AND n.car_id=c.car_id
      WHERE n.nodes_count > (SELECT COUNT(*) FROM catalog_library_schemes s
                              WHERE s.catalog_id=c.catalog_id AND s.car_id=c.car_id)"
)->fetchColumn();

echo str_repeat('═', 70) . "\n";
echo "Текущий лимит узлов (catalog_nodes_limit): $limit\n";
echo "Авто к пересчёту дерева (обрезаны/нет)   : " . count($suspectCars) . "\n";
if ($atCeil > 0) {
    echo "Авто, упёршихся в текущий лимит ($limit)   : $atCeil — переобход не поможет,\n";
    echo "                                           поднимите «узлов макс.», если нужно больше\n";
}
echo "Авто с неполными схемами                 : $missing\n";
echo "Недостающих схем всего                   : $missingSchemes\n";
$estMin = round(($missingSchemes * (0.5 + $pauseMs / 1000)) / 60);
echo "Оценка времени на добор схем             : ~{$estMin} мин (при паузе {$pauseMs} мс)\n";
echo "Тариф VIN                                : НЕ тратится (groups2/parts2)\n";
echo str_repeat('═', 70) . "\n";

if ($what === 'plan') {
    echo "Режим plan — ничего не изменено.\n";
    echo "Запуск работы:  php superadmin/catalog_library_rebuild.php all {$carsBudget} {$schemeBudget} {$pauseMs}\n";
    exit(0);
}

// ── Фаза 1: пересчёт деревьев ────────────────────────────────────────────────
if ($what === 'all' || $what === 'recount') {
    if ($carsBudget <= 0) {
        rlog('Пересчёт пропущен (бюджет авто = 0).');
    } elseif (!$suspectCars) {
        rlog('Пересчёт не нужен — обрезанных деревьев нет.');
    } else {
        rlog('── Фаза 1: пересчёт деревьев (бюджет ' . $carsBudget . ' авто) ──');
        $done = 0;
        foreach ($suspectCars as $car) {
            if ($done >= $carsBudget) break;
            $cid = (string)$car['catalog_id'];
            $rid = (string)$car['car_id'];
            $was = (int)$car['nodes_count'];

            try {
                // Обход дерева — это минуты HTTP-запросов, за которые MySQL успевает
                // закрыть простаивающее соединение. Поднимаем его перед КАЖДОЙ машиной
                // (до записей) и ещё раз после обхода — иначе «server has gone away».
                $db = dbKeepAlive();

                // Сохраняем текущее дерево ПЕРЕД удалением: если обход вернёт пусто
                // (лимит API/сбой сети), старое дерево надо вернуть на место, иначе
                // авто останется вообще без узлов — так в прошлом прогоне потерялось
                // 15 деревьев (86 → 71).
                $bk = $db->prepare("SELECT nodes_count, nodes_json FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?");
                $bk->execute([$cid, $rid]);
                $backup = $bk->fetch() ?: null;

                $db->prepare("DELETE FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?")->execute([$cid, $rid]);
                $db->prepare("DELETE FROM partsapi_kv_cache WHERE k LIKE ?")->execute(['nodes:%:' . $cid . ':' . $rid]);

                $nodes = $prov->oemNodesForCar($rid, $cid, (string)($car['criteria'] ?? ''), (string)($car['brand'] ?? ''));
                $now   = count($nodes);
                $db    = dbKeepAlive();
                $done++;

                // Обход не дал ничего — откатываем удаление.
                if ($now === 0 && $backup !== null) {
                    $db->prepare("INSERT INTO catalog_library_nodes (catalog_id, car_id, nodes_count, nodes_json)
                                  VALUES (?,?,?,?)
                                  ON DUPLICATE KEY UPDATE nodes_count=VALUES(nodes_count), nodes_json=VALUES(nodes_json)")
                       ->execute([$cid, $rid, (int)$backup['nodes_count'], (string)$backup['nodes_json']]);
                }

                // Печатаем лимит, который реально видит адаптер — чтобы расхождение
                // «в шапке 1000, а обход встал на 300» было видно сразу.
                $effLimit = PartsCatalogsAdapter::nodesLimit();
                if ($now === 0) {
                    rlog(sprintf('  %-14s %s: ПУСТО (лимит API или сбой) — %s',
                        $car['brand'] ?: '—', substr($rid, 0, 10),
                        $backup !== null
                            ? 'старое дерево (' . (int)$backup['nodes_count'] . ') возвращено, повторите позже'
                            : 'дерева и не было, повторите позже'));
                } else {
                    rlog(sprintf('  %-14s %s: %d → %d узлов (лимит %d)%s',
                        $car['brand'] ?: '—', substr($rid, 0, 10), $was, $now, $effLimit,
                        $now >= $effLimit ? '  ← упёрлось в лимит, поднимите «узлов макс.»' : ''));
                }
            } catch (Throwable $e) {
                // Одна проблемная машина не должна убивать весь прогон.
                $done++;
                rlog(sprintf('  %-14s %s: ОШИБКА — %s', $car['brand'] ?: '—', substr($rid, 0, 10),
                    mb_substr($e->getMessage(), 0, 120)));
                try { $db = getDB(true); } catch (Throwable $e2) { rlog('  Не удалось восстановить соединение с БД — выходим.'); break; }
            }
            if ($pauseMs > 0) usleep($pauseMs * 1000);
        }
        rlog("Фаза 1 завершена: пересчитано авто — $done из " . count($suspectCars) . '.');
    }
}

// ── Фаза 2: добор схем ───────────────────────────────────────────────────────
if ($what === 'all' || $what === 'schemes') {
    if ($schemeBudget <= 0) {
        rlog('Добор схем пропущен (бюджет схем = 0).');
    } else {
        rlog('── Фаза 2: добор схем (бюджет ' . $schemeBudget . ' схем) ──');
        $cars = $db->query(
            "SELECT c.catalog_id, c.car_id, c.brand, c.criteria, n.nodes_count,
                    (SELECT COUNT(*) FROM catalog_library_schemes s
                      WHERE s.catalog_id=c.catalog_id AND s.car_id=c.car_id) AS have
               FROM catalog_library_cars c
               JOIN catalog_library_nodes n ON n.catalog_id=c.catalog_id AND n.car_id=c.car_id
             HAVING have < n.nodes_count
             ORDER BY c.updated_at DESC"
        )->fetchAll();

        $fetched = 0;
        foreach ($cars as $car) {
            if ($fetched >= $schemeBudget) break;
            $cid = (string)$car['catalog_id'];
            $rid = (string)$car['car_id'];

            try {
            $db = dbKeepAlive();   // фаза идёт часами — соединение надо оживлять
            $n = $db->prepare("SELECT nodes_json FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?");
            $n->execute([$cid, $rid]);
            $nodes = json_decode(($n->fetchColumn() ?: '[]'), true) ?: [];

            $h = $db->prepare("SELECT group_id FROM catalog_library_schemes WHERE catalog_id=? AND car_id=?");
            $h->execute([$cid, $rid]);
            $have = [];
            foreach ($h->fetchAll(PDO::FETCH_COLUMN) as $g) $have[(string)$g] = true;

            $miss = [];
            foreach ($nodes as $nd) {
                $cat = (string)($nd['cat'] ?? '');
                if ($cat !== '' && empty($have[$cat])) $miss[] = $cat;
            }
            if (!$miss) continue;

            $take = array_slice($miss, 0, $schemeBudget - $fetched);
            $res  = $prov->harvestSchemes($cid, $rid, (string)($car['criteria'] ?? ''),
                                          (string)($car['brand'] ?? ''), $take, count($take), $pauseMs);
            $fetched += $res['fetched'];
            $db = dbKeepAlive();
            rlog(sprintf('  %-14s %s: +%d схем (было %d из %d)%s',
                $car['brand'] ?: '—', substr($rid, 0, 10), $res['fetched'],
                (int)$car['have'], (int)$car['nodes_count'],
                $res['rate_limited'] ? '  ← STOP: лимит API' : ''));

            if ($res['rate_limited']) { rlog('Достигнут лимит API — останавливаемся, запустите позже.'); break; }
            } catch (Throwable $e) {
                rlog(sprintf('  %-14s %s: ОШИБКА — %s', $car['brand'] ?: '—', substr($rid, 0, 10),
                    mb_substr($e->getMessage(), 0, 120)));
                try { $db = getDB(true); } catch (Throwable $e2) { rlog('  Не удалось восстановить соединение с БД — выходим.'); break; }
            }
        }
        rlog("Фаза 2 завершена: собрано схем за запуск — $fetched.");
    }
}

// ── Итог ─────────────────────────────────────────────────────────────────────
$db     = dbKeepAlive();   // после часов работы соединение почти наверняка отвалилось
$cars   = (int)$db->query("SELECT COUNT(*) FROM catalog_library_cars")->fetchColumn();
$trees  = (int)$db->query("SELECT COUNT(*) FROM catalog_library_nodes")->fetchColumn();
$sch    = (int)$db->query("SELECT COUNT(*) FROM catalog_library_schemes")->fetchColumn();
echo str_repeat('─', 70) . "\n";
rlog("Библиотека: авто $cars · деревьев $trees · схем $sch");
rlog('Скрипт возобновляемый — запустите ещё раз, чтобы продолжить.');
