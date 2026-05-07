<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Войдите в аккаунт, чтобы использовать избранное']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$data   = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []);

if (!verifyCsrfToken($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Неверный CSRF']);
    exit;
}

$action = $data['action'] ?? '';
$partId = (int)($data['part_id'] ?? 0);
if (!$partId) { echo json_encode(['success'=>false,'message'=>'Неверный товар']); exit; }

switch ($action) {
    case 'toggle': {
        $check = $db->prepare("SELECT 1 FROM wishlist WHERE user_id=? AND part_id=?");
        $check->execute([$userId, $partId]);
        if ($check->fetchColumn()) {
            $db->prepare("DELETE FROM wishlist WHERE user_id=? AND part_id=?")->execute([$userId, $partId]);
            echo json_encode(['success'=>true,'in_wishlist'=>false,'message'=>'Удалено из избранного']);
        } else {
            $db->prepare("INSERT IGNORE INTO wishlist (user_id, part_id) VALUES (?,?)")->execute([$userId, $partId]);
            echo json_encode(['success'=>true,'in_wishlist'=>true,'message'=>'Добавлено в избранное']);
        }
        break;
    }
    case 'remove': {
        $db->prepare("DELETE FROM wishlist WHERE user_id=? AND part_id=?")->execute([$userId, $partId]);
        echo json_encode(['success'=>true,'message'=>'Удалено']);
        break;
    }
    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Неизвестное действие']);
}
