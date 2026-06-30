<?php
/**
 * Цена по OEM-номеру — для ленивой подгрузки цен в каталоге по VIN.
 * GET ?oem=АРТИКУЛ&brand=БРЕНД
 *   → { success, found, price, price_raw, stock, source, delivery, part_id, url }
 *
 * Свой склад → (если включено и настроено) AutoEuro. Результат AutoEuro
 * кэшируется на сервере, поэтому повторные запросы мгновенны.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

$oem   = trim($_GET['oem']   ?? $_GET['art'] ?? '');
$brand = trim($_GET['brand'] ?? '');
if ($oem === '') {
    echo json_encode(['success' => false, 'error' => 'no_oem', 'found' => false]);
    exit;
}

@set_time_limit(30);

$r = Catalog::price()->priceByOem($oem, $brand);
if ($r === null) {
    echo json_encode(['success' => true, 'found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success'   => true,
    'found'     => true,
    'price'     => formatPrice((float)$r['price']),
    'price_raw' => $r['price'],
    'stock'     => $r['stock'],
    'source'    => $r['source'],
    'delivery'  => $r['delivery'] ?? null,
    'part_id'   => $r['part_id'] ?? null,
    'url'       => $r['url'] ?? null,
], JSON_UNESCAPED_UNICODE);
