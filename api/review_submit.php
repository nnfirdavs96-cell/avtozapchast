<?php
require_once dirname(__DIR__) . '/config/config.php';

// Form POST → redirect back to product page with a flash message
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/catalog/index.php');
}

$partId = (int)($_POST['part_id'] ?? 0);
$back   = APP_URL . '/catalog/part.php?id=' . $partId . '#reviews';

if (!isLoggedIn()) {
    flashMessage('danger', t('login_to_review'));
    redirect(APP_URL . '/auth/login.php?redirect=' . urlencode('/catalog/part.php?id=' . $partId));
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

// Product must exist and be active
$chk = $db->prepare("SELECT id FROM parts WHERE id = ? AND is_active = 1");
$chk->execute([$partId]);
if (!$chk->fetch()) {
    flashMessage('danger', 'Товар не найден.');
    redirect(APP_URL . '/catalog/index.php');
}

// One review per user per product — resubmit overwrites and returns to moderation
$stmt = $db->prepare(
    "INSERT INTO product_reviews (part_id, user_id, rating, comment, status)
     VALUES (?, ?, ?, ?, 'pending')
     ON DUPLICATE KEY UPDATE
         rating = VALUES(rating),
         comment = VALUES(comment),
         status = 'pending',
         updated_at = CURRENT_TIMESTAMP"
);
$stmt->execute([$partId, $userId, $rating, $comment]);

flashMessage('success', t('review_submitted'));
redirect($back);
