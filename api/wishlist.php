<?php
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'redirect'=>APP_URL.'/auth/login.php','message'=>t('login_required')]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

function wishCount(int $uid, $db): int {
    $s = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?");
    $s->execute([$uid]);
    return (int)$s->fetchColumn();
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';
$partId = (int)($input['part_id'] ?? $_GET['part_id'] ?? $_POST['part_id'] ?? 0);

if (in_array($action, ['toggle','remove','add'], true)) {
    $csrf = $input['_csrf'] ?? $_GET['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        echo json_encode(['success'=>false,'message'=>'Invalid CSRF']);
        exit;
    }
}

switch ($action) {
    case 'toggle':
        if (!$partId) { echo json_encode(['success'=>false,'message'=>'Invalid part']); exit; }
        $check = $db->prepare("SELECT id FROM wishlist WHERE user_id=? AND part_id=?");
        $check->execute([$userId, $partId]);
        if ($check->fetch()) {
            $db->prepare("DELETE FROM wishlist WHERE user_id=? AND part_id=?")->execute([$userId,$partId]);
            echo json_encode(['success'=>true,'message'=>'Удалено из избранного','wishlist_count'=>wishCount($userId,$db),'in_wishlist'=>false]);
        } else {
            $db->prepare("INSERT INTO wishlist (user_id,part_id) VALUES (?,?) ON DUPLICATE KEY UPDATE added_at=NOW()")->execute([$userId,$partId]);
            echo json_encode(['success'=>true,'message'=>t('added_to_wishlist'),'wishlist_count'=>wishCount($userId,$db),'in_wishlist'=>true]);
        }
        exit;
    case 'remove':
        if (!$partId) { echo json_encode(['success'=>false,'message'=>'Invalid part']); exit; }
        $db->prepare("DELETE FROM wishlist WHERE user_id=? AND part_id=?")->execute([$userId,$partId]);
        $ref = $_SERVER['HTTP_REFERER'] ?? APP_URL.'/buyer/wishlist.php';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo json_encode(['success'=>true,'wishlist_count'=>wishCount($userId,$db)]);
            exit;
        }
        header('Location: '.$ref); exit;
    case 'count':
        echo json_encode(['wishlist_count'=>wishCount($userId,$db)]); exit;
    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action']); exit;
}
