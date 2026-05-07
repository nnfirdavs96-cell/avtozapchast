<?php
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) redirect(APP_URL . '/buyer/index.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = 'Неверный CSRF-токен';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $pw       = (string)($_POST['password'] ?? '');
        $pw2      = (string)($_POST['password2'] ?? '');

        if (mb_strlen($username) < 3) $err = 'Имя пользователя минимум 3 символа';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Неверный e-mail';
        elseif (strlen($pw) < 8) $err = 'Пароль минимум 8 символов';
        elseif ($pw !== $pw2) $err = 'Пароли не совпадают';
        else {
            try {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                getDB()->prepare("INSERT INTO users (username,email,phone,password_hash,role) VALUES (?,?,?,?,'buyer')")
                    ->execute([$username, $email, $phone ?: null, $hash]);
                $userId = (int)getDB()->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['role']    = 'buyer';

                sendEmail($email, 'Добро пожаловать в АвтоЗапчасть',
                    emailLayout('Регистрация прошла успешно',
                        "<p>Здравствуйте, {$username}!</p><p>Спасибо за регистрацию в АвтоЗапчасть. Теперь вы можете оформлять заказы, сохранять избранное и оставлять отзывы.</p>"));

                flashMessage('success', 'Регистрация прошла успешно!');
                redirect(APP_URL . '/buyer/index.php');
            } catch (PDOException $e) {
                $err = $e->getCode() === '23000' ? 'Пользователь с таким e-mail уже существует' : 'Ошибка регистрации';
            }
        }
    }
}

$pageTitle = t('register_title');
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-wrap">
  <div class="auth-card">
    <h2><?= t('register_title') ?></h2>
    <p class="sub">Создайте аккаунт за 30 секунд</p>

    <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
      <div class="form-group">
        <label class="form-label"><?= t('username') ?></label>
        <input type="text" name="username" class="form-input" required minlength="3" value="<?= sanitize($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('email') ?></label>
        <input type="email" name="email" class="form-input" required value="<?= sanitize($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('phone') ?></label>
        <input type="tel" name="phone" class="form-input" value="<?= sanitize($_POST['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('password') ?></label>
        <input type="password" name="password" class="form-input" required minlength="8">
      </div>
      <div class="form-group">
        <label class="form-label"><?= t('password_confirm') ?></label>
        <input type="password" name="password2" class="form-input" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg"><?= t('register') ?></button>
    </form>

    <div class="auth-foot">
      <?= t('have_account') ?> <a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
