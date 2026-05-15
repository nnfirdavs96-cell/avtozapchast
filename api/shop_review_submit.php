<?php
require_once dirname(__DIR__) . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/pages/reviews.php');
}

$back = APP_URL . '/pages/reviews.php#form';

if (!isLoggedIn()) {
    flashMessage('danger', t('login_to_review'));
    redirect(APP_URL . '/auth/login.php?redirect=' . urlencode('/pages/reviews.php'));
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    flashMessage('danger', 'CSRF ошибка. Попробуйте снова.');
    redirect($back);
}

$rating  = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$userId  = (int)$_SESSION['user_id'];

if ($rating < 1 || $rating > 5) {
    flashMessage('danger', t('review_rating_invalid'));
    redirect($back);
}
if (mb_strlen($comment) < 10) {
    flashMessage('danger', t('review_too_short'));
    redirect($back);
}
if (mb_strlen($comment) > 2000) {
    $comment = mb_substr($comment, 0, 2000);
}

$db = getDB();
$stmt = $db->prepare(
    "INSERT INTO shop_reviews (user_id, rating, comment, status)
     VALUES (?, ?, ?, 'pending')
     ON DUPLICATE KEY UPDATE
         rating = VALUES(rating),
         comment = VALUES(comment),
         status = 'pending',
         is_featured = 0,
         updated_at = CURRENT_TIMESTAMP"
);
$stmt->execute([$userId, $rating, $comment]);

flashMessage('success', t('review_submitted'));
redirect($back);
