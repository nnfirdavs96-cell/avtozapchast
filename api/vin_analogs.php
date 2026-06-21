<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';

header('Content-Type: application/json; charset=utf-8');

$partId = (int)($_GET['part_id'] ?? 0);
if ($partId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Bad part_id']);
    exit;
}

$analogs = VinService::getAnalogs($partId, 6);

$items = [];
foreach ($analogs as $a) {
    $imgs  = json_decode($a['images'] ?? '[]', true) ?: [];
    $thumb = !empty($imgs[0]) ? productImageUrl($imgs, 0) : '';
    $st    = getStockStatus((int)$a['stock']);
    $items[] = [
        'id'            => (int)$a['id'],
        'name'          => $a['name'],
        'part_number'   => $a['part_number'],
        'brand_name'    => $a['brand_name'] ?? '',
        'category_name' => $a['category_name'] ?? '',
        'price'         => formatPrice($a['price']),
        'price_raw'     => (float)$a['price'],
        'stock_label'   => $st['label'],
        'stock_class'   => $st['class'],
        'thumb'         => $thumb,
        'url'           => partUrl((int)$a['id'], $a['name'] ?? ''),
        'confidence'    => $a['analog_confidence'] ?? 'high',
        'source'        => $a['analog_source'] ?? 'auto',
    ];
}

echo json_encode(['success' => true, 'count' => count($items), 'items' => $items]);
