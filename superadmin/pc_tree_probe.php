<?php
/**
 * Диагностика РЕАЛЬНОГО размера дерева узлов Parts-Catalogs — ТОЛЬКО CLI.
 *
 * Зачем: в боевом обходе (PartsCatalogsAdapter::collectLeaves) стоят жёсткие
 * ограничения `depth > 4` и `count >= 120`, поэтому у «богатых» машин дерево
 * обрезается и мы не знаем, сколько узлов на самом деле. Этот скрипт проходит
 * дерево БЕЗ потолка (с настраиваемым предохранителем) и показывает:
 *   • реальное число узлов (листьев со схемами),
 *   • сколько запросов groups2 на это ушло,
 *   • распределение узлов по глубине,
 *   • обрезал бы боевой лимит это дерево или нет.
 *
 * ВАЖНО: обход дерева использует метод groups2 — это НЕ расшифровка VIN,
 * тарифный лимит («запрос по VIN») он не тратит. Ничего в БД не пишет — только читает.
 *
 * Запуск:
 *   php superadmin/pc_tree_probe.php <catalogId> <carId> [criteria] [maxDepth=10] [safety=2000]
 *   php superadmin/pc_tree_probe.php --vin <VIN> [maxDepth=10] [safety=2000]
 *   php superadmin/pc_tree_probe.php --lib            # взять первое авто из библиотеки
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из консоли (CLI).\n"); }

require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/includes/catalog.php';

$prov = Catalog::provider();
if (!($prov instanceof PartsCatalogsAdapter) || !$prov->enabled()) {
    exit("Провайдер Parts-Catalogs выключен или не выбран (Суперадмин → VIN-поиск).\n");
}

// Доступ к приватному get() адаптера — чтобы авторизация/база/язык были ровно те же.
$getM = new ReflectionMethod($prov, 'get');
$getM->setAccessible(true);

$db = getDB();
$catalogId = ''; $carId = ''; $criteria = ''; $brand = '';
$maxDepth = 10; $safety = 2000;

$a1 = (string)($argv[1] ?? '');

if ($a1 === '--lib') {
    $row = $db->query("SELECT catalog_id, car_id, criteria, brand FROM catalog_library_cars ORDER BY updated_at DESC LIMIT 1")->fetch();
    if (!$row) exit("В библиотеке нет авто.\n");
    [$catalogId, $carId, $criteria, $brand] = [$row['catalog_id'], $row['car_id'], (string)$row['criteria'], (string)$row['brand']];
} elseif ($a1 === '--vin') {
    $vin = trim((string)($argv[2] ?? ''));
    if ($vin === '') exit("Укажите VIN: php superadmin/pc_tree_probe.php --vin <VIN>\n");
    $maxDepth = (int)($argv[3] ?? 10) ?: 10;
    $safety   = (int)($argv[4] ?? 2000) ?: 2000;
    // Сначала пробуем библиотеку (0 запросов), иначе — расшифровка (1 VIN-запрос!).
    $row = $db->prepare("SELECT catalog_id, car_id, criteria, brand FROM catalog_library_cars WHERE vin = ? LIMIT 1");
    $row->execute([$vin]);
    if ($r = $row->fetch()) {
        [$catalogId, $carId, $criteria, $brand] = [$r['catalog_id'], $r['car_id'], (string)$r['criteria'], (string)$r['brand']];
        echo "Авто взято из библиотеки (расшифровка VIN не понадобилась).\n";
    } else {
        echo "ВНИМАНИЕ: VIN не найден в библиотеке — будет расшифровка (тратит 1 запрос тарифа).\n";
        // Разбор — как в PartsCatalogsAdapter::carInfoFull(): массив авто, берём первое.
        [$j] = $getM->invoke($prov, 'v1/car/info/', ['q' => $vin]);
        $car = null;
        if (is_array($j)) {
            $car = (isset($j[0]) && is_array($j[0])) ? $j[0] : (isset($j['carId']) ? $j : null);
        }
        if (!$car || empty($car['carId']) || empty($car['catalogId'])) exit("Не удалось расшифровать VIN.\n");
        $catalogId = (string)$car['catalogId'];
        $carId     = (string)$car['carId'];
        $criteria  = (string)($car['criteria'] ?? '');
        $brand     = (string)($car['brand'] ?? '');
    }
} else {
    $catalogId = $a1;
    $carId     = (string)($argv[2] ?? '');
    $criteria  = (string)($argv[3] ?? '');
    $maxDepth  = (int)($argv[4] ?? 10) ?: 10;
    $safety    = (int)($argv[5] ?? 2000) ?: 2000;
}

if ($catalogId === '' || $carId === '') {
    exit("Нужны catalogId и carId.\nПример: php superadmin/pc_tree_probe.php lexus 0c3415940fcda3e835488e183e67b5bd\n"
       . "Или:    php superadmin/pc_tree_probe.php --lib\n");
}

echo str_repeat('─', 72) . "\n";
echo "Авто     : " . ($brand !== '' ? $brand . ' · ' : '') . "$catalogId / $carId\n";
echo "criteria : " . ($criteria !== '' ? $criteria : '(пусто)') . "\n";
echo "Обход    : без потолка узлов, maxDepth=$maxDepth, предохранитель=$safety\n";
echo "Тариф    : groups2 — VIN-лимит НЕ тратит\n";
echo str_repeat('─', 72) . "\n";

$reqCount  = 0;   // сколько groups2-запросов сделали
$leaves    = [];  // узлы со схемами: gid => ['name'=>..,'depth'=>..]
$seen      = [];  // защита от повторов (один узел достижим из разных веток)
$byDepth   = [];  // распределение листьев по глубине
$stopped   = false;
$t0        = microtime(true);

$walk = function (string $groupId, int $depth) use (&$walk, &$reqCount, &$leaves, &$seen, &$byDepth, &$stopped,
                                                    $getM, $prov, $catalogId, $carId, $criteria, $maxDepth, $safety): void {
    if ($stopped || $depth > $maxDepth) return;
    if (count($leaves) >= $safety) { $stopped = true; return; }
    if ($groupId !== '') { if (isset($seen[$groupId])) return; $seen[$groupId] = true; }

    $reqCount++;
    [$j] = $getM->invoke($prov, 'v1/catalogs/' . rawurlencode($catalogId) . '/groups2', array_filter([
        'carId'    => $carId,
        'groupId'  => $groupId,
        'criteria' => $criteria,
    ], fn($v) => $v !== ''));
    if ($reqCount % 10 === 0) { echo "  … запросов: $reqCount, найдено узлов: " . count($leaves) . "\n"; }
    usleep(250 * 1000);   // вежливая пауза

    if (!is_array($j)) return;
    foreach ($j as $g) {
        if (!is_array($g)) continue;
        $gid  = (string)($g['id'] ?? '');
        $name = trim((string)($g['name'] ?? ''));
        if ($gid === '' || isset($seen[$gid])) continue;
        if (!empty($g['hasParts'])) {
            $seen[$gid] = true;
            $leaves[$gid] = ['name' => $name !== '' ? $name : $gid, 'depth' => $depth];
            $byDepth[$depth] = ($byDepth[$depth] ?? 0) + 1;
        } else {
            $walk($gid, $depth + 1);
        }
        if ($stopped) return;
    }
};

$walk('', 0);
$sec = round(microtime(true) - $t0, 1);

$total = count($leaves);
echo "\n" . str_repeat('═', 72) . "\n";
echo "РЕАЛЬНОЕ ЧИСЛО УЗЛОВ (со схемами) : $total\n";
echo "Запросов groups2 на обход          : $reqCount   (тариф не тратят)\n";
echo "Время                              : {$sec} сек\n";
if ($stopped) echo "⚠ Сработал предохранитель ($safety) — дерево ещё больше.\n";

echo "\nРаспределение по глубине:\n";
ksort($byDepth);
foreach ($byDepth as $d => $c) {
    echo "  глубина $d: " . str_pad((string)$c, 4, ' ', STR_PAD_LEFT) . " узлов"
       . ($d > 4 ? "   ← боевой обход сюда НЕ доходит (depth > 4)" : "") . "\n";
}

// Сравнение с боевыми лимитами.
$deepLost = 0;
foreach ($byDepth as $d => $c) if ($d > 4) $deepLost += $c;
echo "\nСравнение с боевыми лимитами (depth>4, потолок 120):\n";
echo "  сохранилось бы : " . min(120, $total - $deepLost) . "\n";
echo "  потерялось     : " . max(0, $total - min(120, $total - $deepLost))
   . ($deepLost > 0 ? " (в т.ч. $deepLost из-за глубины)" : "") . "\n";
if ($total > 120) {
    echo "  ⇒ Дерево ОБРЕЗАЕТСЯ. Рекомендуемый catalog_nodes_limit: " . (ceil($total / 50) * 50) . "\n";
} else {
    echo "  ⇒ Дерево помещается в лимит 120.\n";
}

// Что уже собрано у этого авто.
try {
    $st = $db->prepare("SELECT COUNT(*) FROM catalog_library_schemes WHERE catalog_id=? AND car_id=?");
    $st->execute([$catalogId, $carId]);
    echo "\nСхем уже собрано в библиотеке: " . (int)$st->fetchColumn() . " из $total\n";
} catch (Exception $e) {}

echo str_repeat('═', 72) . "\n";
echo "Ничего в БД не изменено — только чтение.\n";
