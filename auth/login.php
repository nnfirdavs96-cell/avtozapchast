<?php
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/buyer/index.php');
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = 'Неверный CSRF-токен';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $pw    = (string)($_POST['password'] ?? '');
        $stmt  = getDB()->prepare("SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pw, $user['password_hash'])) {
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['user_data'] = $user;
            $redirect = $_GET['redirect'] ?? APP_URL . '/buyer/index.php';
            flashMessage('success', 'Добро пожаловать, ' . sanitize($user['username']) . '!');
            redirect($redirect);
        } else {
            $err = 'Неверный e-mail или пароль';
        }
    }
}

$pageTitle = t('login_title');
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-wrap">
  <div class="auth-card">
    <h2><?= t('login_title') ?></h2>
    <p class="sub">Войдите, чтобы оформить заказ или сохранить избранное</p>

    <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
      <div class="form-group">
        <label class="form-label"><?= t('email') ?></label>
        <input type="email" name="email" class="form-input" required autofocus value="<?= sanitize($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('password') ?></label>
        <input type="password" name="password" class="form-input" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg"><?= t('login') ?></button>
    </form>

    <div class="auth-foot">
      <?= t('no_account') ?> <a href="<?= APP_URL ?>/auth/register.php"><?= t('register') ?></a>
    </div>
    <div class="text-muted text-center mt-16" style="font-size:0.78rem;border-top:1px solid var(--border-soft);padding-top:14px">
      Демо-аккаунты:<br>
      buyer@avtozapchast.ru · admin@avtozapchast.ru<br>
      manager@avtozapchast.ru · superadmin@avtozapchast.ru<br>
      Пароль: <code>Password123!</code>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
