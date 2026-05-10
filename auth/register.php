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

<!-- customer login start -->
<div class="login_page_bg">
    <div class="container">
        <div class="customer_login">
            <div class="row">
                <!--login panel start-->
                <div class="col-lg-6 col-md-6">
                    <div class="account_form login">
                        <h2><?= t('login') ?></h2>
                        <p><?= t('have_account') ?></p>
                        <p><?= t('login_desc') ?></p>
                        <div class="login_submit">
                            <a href="<?= APP_URL ?>/auth/login.php" class="button"><?= t('sign_in') ?></a>
                        </div>
                    </div>
                </div>
                <!--login panel end-->

                <!--register area start-->
                <div class="col-lg-6 col-md-6">
                    <div class="account_form register">
                        <h2><?= t('register') ?></h2>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert" style="margin-bottom:16px;">
                                <?php foreach ($errors as $err): ?>
                                    <div><?= sanitize($err) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= APP_URL ?>/auth/register.php">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

                            <p>
                                <label><?= t('username') ?> <span>*</span></label>
                                <input type="text"
                                       name="username"
                                       placeholder="<?= t('username') ?>"
                                       value="<?= sanitize($username) ?>"
                                       required
                                       autocomplete="username">
                            </p>
                            <p>
                                <label><?= t('email') ?> <span>*</span></label>
                                <input type="email"
                                       name="email"
                                       placeholder="your@email.com"
                                       value="<?= sanitize($email) ?>"
                                       required
                                       autocomplete="email">
                            </p>
                            <p>
                                <label><?= t('password') ?> <span>*</span></label>
                                <input type="password"
                                       name="password"
                                       placeholder="<?= t('min_6_chars') ?>"
                                       required
                                       autocomplete="new-password">
                            </p>
                            <p>
                                <label><?= t('confirm_password') ?> <span>*</span></label>
                                <input type="password"
                                       name="confirm_password"
                                       placeholder="<?= t('confirm_password') ?>"
                                       required
                                       autocomplete="new-password">
                            </p>
                            <div class="login_submit">
                                <button type="submit"><?= t('sign_up') ?></button>
                            </div>
                        </form>
                    </div>
                </div>
                <!--register area end-->
            </div>
        </div>
    </div>
</div>
<!-- customer login end -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
