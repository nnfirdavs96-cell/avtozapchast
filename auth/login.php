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

                if ($user && password_verify($password, $user['password'])) {
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

<div class="login_register_wrap section_padding">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="login_register_wrapper">

                    <div class="login_register_tab_list">
                        <a class="active" href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a>
                        <a href="<?= APP_URL ?>/auth/register.php"><?= t('register') ?></a>
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

                            <form method="POST"
                                  action="<?= APP_URL ?>/auth/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

                                <div class="form-group">
                                    <label for="login_email"><?= t('email') ?> <span class="required">*</span></label>
                                    <input id="login_email"
                                           type="email"
                                           name="email"
                                           placeholder="your@email.com"
                                           value="<?= sanitize($email) ?>"
                                           required
                                           autocomplete="email">
                                </div>

                                <div class="form-group">
                                    <label for="login_password"><?= t('password') ?> <span class="required">*</span></label>
                                    <input id="login_password"
                                           type="password"
                                           name="password"
                                           placeholder="••••••••"
                                           required
                                           autocomplete="current-password">
                                </div>

                                <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                    <label style="display:flex;align-items:center;gap:8px;margin:0;font-weight:400;cursor:pointer;font-size:0.9rem;">
                                        <input type="checkbox" name="remember_me" value="1">
                                        <?= t('remember_me') ?>
                                    </label>
                                    <a href="#" style="font-size:0.875rem;color:#d32f2f;"><?= t('forgot_password') ?></a>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn"><?= t('sign_in') ?></button>
                                </div>

                                <p style="text-align:center;font-size:0.9rem;color:#666;margin-top:12px;">
                                    <?= t('no_account') ?>
                                    <a href="<?= APP_URL ?>/auth/register.php"
                                       style="color:#d32f2f;font-weight:600;"><?= t('sign_up') ?></a>
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
