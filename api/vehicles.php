<?php
/**
 * Зависимые списки справочника авто для фильтра каталога и форм товара.
 *   ?action=models&make=<id|slug>[&with_products=1]  → модели марки
 *   ?action=mods&model=<id>                          → модификации модели
 * Публичный (данные каталога), только чтение.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vehicles.php';

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$action = is_scalar($_GET['action'] ?? null) ? (string)$_GET['action'] : '';

if ($action === 'models') {
    $makeKey = is_scalar($_GET['make'] ?? null) ? (string)$_GET['make'] : '';
    $withP   = ($_GET['with_products'] ?? '') === '1';
    $make    = vehFindMake($db, $makeKey);
    if (!$make) { echo json_encode(['ok' => true, 'models' => []]); exit; }
    $models = vehModels($db, (int)$make['id'], $withP);
    $out = array_map(static fn($m) => [
        'id'   => (int)$m['id'],
        'name' => $m['name'],
        'slug' => $m['slug'],
        'cnt'  => isset($m['cnt']) ? (int)$m['cnt'] : null,
    ], $models);
    echo json_encode(['ok' => true, 'make' => ['id' => (int)$make['id'], 'slug' => $make['slug']], 'models' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'mods') {
    $modelId = (int)(is_scalar($_GET['model'] ?? null) ? $_GET['model'] : 0);
    $mods = vehMods($db, $modelId);
    $out = array_map(static fn($m) => [
        'id'    => (int)$m['id'],
        'label' => vehModLabel($m) ?: 'Модификация',
    ], $mods);
    echo json_encode(['ok' => true, 'mods' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad_action']);
