<?php
require_once dirname(__DIR__) . '/config/config.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

ensurePhoneAuthSchema();

$errors    = [];
$username  = '';
$email     = '';
$regPhone  = '';
$activeTab = 'phone';   // default tab: quick phone registration

// ── Phone registration (SMS code) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'phone_register') {
    $activeTab = 'phone';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } else {
        $regPhone = trim($_POST['phone'] ?? '');
        $code     = trim($_POST['code'] ?? '');
        $norm     = normalizePhone($regPhone);

        if ($norm === '') {
            $errors[] = 'Введите корректный номер телефона.';
        } elseif ($code === '') {
            $errors[] = 'Введите код из SMS.';
        } elseif (findUserByPhone($norm)) {
            $errors[] = 'Этот номер уже зарегистрирован. Войдите по номеру.';
        } elseif (!verifyPhoneOtp($norm, $code, 'register')) {
            $errors[] = 'Неверный или просроченный код. Запросите новый.';
        } else {
            try {
                $db = getDB();
                // Generate a unique username from the phone (user + last 4 digits)
                $base = 'user' . substr($norm, -4);
                $uname = $base; $i = 0;
                while (true) {
                    $chk = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $chk->execute([$uname]);
                    if (!$chk->fetch()) break;
                    $uname = $base . (++$i);
                }
                $db->prepare(
                    "INSERT INTO users (username, email, password_hash, role, phone, phone_e164, is_active, created_at)
                     VALUES (?, NULL, NULL, 'buyer', ?, ?, 1, NOW())"
                )->execute([$uname, '+' . $norm, $norm]);
                $newId = (int)$db->lastInsertId();
                $row = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $row->execute([$newId]);
                loginUser($row->fetch());
                flashMessage('success', 'Добро пожаловать! Заполните профиль, чтобы оформлять заказы быстрее.');
                redirect(APP_URL . '/buyer/profile.php');
            } catch (Exception $e) {
                $errors[] = 'Ошибка сервера. Попробуйте позже.';
            }
        }
    }
}

// ── Email registration (login + password) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'phone_register') {
    $activeTab = 'email';
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
                        "INSERT INTO users (username, email, password_hash, role, is_active, created_at)
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

                        <!-- Tabs -->
                        <div class="auth_tabs">
                            <button type="button" class="auth_tab <?= $activeTab==='phone'?'active':'' ?>" data-auth-tab="phone">
                                <i class="fa fa-mobile"></i> По номеру
                            </button>
                            <button type="button" class="auth_tab <?= $activeTab==='email'?'active':'' ?>" data-auth-tab="email">
                                <i class="fa fa-envelope-o"></i> По email
                            </button>
                        </div>

                        <!-- Phone (SMS) registration -->
                        <form method="POST" action="<?= APP_URL ?>/auth/register.php"
                              class="auth_pane" data-auth-pane="phone" style="<?= $activeTab==='phone'?'':'display:none;' ?>"
                              data-sms-mode="register">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="phone_register">

                            <p>
                                <label>Номер телефона <span>*</span></label>
                                <input type="tel" name="phone" data-phone="tj"
                                       value="<?= sanitize($regPhone) ?>"
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
                                <div class="login_submit">
                                    <button type="submit"><?= t('sign_up') ?></button>
                                </div>
                            </div>

                            <p class="auth_hint">Быстрая регистрация: введите номер, получите код по SMS и войдите. Остальное (email, адрес) можно заполнить позже в профиле.</p>
                        </form>

                        <!-- Email + password registration -->
                        <form method="POST" action="<?= APP_URL ?>/auth/register.php"
                              class="auth_pane" data-auth-pane="email" style="<?= $activeTab==='email'?'':'display:none;' ?>">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="email_register">

                            <p>
                                <label><?= t('username') ?> <span>*</span></label>
                                <input type="text" name="username"
                                       placeholder="<?= t('username') ?>"
                                       value="<?= sanitize($username) ?>"
                                       autocomplete="username">
                            </p>
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
                                           placeholder="<?= t('min_6_chars') ?>"
                                           autocomplete="new-password">
                                    <button type="button" class="pwd-toggle" aria-label="Показать пароль">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
                            </p>
                            <p>
                                <label><?= t('confirm_password') ?> <span>*</span></label>
                                <span class="pwd-field">
                                    <input type="password" name="confirm_password"
                                           placeholder="<?= t('confirm_password') ?>"
                                           autocomplete="new-password">
                                    <button type="button" class="pwd-toggle" aria-label="Показать пароль">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
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
