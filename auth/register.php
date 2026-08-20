<?php
require_once dirname(__DIR__) . '/config/config.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

ensurePhoneAuthSchema();
ensureStaffPinSchema();

$errors      = [];
$username    = '';
$email       = '';
$regPhone    = '';
$activeTab   = 'phone';   // default tab: quick phone registration
$emailSignup = emailAuthEnabled();   // email registration allowed?
$accountType = 'buyer';   // buyer | seller — выбор типа аккаунта
$sellerShop  = '';
$sellerPhone = '';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email_register') {
    $activeTab = 'email';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } elseif (!emailAuthEnabled()) {
        $errors[] = 'Регистрация по email отключена. Зарегистрируйтесь по номеру телефона.';
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

// ── Seller registration (email + password + shop) ──────────────────────
// Заявка продавца: создаём пользователя role='seller' и магазин в статусе
// 'pending'. Выкладывать товары можно после одобрения модератором.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seller_register') {
    $accountType = 'seller';
    $activeTab   = 'email';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } elseif (!emailAuthEnabled()) {
        $errors[] = 'Регистрация продавца по email сейчас отключена.';
    } else {
        $username        = trim($_POST['username'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $sellerShop      = trim($_POST['shop_name'] ?? '');
        $sellerPhone     = trim($_POST['shop_phone'] ?? '');

        if ($sellerShop === '' || mb_strlen($sellerShop) < 2 || mb_strlen($sellerShop) > 150) {
            $errors[] = 'Введите название магазина (2–150 символов).';
        }
        if ($username === '') {
            $errors[] = 'Введите имя пользователя (логин).';
        } elseif (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
            $errors[] = 'Имя пользователя должно содержать от 2 до 50 символов.';
        }
        if ($email === '') {
            $errors[] = 'Введите адрес email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }
        if ($password === '') {
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
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) $errors[] = 'Пользователь с таким email уже существует.';

                if (empty($errors)) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) $errors[] = 'Это имя пользователя уже занято.';
                }

                if (empty($errors)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $db->prepare(
                        "INSERT INTO users (username, email, password_hash, role, is_active, created_at)
                         VALUES (?, ?, ?, 'seller', 1, NOW())"
                    )->execute([$username, $email, $hash]);
                    $uid = (int)$db->lastInsertId();

                    // slug магазина: латиница из названия, иначе shop-<id>; уникальный.
                    $slugBase = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($sellerShop)), '-');
                    if ($slugBase === '') $slugBase = 'shop-' . $uid;
                    $slug = $slugBase; $k = 0;
                    while (true) {
                        $c = $db->prepare("SELECT id FROM sellers WHERE slug = ? LIMIT 1");
                        $c->execute([$slug]);
                        if (!$c->fetch()) break;
                        $slug = $slugBase . '-' . (++$k);
                    }
                    $commission = (float)str_replace(',', '.', getSetting('marketplace_commission', '0'));
                    $db->prepare(
                        "INSERT INTO sellers (user_id, shop_name, slug, phone, status, commission_percent, created_at)
                         VALUES (?, ?, ?, ?, 'pending', ?, NOW())"
                    )->execute([$uid, $sellerShop, $slug, ($sellerPhone !== '' ? $sellerPhone : null), $commission]);

                    flashMessage('success', 'Заявка продавца принята! После проверки модератором вы сможете войти и выкладывать товары.');
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

                        <!-- Тип аккаунта: Покупатель / Продавец -->
                        <div class="acct_type_toggle">
                            <button type="button" class="acct_type_btn <?= $accountType==='buyer'?'active':'' ?>" data-acct="buyer"><i class="fa fa-user"></i> Покупатель</button>
                            <button type="button" class="acct_type_btn <?= $accountType==='seller'?'active':'' ?>" data-acct="seller"><i class="fa fa-briefcase"></i> Продавец</button>
                        </div>

                        <!-- Покупатель -->
                        <div class="acct_pane" data-acct-pane="buyer" style="<?= $accountType==='seller'?'display:none;':'' ?>">
                        <!-- Tabs -->
                        <div class="auth_tabs">
                            <button type="button" class="auth_tab <?= $activeTab==='phone'?'active':'' ?>" data-auth-tab="phone">
                                <i class="fa fa-mobile"></i> По номеру
                            </button>
                            <?php if ($emailSignup): ?>
                            <button type="button" class="auth_tab <?= $activeTab==='email'?'active':'' ?>" data-auth-tab="email">
                                <i class="fa fa-envelope-o"></i> По email
                            </button>
                            <?php endif; ?>
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
                        <?php if ($emailSignup): ?>
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
                        <?php endif; ?>
                        </div><!-- /pane покупатель -->

                        <!-- Продавец -->
                        <div class="acct_pane" data-acct-pane="seller" style="<?= $accountType==='seller'?'':'display:none;' ?>">
                          <?php if ($emailSignup): ?>
                          <form method="POST" action="<?= APP_URL ?>/auth/register.php" class="account_seller_form">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                            <input type="hidden" name="action" value="seller_register">
                            <p>
                                <label>Название магазина <span>*</span></label>
                                <input type="text" name="shop_name" value="<?= sanitize($sellerShop) ?>" placeholder="Например: Авто Плюс" maxlength="150">
                            </p>
                            <p>
                                <label>Имя пользователя (логин) <span>*</span></label>
                                <input type="text" name="username" value="<?= sanitize($username) ?>" autocomplete="username">
                            </p>
                            <p>
                                <label><?= t('email') ?> <span>*</span></label>
                                <input type="email" name="email" value="<?= sanitize($email) ?>" placeholder="your@email.com" autocomplete="email">
                            </p>
                            <p>
                                <label>Телефон магазина</label>
                                <input type="tel" name="shop_phone" value="<?= sanitize($sellerPhone) ?>" placeholder="+992 __ ___-__-__">
                            </p>
                            <p>
                                <label><?= t('password') ?> <span>*</span></label>
                                <span class="pwd-field">
                                    <input type="password" name="password" autocomplete="new-password" placeholder="<?= t('min_6_chars') ?>">
                                    <button type="button" class="pwd-toggle" aria-label="Показать пароль"><i class="fa fa-eye"></i></button>
                                </span>
                            </p>
                            <p>
                                <label><?= t('confirm_password') ?> <span>*</span></label>
                                <span class="pwd-field">
                                    <input type="password" name="confirm_password" autocomplete="new-password">
                                    <button type="button" class="pwd-toggle" aria-label="Показать пароль"><i class="fa fa-eye"></i></button>
                                </span>
                            </p>
                            <div class="login_submit">
                                <button type="submit">Подать заявку продавца</button>
                            </div>
                            <p class="auth_hint">После проверки модератором вы получите доступ к кабинету продавца и сможете выкладывать свои товары.</p>
                          </form>
                          <?php else: ?>
                          <p class="auth_hint">Регистрация продавца по email сейчас отключена. Обратитесь к администратору.</p>
                          <?php endif; ?>
                        </div><!-- /pane продавец -->
                    </div>
                </div>
                <!--register area end-->
            </div>
        </div>
    </div>
</div>
<!-- customer login end -->

<script>
// Переключатель типа аккаунта (Покупатель / Продавец)
document.addEventListener('click', function(e){
    var b = e.target.closest ? e.target.closest('.acct_type_btn') : null;
    if(!b) return;
    var t = b.getAttribute('data-acct');
    document.querySelectorAll('.acct_type_btn').forEach(function(x){ x.classList.toggle('active', x===b); });
    document.querySelectorAll('.acct_pane').forEach(function(p){ p.style.display = (p.getAttribute('data-acct-pane')===t) ? '' : 'none'; });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
