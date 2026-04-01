<?php
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

// requireLogin check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

// GET → return cart count
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);
    exit;
}

// POST operations
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    // Fallback to POST form data
    $data = $_POST;
}

$action = $data['action'] ?? '';

function cartTotal(PDO $db, int $userId): float {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(c.quantity * p.price), 0)
         FROM cart c
         JOIN parts p ON p.id = c.part_id
         WHERE c.user_id = ?"
    );
    $stmt->execute([$userId]);
    return (float)$stmt->fetchColumn();
}

function cartCount(PDO $db, int $userId): int {
    $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

switch ($action) {
    case 'add': {
        $partId  = (int)($data['part_id'] ?? 0);
        $qty     = max(1, (int)($data['quantity'] ?? 1));
        if (!$partId) {
            echo json_encode(['success' => false, 'error' => 'Неверный ID товара']);
            exit;
        }
        // Check part exists and in stock
        $partStmt = $db->prepare("SELECT id, stock FROM parts WHERE id = ? AND is_active = 1");
        $partStmt->execute([$partId]);
        $part = $partStmt->fetch();
        if (!$part) {
            echo json_encode(['success' => false, 'error' => 'Товар не найден']);
            exit;
        }
        // Upsert
        $ins = $db->prepare(
            "INSERT INTO cart (user_id, part_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
        );
        $ins->execute([$userId, $partId, $qty]);
        echo json_encode([
            'success'    => true,
            'cart_count' => cartCount($db, $userId),
            'cart_total' => cartTotal($db, $userId),
        ]);
        break;
    }

    case 'remove': {
        $partId = (int)($data['part_id'] ?? 0);
        $del = $db->prepare("DELETE FROM cart WHERE user_id = ? AND part_id = ?");
        $del->execute([$userId, $partId]);
        echo json_encode([
            'success'    => true,
            'cart_count' => cartCount($db, $userId),
            'cart_total' => cartTotal($db, $userId),
        ]);
        break;
    }

    case 'update': {
        $partId = (int)($data['part_id'] ?? 0);
        $qty    = max(1, min(99, (int)($data['quantity'] ?? 1)));
        $upd = $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND part_id = ?");
        $upd->execute([$qty, $userId, $partId]);
        // Row subtotal
        $subStmt = $db->prepare(
            "SELECT c.quantity * p.price FROM cart c JOIN parts p ON p.id = c.part_id WHERE c.user_id = ? AND c.part_id = ?"
        );
        $subStmt->execute([$userId, $partId]);
        $rowSub = (float)$subStmt->fetchColumn();
        echo json_encode([
            'success'       => true,
            'cart_count'    => cartCount($db, $userId),
            'cart_total'    => cartTotal($db, $userId),
            'row_subtotal'  => $rowSub,
        ]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
