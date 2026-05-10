<?php
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────
function cartCount(PDO $db, int $userId): int {
    $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function cartTotal(PDO $db, int $userId): float {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(c.quantity * p.price), 0)
         FROM cart c JOIN parts p ON p.id = c.part_id WHERE c.user_id = ?"
    );
    $stmt->execute([$userId]);
    return (float)$stmt->fetchColumn();
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'count';

    // GET remove (mini-cart link with CSRF in query string)
    if ($action === 'remove') {
        header('Content-Type: text/html'); // redirect response
        if (!isLoggedIn()) { redirect(APP_URL . '/auth/login.php'); }
        if (!verifyCsrfToken($_GET['_csrf'] ?? '')) {
            flashMessage('danger', 'CSRF ошибка.');
            redirect($_SERVER['HTTP_REFERER'] ?? APP_URL . '/buyer/cart.php');
        }
        $partId = (int)($_GET['part_id'] ?? 0);
        $db     = getDB();
        $db->prepare("DELETE FROM cart WHERE user_id = ? AND part_id = ?")->execute([(int)$_SESSION['user_id'], $partId]);
        redirect($_SERVER['HTTP_REFERER'] ?? APP_URL . '/buyer/cart.php');
    }

    // count
    if (!isLoggedIn()) {
        echo json_encode(['cart_count' => 0]);
        exit;
    }
    $db = getDB();
    echo json_encode(['cart_count' => cartCount($db, (int)$_SESSION['user_id'])]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = $data['action'] ?? '';

if (!isLoggedIn()) {
    echo json_encode([
        'success'  => false,
        'redirect' => APP_URL . '/auth/login.php',
        'message'  => 'Для этого действия необходимо войти в аккаунт.',
    ]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

switch ($action) {

    case 'add': {
        $csrf   = $data['_csrf'] ?? '';
        $partId = (int)($data['part_id'] ?? 0);
        $qty    = max(1, min(99, (int)($data['quantity'] ?? 1)));

        if (!verifyCsrfToken($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Ошибка проверки безопасности (CSRF).']);
            exit;
        }
        if (!$partId) {
            echo json_encode(['success' => false, 'message' => 'Не указан товар.']);
            exit;
        }

        $pStmt = $db->prepare("SELECT id, name, stock FROM parts WHERE id = ? AND is_active = 1");
        $pStmt->execute([$partId]);
        $part = $pStmt->fetch();
        if (!$part) {
            echo json_encode(['success' => false, 'message' => 'Товар не найден.']);
            exit;
        }

        $db->prepare(
            "INSERT INTO cart (user_id, part_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), 99), added_at = NOW()"
        )->execute([$userId, $partId, $qty]);

        echo json_encode([
            'success'    => true,
            'cart_count' => cartCount($db, $userId),
            'cart_total' => cartTotal($db, $userId),
            'message'    => 'Товар добавлен в корзину.',
        ]);
        break;
    }

    case 'remove': {
        $csrf   = $data['_csrf'] ?? '';
        $partId = (int)($data['part_id'] ?? 0);

        if (!verifyCsrfToken($csrf)) {
            echo json_encode(['success' => false, 'message' => 'CSRF ошибка.']);
            exit;
        }

        $db->prepare("DELETE FROM cart WHERE user_id = ? AND part_id = ?")->execute([$userId, $partId]);

        echo json_encode([
            'success'    => true,
            'cart_count' => cartCount($db, $userId),
            'cart_total' => cartTotal($db, $userId),
            'message'    => 'Товар удалён из корзины.',
        ]);
        break;
    }

    case 'update': {
        // Update multiple: {action:'update', items:[{part_id:X, quantity:Y}]}
        $items = $data['items'] ?? [];
        if (empty($items) && isset($data['part_id'])) {
            $items = [['part_id' => $data['part_id'], 'quantity' => $data['quantity'] ?? 1]];
        }

        if (!is_array($items)) {
            echo json_encode(['success' => false, 'message' => 'Некорректные данные.']);
            exit;
        }

        foreach ($items as $item) {
            $partId = (int)($item['part_id'] ?? 0);
            $qty    = max(1, min(99, (int)($item['quantity'] ?? 1)));
            if ($partId) {
                $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND part_id = ?")
                   ->execute([$qty, $userId, $partId]);
            }
        }

        $rowSub = 0;
        if (count($items) === 1) {
            $subStmt = $db->prepare(
                "SELECT c.quantity * p.price FROM cart c JOIN parts p ON p.id = c.part_id
                 WHERE c.user_id = ? AND c.part_id = ?"
            );
            $subStmt->execute([$userId, (int)($items[0]['part_id'] ?? 0)]);
            $rowSub = (float)$subStmt->fetchColumn();
        }

        echo json_encode([
            'success'      => true,
            'cart_count'   => cartCount($db, $userId),
            'cart_total'   => cartTotal($db, $userId),
            'row_subtotal' => $rowSub,
            'message'      => 'Корзина обновлена.',
        ]);
        break;
    }

    case 'count': {
        echo json_encode(['cart_count' => cartCount($db, $userId)]);
        break;
    }

    default: {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие: ' . sanitize($action)]);
    }
}
