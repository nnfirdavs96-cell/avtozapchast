<?php
require_once dirname(__DIR__) . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'buyer';
    $redirectMap = [
        'buyer'      => APP_URL . '/buyer/index.php',
        'manager'    => APP_URL . '/manager/index.php',
        'admin'      => APP_URL . '/admin/index.php',
        'superadmin' => APP_URL . '/superadmin/index.php',
    ];
    redirect($redirectMap[$role] ?? APP_URL . '/index.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember_me']);

        if (empty($email)) {
            $errors[] = 'Введите адрес email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }
        if (empty($password)) {
            $errors[] = 'Введите пароль.';
        }

        if (empty($errors)) {
            try {
                $db   = getDB();
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);

                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['role']     = $user['role'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_data'] = [
                        'id'        => $user['id'],
                        'username'  => $user['username'],
                        'email'     => $user['email'],
                        'role'      => $user['role'],
                        'phone'     => $user['phone'] ?? '',
                        'is_active' => $user['is_active'],
                    ];

                    // Remember me: extend session cookie lifetime
                    if ($remember) {
                        $params = session_get_cookie_params();
                        setcookie(
                            session_name(),
                            session_id(),
                            time() + 86400 * 30,
                            $params['path'],
                            $params['domain'],
                            $params['secure'],
                            $params['httponly']
                        );
                    }

                    flashMessage('success', 'Добро пожаловать, ' . $user['username'] . '!');

                    // Honour redirect param if safe (relative URL only)
                    $redirectTo = $_GET['redirect'] ?? '';
                    if ($redirectTo && strpos($redirectTo, '/') === 0 && strpos($redirectTo, '//') !== 0) {
                        redirect(APP_URL . $redirectTo);
                    }

                    $redirectMap = [
                        'buyer'      => APP_URL . '/buyer/index.php',
                        'manager'    => APP_URL . '/manager/index.php',
                        'admin'      => APP_URL . '/admin/index.php',
                        'superadmin' => APP_URL . '/superadmin/index.php',
                    ];
                    redirect($redirectMap[$user['role']] ?? APP_URL . '/index.php');

                } else {
                    $errors[] = 'Неверный email или пароль.';
                }
            } catch (Exception $e) {
                $errors[] = 'Ошибка сервера. Попробуйте позже.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle  = t('login');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?= breadcrumb([
    ['label' => t('home'),  'url' => APP_URL . '/index.php'],
    ['label' => t('login'), 'url' => ''],
]) ?>

<!-- customer login start -->
<div class="login_page_bg">
    <div class="container">
        <div class="customer_login">
            <div class="row">
                <!--login area start-->
                <div class="col-lg-6 col-md-6">
                    <div class="account_form login">
                        <h2><?= t('login') ?></h2>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert" style="margin-bottom:16px;">
                                <?php foreach ($errors as $err): ?>
                                    <div><?= sanitize($err) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST"
                              action="<?= APP_URL ?>/auth/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

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
                                       placeholder="••••••••"
                                       required
                                       autocomplete="current-password">
                            </p>
                            <div class="login_submit">
                                <a href="#"><?= t('forgot_password') ?></a>
                                <label for="remember_me">
                                    <input id="remember_me" type="checkbox" name="remember_me" value="1">
                                    <?= t('remember_me') ?>
                                </label>
                                <button type="submit"><?= t('sign_in') ?></button>
                            </div>
                        </form>
                    </div>
                </div>
                <!--login area end-->

                <!--register panel start-->
                <div class="col-lg-6 col-md-6">
                    <div class="account_form register">
                        <h2><?= t('register') ?></h2>
                        <p><?= t('no_account') ?></p>
                        <p><?= t('register_desc') ?></p>
                        <div class="login_submit">
                            <a href="<?= APP_URL ?>/auth/register.php" class="button"><?= t('sign_up') ?></a>
                        </div>
                    </div>
                </div>
                <!--register panel end-->
            </div>
        </div>
    </div>
</div>
<!-- customer login end -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
