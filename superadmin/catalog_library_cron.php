<?php
/**
 * Cron-дособиратель библиотеки каталога Parts-Catalogs.
 * Тихо дотягивает недостающие схемы у накопленных авто малыми порциями, чтобы не
 * выжечь суточный лимит ключа. Управляется тумблером в суперадмине
 * (site_settings.catalog_library_autocollect): если '0' — выходит, ничего не делая,
 * и суперадмин собирает вручную кнопкой на странице библиотеки.
 *
 * Запуск (каждые 5 минут):
 *   *​/5 * * * * php /path/to/superadmin/catalog_library_cron.php [budget=12]
 *
 * budget — сколько схем максимум тянуть за один запуск (по умолчанию 12).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/catalog.php';

function clog(string $m): void { echo '[' . date('c') . '] ' . $m . "\n"; }

// 1. Тумблер: выключен — выходим молча (ручной режим).
if (getSetting('catalog_library_autocollect', '0') !== '1') {
    clog('Автосбор выключен (catalog_library_autocollect=0) — выход.');
    exit(0);
}

// 2. Провайдер должен быть Parts-Catalogs и включён.
$prov = Catalog::provider();
if (!($prov instanceof PartsCatalogsAdapter) || !$prov->enabled()) {
    clog('Провайдер Parts-Catalogs выключен или не выбран — выход.');
    exit(0);
}

$budget = max(1, (int)($argv[1] ?? 12));   // сколько схем максимум за запуск
$db = getDB();

// 3. Авто с неполными схемами (собрано меньше, чем узлов). Свежие — первыми.
try {
    $cars = $db->query(
        "SELECT c.catalog_id, c.car_id, c.brand, c.criteria, n.nodes_count,
                (SELECT COUNT(*) FROM catalog_library_schemes s
                  WHERE s.catalog_id=c.catalog_id AND s.car_id=c.car_id) AS have
         FROM catalog_library_cars c
         JOIN catalog_library_nodes n ON n.catalog_id=c.catalog_id AND n.car_id=c.car_id
         HAVING have < n.nodes_count
         ORDER BY c.updated_at DESC
         LIMIT 50"
    )->fetchAll();
} catch (Exception $e) {
    clog('Таблицы библиотеки недоступны (миграция не запущена?): ' . $e->getMessage());
    exit(1);
}

if (!$cars) { clog('Нет авто с недостающими схемами — всё собрано.'); exit(0); }

$totalFetched = 0;
foreach ($cars as $car) {
    if ($totalFetched >= $budget) break;

    // Недостающие узлы этого авто.
    $n = $db->prepare("SELECT nodes_json FROM catalog_library_nodes WHERE catalog_id=? AND car_id=?");
    $n->execute([$car['catalog_id'], $car['car_id']]);
    $nodes = json_decode(($n->fetchColumn() ?: '[]'), true) ?: [];

    $h = $db->prepare("SELECT group_id FROM catalog_library_schemes WHERE catalog_id=? AND car_id=?");
    $h->execute([$car['catalog_id'], $car['car_id']]);
    $have = [];
    foreach ($h->fetchAll(PDO::FETCH_COLUMN) as $g) $have[(string)$g] = true;

    $missing = [];
    foreach ($nodes as $nd) {
        $cat = (string)($nd['cat'] ?? '');
        if ($cat !== '' && empty($have[$cat])) $missing[] = $cat;
    }
    if (!$missing) continue;

    $take = array_slice($missing, 0, $budget - $totalFetched);
    $res  = $prov->harvestSchemes(
        (string)$car['catalog_id'], (string)$car['car_id'],
        (string)($car['criteria'] ?? ''), (string)($car['brand'] ?? ''),
        $take, count($take), 300
    );
    $totalFetched += $res['fetched'];
    clog(sprintf('%s [%s/%s]: +%d схем (было %d из %d)%s',
        $car['brand'] ?: '—', $car['catalog_id'], $car['car_id'],
        $res['fetched'], (int)$car['have'], (int)$car['nodes_count'],
        $res['rate_limited'] ? ' — STOP: rate-limit' : ''));

    if ($res['rate_limited']) { clog('Достигнут лимит запросов — останавливаемся до следующего запуска.'); break; }
}

clog("Готово. Собрано схем за запуск: {$totalFetched} (budget={$budget}).");
