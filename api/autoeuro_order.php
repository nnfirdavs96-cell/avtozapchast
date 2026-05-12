<?php
/**
 * AutoEuro API v2 — create order (AJAX endpoint)
 *
 * POST JSON body:
 * {
 *   "items": [
 *     {"offer_key":"...", "quantity":2, "price":0, "comment":""}
 *   ],
 *   "delivery_key":  "...",   // optional, falls back to setting
 *   "payer_key":     "...",   // optional, falls back to setting
 *   "wait_all":      true,    // optional, default true
 *   "comment":       "...",   // optional
 *   "delivery_date": "YYYY-MM-DD" // optional
 * }
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/autoeuro.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Необходима авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

// Verify CSRF from header (X-CSRF-Token) or body
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$rawBody    = file_get_contents('php://input');
$body       = json_decode($rawBody, true) ?? [];
$csrfBody   = $body['csrf_token'] ?? '';

if (!verifyCsrfToken($csrfHeader ?: $csrfBody)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF ошибка']);
    exit;
}

$ae = AutoEuro::fromSettings();
if (!$ae) {
    http_response_code(503);
    echo json_encode(['error' => 'AutoEuro API отключён или не настроен']);
    exit;
}

// Validate items
$items = $body['items'] ?? [];
if (empty($items) || !is_array($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Список товаров не может быть пустым']);
    exit;
}

$stockItems = [];
foreach ($items as $item) {
    $offerKey = trim($item['offer_key'] ?? '');
    $qty      = (int)($item['quantity'] ?? 0);
    if ($offerKey === '' || $qty <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Некорректный товар: offer_key и quantity обязательны']);
        exit;
    }
    $row = ['offer_key' => $offerKey, 'quantity' => $qty];
    if (isset($item['price']))   $row['price']   = (float)$item['price'];
    if (isset($item['comment'])) $row['comment'] = (string)$item['comment'];
    $stockItems[] = $row;
}

$deliveryKey  = trim($body['delivery_key']  ?? '') ?: getSetting('autoeuro_delivery_key');
$payerKey     = trim($body['payer_key']     ?? '') ?: getSetting('autoeuro_payer_key');
$waitAll      = isset($body['wait_all']) ? (bool)$body['wait_all'] : true;
$comment      = trim($body['comment']      ?? '');
$deliveryDate = trim($body['delivery_date'] ?? '');

if ($deliveryKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'delivery_key не задан. Настройте его в разделе «Склад API».']);
    exit;
}
if ($payerKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'payer_key не задан. Настройте его в разделе «Склад API».']);
    exit;
}

$result = $ae->createOrder($deliveryKey, $payerKey, $stockItems, $waitAll, $comment, $deliveryDate);

if (isset($result['error'])) {
    http_response_code(502);
    echo json_encode($result);
    exit;
}

// Log to warehouse_api_log
try {
    $db = getDB();
    $db->prepare(
        "INSERT INTO warehouse_api_log (action, request_url, response_code, response_body, success, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    )->execute([
        'create_order',
        'autoeuro/create_order',
        200,
        mb_substr(json_encode($result), 0, 2000),
        ($result['result'] ?? false) ? 1 : 0,
    ]);
} catch (Exception $e) {}

echo json_encode([
    'success'            => (bool)($result['result'] ?? false),
    'order_id'           => $result['order_id'] ?? null,
    'result_description' => $result['result_description'] ?? '',
]);
