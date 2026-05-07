<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$db   = getDB();
$data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []);

if (!verifyCsrfToken($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Неверный CSRF']);
    exit;
}

$action = $data['action'] ?? '';
$partId = (int)($data['part_id'] ?? 0);
if (!$partId) { echo json_encode(['success'=>false,'message'=>'Неверный товар']); exit; }

$userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
$sid    = session_id();

function existsCmp(PDO $db, ?int $uid, string $sid, int $partId): bool {
    if ($uid) {
        $s = $db->prepare("SELECT 1 FROM compare_list WHERE user_id=? AND part_id=?");
        $s->execute([$uid, $partId]);
    } else {
        $s = $db->prepare("SELECT 1 FROM compare_list WHERE session_id=? AND part_id=?");
        $s->execute([$sid, $partId]);
    }
    return (bool)$s->fetchColumn();
}

switch ($action) {
    case 'toggle': {
        if (existsCmp($db, $userId, $sid, $partId)) {
            if ($userId) {
                $db->prepare("DELETE FROM compare_list WHERE user_id=? AND part_id=?")->execute([$userId, $partId]);
            } else {
                $db->prepare("DELETE FROM compare_list WHERE session_id=? AND part_id=?")->execute([$sid, $partId]);
            }
            echo json_encode(['success'=>true,'in_compare'=>false,'message'=>'Убрано из сравнения']);
        } else {
            // limit 4 items
            $cntStmt = $userId
                ? $db->prepare("SELECT COUNT(*) FROM compare_list WHERE user_id=?")
                : $db->prepare("SELECT COUNT(*) FROM compare_list WHERE session_id=?");
            $cntStmt->execute([$userId ?: $sid]);
            if ((int)$cntStmt->fetchColumn() >= 4) {
                echo json_encode(['success'=>false,'message'=>'Можно сравнивать до 4 товаров']);
                exit;
            }
            $db->prepare("INSERT INTO compare_list (user_id, session_id, part_id) VALUES (?,?,?)")
                ->execute([$userId, $userId ? null : $sid, $partId]);
            echo json_encode(['success'=>true,'in_compare'=>true,'message'=>'Добавлено в сравнение']);
        }
        break;
    }
    case 'remove': {
        if ($userId) {
            $db->prepare("DELETE FROM compare_list WHERE user_id=? AND part_id=?")->execute([$userId, $partId]);
        } else {
            $db->prepare("DELETE FROM compare_list WHERE session_id=? AND part_id=?")->execute([$sid, $partId]);
        }
        echo json_encode(['success'=>true,'message'=>'Удалено']);
        break;
    }
    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Неизвестное действие']);
}
