<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Необходима авторизация']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$data   = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []);
$action = $data['action'] ?? '';

if (in_array($action, ['add','update','remove'], true) && !verifyCsrfToken($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Неверный CSRF']);
    exit;
}

function cartCount(PDO $db, int $userId): int {
    $s = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id=?");
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}
function cartTotal(PDO $db, int $userId): float {
    $s = $db->prepare("SELECT COALESCE(SUM(c.quantity*p.price),0) FROM cart c JOIN parts p ON p.id=c.part_id WHERE c.user_id=?");
    $s->execute([$userId]);
    return (float)$s->fetchColumn();
}

switch ($action) {
    case 'add': {
        $partId = (int)($data['part_id'] ?? 0);
        $qty    = max(1, (int)($data['quantity'] ?? 1));
        if (!$partId) { echo json_encode(['success'=>false,'message'=>'Неверный товар']); exit; }
        $p = $db->prepare("SELECT id,stock FROM parts WHERE id=? AND is_active=1");
        $p->execute([$partId]); $part = $p->fetch();
        if (!$part) { echo json_encode(['success'=>false,'message'=>'Товар не найден']); exit; }
        $db->prepare("INSERT INTO cart (user_id,part_id,quantity) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
            ->execute([$userId,$partId,$qty]);
        echo json_encode([
            'success'=>true,
            'message'=>'Товар добавлен в корзину',
            'cart_count'=>cartCount($db,$userId),
            'cart_total'=>cartTotal($db,$userId),
        ]);
        break;
    }
    case 'remove': {
        $partId = (int)($data['part_id'] ?? 0);
        $db->prepare("DELETE FROM cart WHERE user_id=? AND part_id=?")->execute([$userId,$partId]);
        echo json_encode([
            'success'=>true,'message'=>'Удалено',
            'cart_count'=>cartCount($db,$userId),
            'cart_total'=>cartTotal($db,$userId),
        ]);
        break;
    }
    case 'update': {
        $partId = (int)($data['part_id'] ?? 0);
        $qty    = max(1, min(99, (int)($data['quantity'] ?? 1)));
        $db->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND part_id=?")->execute([$qty,$userId,$partId]);
        echo json_encode([
            'success'=>true,'message'=>'Обновлено',
            'cart_count'=>cartCount($db,$userId),
            'cart_total'=>cartTotal($db,$userId),
        ]);
        break;
    }
    case 'clear': {
        $db->prepare("DELETE FROM cart WHERE user_id=?")->execute([$userId]);
        echo json_encode(['success'=>true,'message'=>'Корзина очищена','cart_count'=>0,'cart_total'=>0]);
        break;
    }
    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Неизвестное действие']);
}
