<?php
/**
 * Покупатель пишет продавцу со страницы товара («Написать продавцу»).
 * Только для авторизованных. Сообщение уходит в переписку seller_messages.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller_stats.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'method']); exit; }
if (!verifyCsrfToken($_POST['_csrf'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
if (!isLoggedIn()) { echo json_encode(['ok'=>false,'error'=>'auth','msg'=>'Войдите, чтобы написать продавцу.']); exit; }

$buyerId  = (int)($_SESSION['user_id'] ?? 0);
$sellerId = (int)($_POST['seller_id'] ?? 0);
$partId   = (int)($_POST['part_id'] ?? 0) ?: null;
$body     = trim((string)($_POST['body'] ?? ''));

if ($sellerId <= 0 || $body === '') { echo json_encode(['ok'=>false,'error'=>'bad','msg'=>'Пустое сообщение.']); exit; }

$db = getDB();

// Продавец должен существовать и быть одобрен (не пишем в «никуда»/заблокированным).
$st = $db->prepare("SELECT id FROM sellers WHERE id = ? AND status = 'approved' LIMIT 1");
$st->execute([$sellerId]);
if (!$st->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'seller','msg'=>'Продавец недоступен.']); exit; }

// Нельзя писать самому себе (если продавец открыл свой товар).
$self = $db->prepare("SELECT 1 FROM sellers WHERE id = ? AND user_id = ? LIMIT 1");
$self->execute([$sellerId, $buyerId]);
if ($self->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'self','msg'=>'Это ваш магазин.']); exit; }

$id = sellerPostMessage($db, $sellerId, $buyerId, $partId, 'buyer', $body);
echo json_encode($id ? ['ok'=>true] : ['ok'=>false,'error'=>'save','msg'=>'Не удалось отправить.']);
