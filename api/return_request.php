<?php
/**
 * Заявка покупателя на возврат и её отзыв (маркетплейс, Фаза 4).
 *
 * Право проверяется внутри `returnRequest()` / `returnCancel()`, а не здесь: это
 * правило безопасности, и оно должно жить в одном месте.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/returns.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(APP_URL . '/buyer/orders.php');
if (!isLoggedIn()) {
    flashMessage('danger', 'Войдите, чтобы оформить возврат.');
    redirect(APP_URL . '/auth/login.php');
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    flashMessage('danger', 'Ошибка безопасности. Обновите страницу.');
    redirect(APP_URL . '/buyer/orders.php');
}

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

if (($_POST['do'] ?? '') === 'cancel') {
    [$ok, $msg] = returnCancel($db, (int)($_POST['return_id'] ?? 0), $uid);
} else {
    [$ok, $msg] = returnRequest(
        $db, $uid,
        (int)($_POST['order_seller_id'] ?? 0),
        (int)($_POST['order_item_id'] ?? 0),
        (string)($_POST['reason'] ?? 'other'),
        (string)($_POST['comment'] ?? '')
    );
}

flashMessage($ok ? 'success' : 'danger', $msg);
redirect(APP_URL . '/buyer/orders.php');
