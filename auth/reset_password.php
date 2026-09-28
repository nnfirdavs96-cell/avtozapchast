<?php
/**
 * Сброс пароля по ссылке из письма.
 *
 * GET  — проверяем токен из адреса, показываем форму нового пароля.
 * POST — снова проверяем токен (уже из скрытого поля), ставим новый пароль и
 *        гасим токен. Токен одноразовый и живёт 1 час (см. includes/password_reset.php).
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/password_reset.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$db      = getDB();
$errors  = [];
$token   = '';
$valid   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
        $token = trim($_POST['token'] ?? '');
    } else {
        $token    = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (passwordResetLookup($db, $token) === null) {
            $errors[] = 'Ссылка недействительна или устарела. Запросите сброс заново.';
        } elseif (mb_strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать не менее 6 символов.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Пароли не совпадают.';
        } else {
            [$ok, $err] = passwordResetConsume($db, $token, $password);
            if ($ok) {
                flashMessage('success', 'Пароль изменён. Теперь войдите с новым паролем.');
                redirect(APP_URL . '/auth/login.php');
            }
            $errors[] = $err;
        }
    }
    // После неуспешного POST проверим, показывать ли форму дальше.
    $valid = ($token !== '' && passwordResetLookup($db, $token) !== null);
} else {
    $token = trim($_GET['token'] ?? '');
    $valid = ($token !== '' && passwordResetLookup($db, $token) !== null);
    if (!$valid) {
        $errors[] = 'Ссылка недействительна или устарела. Запросите сброс пароля заново.';
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Новый пароль';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?= breadcrumb([
    ['label' => t('home'),  'url' => APP_URL . '/index.php'],
    ['label' => t('login'), 'url' => APP_URL . '/auth/login.php'],
    ['label' => 'Новый пароль', 'url' => ''],
]) ?>

<div class="login_page_bg">
    <div class="container">
        <div class="customer_login">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="account_form login">
                        <h2>Новый пароль</h2>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert" style="margin-bottom:16px;">
                                <?php foreach ($errors as $err): ?><div><?= sanitize($err) ?></div><?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($valid): ?>
                            <p style="color:#666;margin-bottom:16px;">Придумайте новый пароль для входа.</p>
                            <form method="post" action="">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                <input type="hidden" name="token" value="<?= sanitize($token) ?>">
                                <p>
                                    <label>Новый пароль <span>*</span></label>
                                    <input type="password" name="password" placeholder="не менее 6 символов"
                                           minlength="6" required autofocus autocomplete="new-password">
                                </p>
                                <p>
                                    <label>Повторите пароль <span>*</span></label>
                                    <input type="password" name="confirm_password" placeholder="ещё раз"
                                           minlength="6" required autocomplete="new-password">
                                </p>
                                <div class="login_submit">
                                    <button type="submit">Сохранить пароль</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="login_submit">
                                <a href="<?= APP_URL ?>/auth/forgot_password.php" class="button">Запросить сброс заново</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
