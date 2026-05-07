<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Только POST']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Неверный CSRF']);
    exit;
}
$email = trim((string)($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'message'=>'Неверный e-mail']);
    exit;
}
try {
    getDB()->prepare("INSERT IGNORE INTO newsletter (email) VALUES (?)")->execute([$email]);
    sendEmail($email, 'Подписка на рассылку АвтоЗапчасть',
        emailLayout('Спасибо за подписку!',
            "<p>Здравствуйте!</p><p>Вы успешно подписаны на рассылку АвтоЗапчасть. Будем сообщать о новых поступлениях, скидках и полезных статьях.</p>"));
    echo json_encode(['success'=>true,'message'=>'Спасибо! Вы подписаны на рассылку.']);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Ошибка сервера']);
}
