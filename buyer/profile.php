<?php
require_once __DIR__ . '/../config/config.php';
requireRole(['buyer','manager','admin','superadmin']);

$db = getDB();
$user = getCurrentUser();
$err = $msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $newPw = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Неверный e-mail';
    } else {
        try {
            if ($newPw !== '') {
                if (strlen($newPw) < 8) $err = 'Пароль не короче 8 символов';
                else {
                    $db->prepare("UPDATE users SET email=?, phone=?, password_hash=? WHERE id=?")
                        ->execute([$email, $phone, password_hash($newPw, PASSWORD_BCRYPT), $_SESSION['user_id']]);
                }
            } else {
                $db->prepare("UPDATE users SET email=?, phone=? WHERE id=?")
                    ->execute([$email, $phone, $_SESSION['user_id']]);
            }
            if (!$err) {
                unset($_SESSION['user_data']);
                $msg = 'Профиль обновлён';
                $user = getCurrentUser();
            }
        } catch (Throwable $e) {
            $err = 'Ошибка: ' . $e->getMessage();
        }
    }
}

$pageTitle = t('profile');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><div class="container">
  <h1><?= t('profile') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><a href="<?= APP_URL ?>/buyer/index.php"><?= t('my_account') ?></a><span class="sep">/</span><span class="current"><?= t('profile') ?></span></nav>
</div></div>

<section class="section">
  <div class="container container-sm">
    <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

    <div class="checkout-card">
      <h3>Личные данные</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <div class="form-group">
          <label class="form-label"><?= t('username') ?></label>
          <input type="text" class="form-input" value="<?= sanitize($user['username']) ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('email') ?></label>
          <input type="email" name="email" class="form-input" value="<?= sanitize($user['email']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?= t('phone') ?></label>
          <input type="tel" name="phone" class="form-input" value="<?= sanitize($user['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Новый пароль (оставьте пустым, если не меняете)</label>
          <input type="password" name="password" class="form-input" minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
