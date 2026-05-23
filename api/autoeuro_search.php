<?php
/**
 * AutoEuro API v2 — search items (AJAX endpoint)
 *
 * GET/POST params:
 *   code          - part number (required)
 *   brand         - brand name (required)
 *   delivery_key  - delivery key; falls back to autoeuro_delivery_key setting
 *   with_crosses  - 1/0 (default 1)
 *   with_offers   - 1/0 (default 0)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/autoeuro.php';

header('Content-Type: application/json; charset=utf-8');

// Only logged-in users may search
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Необходима авторизация']);
    exit;
}

$ae = AutoEuro::fromSettings();
if (!$ae) {
    http_response_code(503);
    echo json_encode(['error' => 'AutoEuro API отключён или не настроен']);
    exit;
}

$p = static fn(string $k, string $d = ''): string =>
    is_scalar($_REQUEST[$k] ?? null) ? trim((string)$_REQUEST[$k]) : $d;

$code        = $p('code');
$brand       = $p('brand');
$deliveryKey = $p('delivery_key') ?: getSetting('autoeuro_delivery_key');
$withCrosses = $p('with_crosses', '1') === '1';
$withOffers  = $p('with_offers',  '0') === '1';

if ($code === '' || $brand === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Необходимо указать code и brand']);
    exit;
}
if ($deliveryKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'delivery_key не задан. Настройте его в разделе «Склад API».']);
    exit;
}

$result = $ae->searchItems($brand, $code, $deliveryKey, $withCrosses, $withOffers);

if (isset($result['error'])) {
    http_response_code(502);
    echo json_encode($result);
    exit;
}

// Normalize to always return an array of offers
$offers = is_array($result) && isset($result[0]) ? $result : (array)$result;

// Keep only fields the frontend needs
$output = [];
foreach ($offers as $item) {
    if (!is_array($item)) continue;
    $output[] = [
        'offer_key'         => $item['offer_key']         ?? '',
        'brand'             => $item['brand']             ?? '',
        'code'              => $item['code']              ?? '',
        'name'              => $item['name']              ?? '',
        'price'             => (float)($item['price']     ?? 0),
        'currency'          => $item['currency']          ?? 'RUB',
        'amount'            => (int)($item['amount']      ?? 0),
        'unit'              => $item['unit']              ?? 'шт',
        'packing'           => (int)($item['packing']     ?? 0),
        'stock'             => (int)($item['stock']       ?? 0),
        'cross'             => $item['cross'] ?? null,
        'return'            => (int)($item['return']      ?? 0),
        'dealer'            => (int)($item['dealer']      ?? 0),
        'rejects'           => (float)($item['rejects']   ?? 0),
        'warehouse_name'    => $item['warehouse_name']    ?? null,
        'warehouse_key'     => $item['warehouse_key']     ?? null,
        'order_before'      => $item['order_before']      ?? null,
        'delivery_time'     => $item['delivery_time']     ?? null,
        'delivery_time_max' => $item['delivery_time_max'] ?? null,
        'product_id'        => $item['product_id']        ?? null,
    ];
}

echo json_encode(['offers' => $output, 'count' => count($output)]);
