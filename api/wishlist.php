<?php
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

function wishlistCount(PDO $db, int $userId): int {
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'count';

    if ($action === 'remove') {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Требуется авторизация.']);
            exit;
        }
        if (!verifyCsrfToken($_GET['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'CSRF ошибка.']);
            exit;
        }
        $partId = (int)($_GET['part_id'] ?? 0);
        $db     = getDB();
        $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND part_id = ?")->execute([(int)$_SESSION['user_id'], $partId]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo json_encode([
                'success'        => true,
                'wishlist_count' => wishlistCount($db, (int)$_SESSION['user_id']),
                'message'        => 'Товар удалён из избранного.',
            ]);
        } else {
            header('Content-Type: text/html');
            redirect($_SERVER['HTTP_REFERER'] ?? APP_URL . '/buyer/wishlist.php');
        }
        exit;
    }

    // count
    if (!isLoggedIn()) {
        echo json_encode(['wishlist_count' => 0]);
        exit;
    }
    $db = getDB();
    echo json_encode(['wishlist_count' => wishlistCount($db, (int)$_SESSION['user_id'])]);
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

    case 'toggle': {
        $csrf   = $data['_csrf'] ?? '';
        $partId = (int)($data['part_id'] ?? 0);

        if (!verifyCsrfToken($csrf)) {
            echo json_encode(['success' => false, 'message' => 'CSRF ошибка.']);
            exit;
        }
        if (!$partId) {
            echo json_encode(['success' => false, 'message' => 'Не указан товар.']);
            exit;
        }

        $pStmt = $db->prepare("SELECT id FROM parts WHERE id = ? AND is_active = 1");
        $pStmt->execute([$partId]);
        if (!$pStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Товар не найден.']);
            exit;
        }

        $chkStmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND part_id = ?");
        $chkStmt->execute([$userId, $partId]);
        $exists = $chkStmt->fetch();

        if ($exists) {
            $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND part_id = ?")->execute([$userId, $partId]);
            $message = 'Товар удалён из избранного.';
            $added   = false;
        } else {
            $db->prepare("INSERT IGNORE INTO wishlist (user_id, part_id) VALUES (?, ?)")->execute([$userId, $partId]);
            $message = 'Товар добавлен в избранное.';
            $added   = true;
        }

        echo json_encode([
            'success'        => true,
            'added'          => $added,
            'wishlist_count' => wishlistCount($db, $userId),
            'message'        => $message,
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

        $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND part_id = ?")->execute([$userId, $partId]);

        echo json_encode([
            'success'        => true,
            'wishlist_count' => wishlistCount($db, $userId),
            'message'        => 'Товар удалён из избранного.',
        ]);
        break;
    }

    case 'count': {
        echo json_encode(['wishlist_count' => wishlistCount($db, $userId)]);
        break;
    }

    default: {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие: ' . sanitize($action)]);
    }
}
