<?php
require_once dirname(__DIR__) . '/config/config.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$errors   = [];
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } else {
        $username        = trim($_POST['username'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate fields
        if (empty($username)) {
            $errors[] = 'Введите имя пользователя.';
        } elseif (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
            $errors[] = 'Имя пользователя должно содержать от 2 до 50 символов.';
        }

        if (empty($email)) {
            $errors[] = 'Введите адрес email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }

        if (empty($password)) {
            $errors[] = 'Введите пароль.';
        } elseif (mb_strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать не менее 6 символов.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Пароли не совпадают.';
        }

        if (empty($errors)) {
            try {
                $db = getDB();

                // Check email uniqueness
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'Пользователь с таким email уже существует.';
                }

                // Check username uniqueness
                if (empty($errors)) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Это имя пользователя уже занято.';
                    }
                }

                if (empty($errors)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare(
                        "INSERT INTO users (username, email, password, role, is_active, created_at)
                         VALUES (?, ?, ?, 'buyer', 1, NOW())"
                    );
                    $stmt->execute([$username, $email, $hash]);

                    flashMessage('success', 'Регистрация прошла успешно! Войдите в систему.');
                    redirect(APP_URL . '/auth/login.php');
                }
            } catch (Exception $e) {
                $errors[] = 'Ошибка сервера. Попробуйте позже.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle  = t('register');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?= breadcrumb([
    ['label' => t('home'),     'url' => APP_URL . '/index.php'],
    ['label' => t('register'), 'url' => ''],
]) ?>

<div class="login_register_wrap section_padding">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="login_register_wrapper">

                    <div class="login_register_tab_list">
                        <a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a>
                        <a class="active" href="<?= APP_URL ?>/auth/register.php"><?= t('register') ?></a>
                    </div>

                    <div class="login_form_container">
                        <div class="account_login_form">

                            <?php if (!empty($errors)): ?>
                                <div class="az-alert az-alert-danger" style="margin-bottom:16px;">
                                    <?php foreach ($errors as $err): ?>
                                        <div><?= sanitize($err) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?= APP_URL ?>/auth/register.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

                                <div class="form-group">
                                    <label for="reg_username"><?= t('username') ?> <span class="required">*</span></label>
                                    <input id="reg_username"
                                           type="text"
                                           name="username"
                                           placeholder="Ваше имя пользователя"
                                           value="<?= sanitize($username) ?>"
                                           required
                                           autocomplete="username">
                                </div>

                                <div class="form-group">
                                    <label for="reg_email"><?= t('email') ?> <span class="required">*</span></label>
                                    <input id="reg_email"
                                           type="email"
                                           name="email"
                                           placeholder="your@email.com"
                                           value="<?= sanitize($email) ?>"
                                           required
                                           autocomplete="email">
                                </div>

                                <div class="form-group">
                                    <label for="reg_password"><?= t('password') ?> <span class="required">*</span></label>
                                    <input id="reg_password"
                                           type="password"
                                           name="password"
                                           placeholder="Минимум 6 символов"
                                           required
                                           autocomplete="new-password">
                                </div>

                                <div class="form-group">
                                    <label for="reg_confirm"><?= t('confirm_password') ?> <span class="required">*</span></label>
                                    <input id="reg_confirm"
                                           type="password"
                                           name="confirm_password"
                                           placeholder="Повторите пароль"
                                           required
                                           autocomplete="new-password">
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn"><?= t('sign_up') ?></button>
                                </div>

                                <p style="text-align:center;font-size:0.9rem;color:#666;margin-top:12px;">
                                    <?= t('have_account') ?>
                                    <a href="<?= APP_URL ?>/auth/login.php"
                                       style="color:#d32f2f;font-weight:600;"><?= t('login') ?></a>
                                </p>
                            </form>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
