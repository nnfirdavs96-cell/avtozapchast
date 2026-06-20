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

ensurePhoneAuthSchema();
ensureStaffPinSchema();

$errors      = [];
$email       = '';
$loginPhone  = '';
$activeTab   = 'phone';
$emailLogin  = emailAuthEnabled();      // email tab available?
// Staff can reveal the email form even when it's hidden for buyers
$showEmailTab = $emailLogin || (($_GET['staff'] ?? '') === '1');

$redirectAfterLogin = function (array $user) {
    $redirectTo = $_GET['redirect'] ?? '';
    if ($redirectTo && strpos($redirectTo, '/') === 0 && strpos($redirectTo, '//') !== 0) {
        redirect(APP_URL . $redirectTo);
    }
    $map = [
        'buyer'      => APP_URL . '/buyer/index.php',
        'manager'    => APP_URL . '/manager/index.php',
        'admin'      => APP_URL . '/admin/index.php',
        'superadmin' => APP_URL . '/superadmin/index.php',
    ];
    redirect($map[$user['role']] ?? APP_URL . '/index.php');
};

// ── Phone login (SMS code, or password if the user has set one) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'phone_login') {
    $activeTab = 'phone';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } else {
        $loginPhone = trim($_POST['phone'] ?? '');
        $code       = trim($_POST['code'] ?? '');
        $pin        = trim($_POST['pin'] ?? '');
        $password   = $_POST['password'] ?? '';
        $user       = findUserByPhone($loginPhone);

        if (!$user) {
            $errors[] = 'Номер не найден. Зарегистрируйтесь по номеру.';
        } elseif ($pin !== '') {
            // Staff PIN path (only users who have a PIN set, i.e. staff)
            if (!empty($user['pin_hash']) && password_verify($pin, $user['pin_hash'])) {
                loginUser($user);
                flashMessage('success', 'Добро пожаловать, ' . $user['username'] . '!');
                $redirectAfterLogin($user);
            } else {
                $errors[] = 'Неверный PIN-код.';
            }
        } elseif ($code !== '') {
            // SMS code path
            if (verifyPhoneOtp($loginPhone, $code, 'login')) {
                loginUser($user);
                flashMessage('success', 'Добро пожаловать!');
                $redirectAfterLogin($user);
            } else {
                $errors[] = 'Неверный или просроченный код. Запросите новый.';
            }
        } elseif ($password !== '') {
            // Password path (only if the user has set a password)
            if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                flashMessage('success', 'Добро пожаловать, ' . $user['username'] . '!');
                $redirectAfterLogin($user);
            } else {
                $errors[] = 'Неверный пароль, либо пароль не задан — войдите по SMS-коду.';
            }
        } else {
            $errors[] = 'Введите код из SMS или пароль.';
        }
    }
}

// ── Email + password login ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'phone_login') {
    $activeTab = 'email';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } elseif (!emailAuthEnabled() && ($_POST['staff'] ?? '') !== '1') {
        $errors[] = 'Вход по email отключён. Войдите по номеру телефона.';
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

                // When email login is disabled, the staff bypass only works for staff accounts.
                if ($user && !emailAuthEnabled() && !isStaffRole($user['role'] ?? '')) {
                    $errors[] = 'Вход по email отключён. Войдите по номеру телефона.';
                } elseif ($user && password_verify($password, $user['password_hash'])) {
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

                        <?php $act = APP_URL . '/auth/login.php' . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''); ?>

                        <!-- Tabs -->
                        <div class="auth_tabs">
                            <button type="button" class="auth_tab <?= $activeTab==='phone'?'active':'' ?>" data-auth-tab="phone">
                                <i class="fa fa-mobile"></i> По номеру
                            </button>
                            <?php if ($showEmailTab): ?>
                            <button type="button" class="auth_tab <?= $activeTab==='email'?'active':'' ?>" data-auth-tab="email">
                                <i class="fa fa-envelope-o"></i> По email
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Phone login (SMS or password) -->
                        <form method="POST" action="<?= $act ?>"
                              class="auth_pane" data-auth-pane="phone" style="<?= $activeTab==='phone'?'':'display:none;' ?>"
                              data-sms-mode="login">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="phone_login">

                            <p>
                                <label>Номер телефона <span>*</span></label>
                                <input type="tel" name="phone" data-phone="tj"
                                       value="<?= sanitize($loginPhone) ?>"
                                       placeholder="+992 (__) ___-__-__"
                                       autocomplete="tel" required>
                            </p>

                            <div class="sms_send_row">
                                <button type="button" class="sms_send_btn"><i class="fa fa-paper-plane-o"></i> Получить код</button>
                                <span class="sms_send_status"></span>
                            </div>

                            <div class="sms_code_wrap" style="display:none;">
                                <p>
                                    <label>Код из SMS <span>*</span></label>
                                    <input type="text" name="code" inputmode="numeric" maxlength="4"
                                           placeholder="4 цифры" autocomplete="one-time-code">
                                </p>
                            </div>

                            <div class="pwd_login_wrap" style="display:none;">
                                <p>
                                    <label><?= t('password') ?></label>
                                    <span class="pwd-field">
                                        <input type="password" name="password"
                                               placeholder="••••••••" autocomplete="current-password">
                                        <button type="button" class="pwd-toggle" aria-label="Показать пароль">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </span>
                                </p>
                            </div>

                            <div class="pin_login_wrap" style="display:none;">
                                <p>
                                    <label>PIN-код сотрудника <span>*</span></label>
                                    <span class="pwd-field">
                                        <input type="password" name="pin" inputmode="numeric" maxlength="6"
                                               placeholder="PIN" autocomplete="off">
                                        <button type="button" class="pwd-toggle" aria-label="Показать PIN">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </span>
                                </p>
                            </div>

                            <div class="login_submit">
                                <a href="#" class="pwd_login_toggle">Войти по паролю</a>
                                <button type="submit"><?= t('sign_in') ?></button>
                            </div>
                            <p style="margin-top:10px;font-size:0.82rem;">
                                <a href="#" class="pin_login_toggle" style="color:#888;">Вход для сотрудников (PIN)</a>
                            </p>
                        </form>

                        <!-- Email + password login -->
                        <?php if ($showEmailTab): ?>
                        <form method="POST" action="<?= $act ?>"
                              class="auth_pane" data-auth-pane="email" style="<?= $activeTab==='email'?'':'display:none;' ?>">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="email_login">
                            <?php if (!$emailLogin && $showEmailTab): ?>
                            <input type="hidden" name="staff" value="1">
                            <?php endif; ?>

                            <p>
                                <label><?= t('email') ?> <span>*</span></label>
                                <input type="email" name="email"
                                       placeholder="your@email.com"
                                       value="<?= sanitize($email) ?>"
                                       autocomplete="email">
                            </p>
                            <p>
                                <label><?= t('password') ?> <span>*</span></label>
                                <span class="pwd-field">
                                    <input type="password" name="password"
                                           placeholder="••••••••"
                                           autocomplete="current-password">
                                    <button type="button" class="pwd-toggle" aria-label="Показать пароль">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
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
                        <?php endif; ?>

                        <?php if (!$emailLogin && !$showEmailTab): ?>
                        <p style="margin-top:14px;font-size:0.8rem;text-align:center;">
                            <a href="<?= APP_URL ?>/auth/login.php?staff=1" style="color:#bbb;">Вход для персонала по email</a>
                        </p>
                        <?php endif; ?>
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
