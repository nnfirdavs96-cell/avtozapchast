<?php
/**
 * Отзыв покупателя о продавце (маркетплейс, Фаза 4).
 *
 * Право оставить отзыв проверяется в `sellerReviewSubmit()`, а не здесь: это
 * правило безопасности, и оно должно жить в одном месте — иначе вторая точка
 * входа однажды забудет его повторить.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller_reviews.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/buyer/orders.php');
}
if (!isLoggedIn()) {
    flashMessage('danger', 'Войдите, чтобы оставить отзыв.');
    redirect(APP_URL . '/auth/login.php');
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    flashMessage('danger', 'Ошибка безопасности. Обновите страницу.');
    redirect(APP_URL . '/buyer/orders.php');
}

[$ok, $msg] = sellerReviewSubmit(
    getDB(),
    (int)$_SESSION['user_id'],
    (int)($_POST['order_seller_id'] ?? 0),
    (int)($_POST['rating'] ?? 0),
    (string)($_POST['comment'] ?? '')
);

flashMessage($ok ? 'success' : 'danger', $msg);
redirect(APP_URL . '/buyer/orders.php');
