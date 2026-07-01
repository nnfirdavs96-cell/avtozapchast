<?php
/**
 * AJAX: реальные (car-specific) узлы дерева каталога по VIN.
 * GET ?vin=XXXXXXXXXXXXXXXXX  →  { success, count, nodes:[{cat,name}] }
 *
 * Нужен провайдерам, у которых узлы зависят от конкретного авто (Parts-Catalogs:
 * groups2 по carId). Провайдеры без oemNodesForVin отдают общий oemNodes().
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

$provider = Catalog::provider();
if (!$provider->enabled()) {
    echo json_encode(['success' => false, 'error' => 'disabled', 'nodes' => []]);
    exit;
}

$vin = strtoupper(trim($_GET['vin'] ?? ''));
if (!VinService::validate($vin)) {
    echo json_encode(['success' => false, 'error' => 'bad_vin', 'nodes' => []]);
    exit;
}

@set_time_limit(60);

$nodes = method_exists($provider, 'oemNodesForVin')
    ? $provider->oemNodesForVin($vin)
    : $provider->oemNodes();

$out = [];
foreach ((array)$nodes as $n) {
    $cat = (string)($n['cat'] ?? '');
    if ($cat === '') continue;
    $out[] = ['cat' => $cat, 'name' => (string)($n['name'] ?? $cat)];
}

echo json_encode(['success' => true, 'count' => count($out), 'nodes' => $out], JSON_UNESCAPED_UNICODE);
