<?php
/**
 * Заявка на деталь от поставщика (AutoEuro) с выбранным вариантом доставки.
 *
 * Детали AutoEuro нет на нашем складе и в каталоге (нет part_id), поэтому обычная
 * корзина к ним не применима. Покупатель в модалке выбирает вариант срока/цены и
 * оформляет ЗАЯВКУ — она уходит менеджеру в переписку (includes/messaging.php,
 * общая ветка поддержки), где менеджер её обрабатывает: заказывает у поставщика /
 * человека в Москве. Реальные деньги/автозаказ (create_order) — отдельный шаг.
 *
 * POST (JSON или форма): _csrf, oem, brand, name, qty, price, price_raw,
 *   delivery_total, delivery_ae, delivery_days, in_stock, comment.
 * Требуется авторизация (нужен контакт покупателя) — иначе {redirect: login}.
 *   → { success, message } | { redirect }
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/messaging.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

if (!verifyCsrfToken($data['_csrf'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Ошибка проверки безопасности (CSRF).']);
    exit;
}

// Заявка требует авторизации — иначе некуда слать ответ менеджера.
if (!isLoggedIn()) {
    echo json_encode(['redirect' => APP_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['HTTP_REFERER'] ?? (APP_URL . '/pages/vin.php'))]);
    exit;
}
$userId = (int)$_SESSION['user_id'];

$oem   = trim((string)($data['oem'] ?? ''));
$brand = trim((string)($data['brand'] ?? ''));
$name  = trim((string)($data['name'] ?? ''));
$qty   = max(1, min(99, (int)($data['qty'] ?? 1)));
$price = trim((string)($data['price'] ?? ''));          // отформатированная цена (сомони)
$dTotal = trim((string)($data['delivery_total'] ?? '')); // прибытие в Худжанд (YYYY-MM-DD)
$dAe    = trim((string)($data['delivery_ae'] ?? ''));    // прибытие в Москву
$dDays  = (int)($data['delivery_days'] ?? 0);
$inStock = !empty($data['in_stock']);
$comment = trim((string)($data['comment'] ?? ''));

if ($oem === '') {
    echo json_encode(['success' => false, 'message' => 'Не указан артикул.']);
    exit;
}

// Дата «ДД.ММ.ГГГГ» из YYYY-MM-DD (для человекочитаемой заявки).
$fmtDate = static function (string $d): string {
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $d, $m) ? "$m[3].$m[2].$m[1]" : $d;
};

// Текст заявки менеджеру.
$lines = [];
$lines[] = '🛒 Заявка на деталь (поставщик)';
$lines[] = '• ' . ($name !== '' ? $name : 'деталь') . ' — ' . ($brand !== '' ? $brand . ' · ' : '') . $oem;
$lines[] = '• Количество: ' . $qty;
if ($price !== '') $lines[] = '• Цена (с доставкой до Худжанда): ' . preg_replace('/<[^>]+>/', '', $price);
$lines[] = '• Наличие: ' . ($inStock ? 'в наличии у поставщика' : 'под заказ');
if ($dTotal !== '') {
    $lines[] = '• Ожидаемая дата в Худжанде: ' . $fmtDate($dTotal)
             . ($dAe !== '' && $dDays > 0 ? ' (Москва ' . $fmtDate($dAe) . ' + ' . $dDays . ' дн)' : '');
}
if ($comment !== '') $lines[] = '• Комментарий покупателя: ' . mb_substr($comment, 0, 500);

$id = postMessage($userId, null, 'customer', implode("\n", $lines));
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Не удалось отправить заявку. Попробуйте ещё раз.']);
    exit;
}

// Подтверждение покупателю в ту же ветку — чтобы он видел, что заявка принята.
postSystemMessage($userId, null,
    'Ваша заявка принята. Менеджер уточнит наличие и свяжется с вами. Ответить можно здесь, в переписке.');

echo json_encode([
    'success'  => true,
    'message'  => 'Заявка отправлена менеджеру. Ответ придёт в раздел «Сообщения».',
    'messages_url' => APP_URL . '/buyer/messages.php',
], JSON_UNESCAPED_UNICODE);
