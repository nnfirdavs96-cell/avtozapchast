<?php
/**
 * AJAX endpoint: send a one-time SMS code for phone registration / login.
 * The actual verification + session creation happens in auth/register.php
 * and auth/login.php (normal form POST), so session handling stays there.
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ошибка безопасности. Обновите страницу.']);
    exit;
}

ensurePhoneAuthSchema();

$phone = (string)($_POST['phone'] ?? '');
$mode  = ($_POST['mode'] ?? 'login') === 'register' ? 'register' : 'login';

$norm = normalizePhone($phone);
if ($norm === '') {
    echo json_encode(['ok' => false, 'error' => 'Введите корректный номер телефона.']);
    exit;
}

$existing = findUserByPhone($norm);
if ($mode === 'register' && $existing) {
    echo json_encode(['ok' => false, 'error' => 'Этот номер уже зарегистрирован. Войдите по номеру.']);
    exit;
}
if ($mode === 'login' && !$existing) {
    echo json_encode(['ok' => false, 'error' => 'Номер не найден. Зарегистрируйтесь по номеру.']);
    exit;
}

$res = createPhoneOtp($norm, $mode);
if (!$res['ok']) {
    echo json_encode(['ok' => false, 'error' => $res['error']]);
    exit;
}

echo json_encode([
    'ok'       => true,
    'dev_code' => $res['dev_code'] ?? null,   // present only in test mode (no real SMS gateway)
]);
