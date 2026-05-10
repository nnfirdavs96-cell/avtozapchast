<?php
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cartCount(int $uid, $db): int {
    $s = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id=?");
    $s->execute([$uid]);
    return (int)$s->fetchColumn();
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

if (!isLoggedIn()) {
    jsonOut(['success'=>false,'redirect'=>APP_URL.'/auth/login.php','message'=>t('login_required')]);
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

switch ($action) {
    case 'add':
        $partId  = (int)($input['part_id'] ?? $_POST['part_id'] ?? 0);
        $qty     = max(1, (int)($input['quantity'] ?? $_POST['quantity'] ?? 1));
        $csrf    = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
        if (!$partId) jsonOut(['success'=>false,'message'=>'Invalid part']);
        // Check part exists
        $p = $db->prepare("SELECT id, stock FROM parts WHERE id=? AND is_active=1");
        $p->execute([$partId]);
        $part = $p->fetch();
        if (!$part) jsonOut(['success'=>false,'message'=>'Товар не найден']);
        // Upsert
        $db->prepare("INSERT INTO cart (user_id,part_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+?")->execute([$userId,$partId,$qty,$qty]);
        jsonOut(['success'=>true,'message'=>t('added_to_cart'),'cart_count'=>cartCount($userId,$db)]);

    case 'remove':
        $partId = (int)($input['part_id'] ?? $_GET['part_id'] ?? $_POST['part_id'] ?? 0);
        $csrf   = $input['_csrf'] ?? $_GET['_csrf'] ?? $_POST['_csrf'] ?? '';
        if (!verifyCsrfToken($csrf)) jsonOut(['success'=>false,'message'=>'Invalid CSRF']);
        if (!$partId) jsonOut(['success'=>false,'message'=>'Invalid part']);
        $db->prepare("DELETE FROM cart WHERE user_id=? AND part_id=?")->execute([$userId,$partId]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($input)) {
            jsonOut(['success'=>true,'cart_count'=>cartCount($userId,$db)]);
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? APP_URL.'/buyer/cart.php';
        header('Location: '.$ref); exit;

    case 'update':
        $items = $input['items'] ?? [];
        if (!is_array($items)) jsonOut(['success'=>false,'message'=>'Invalid items']);
        foreach ($items as $item) {
            $partId = (int)($item['part_id'] ?? 0);
            $qty    = max(0, (int)($item['quantity'] ?? 1));
            if (!$partId) continue;
            if ($qty === 0) {
                $db->prepare("DELETE FROM cart WHERE user_id=? AND part_id=?")->execute([$userId,$partId]);
            } else {
                $db->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND part_id=?")->execute([$qty,$userId,$partId]);
            }
        }
        jsonOut(['success'=>true,'cart_count'=>cartCount($userId,$db)]);

    case 'count':
        jsonOut(['cart_count'=>cartCount($userId,$db)]);

    default:
        jsonOut(['success'=>false,'message'=>'Unknown action']);
}
