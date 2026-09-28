<?php
/**
 * «Забыли пароль?» — запрос ссылки на сброс.
 *
 * Из соображений безопасности НЕ раскрываем, есть ли такой email: ответ всегда
 * одинаковый («если адрес есть — письмо отправлено»). Иначе форму можно было бы
 * использовать для проверки, кто у нас зарегистрирован.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/password_reset.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$errors = [];
$done   = false;
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Неверный токен безопасности. Обновите страницу.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный адрес email.';
        } elseif (!passwordResetReady()) {
            $errors[] = 'Восстановление пароля пока не настроено. Обратитесь к администратору.';
        } elseif (!mailerReady()) {
            $errors[] = 'Отправка почты не настроена. Обратитесь к администратору.';
        } else {
            $db = getDB();
            // Ищем активный аккаунт с этим email и с паролем (email-аккаунт).
            $st = $db->prepare("SELECT id, username, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $st->execute([$email]);
            $user = $st->fetch();

            if ($user) {
                $raw = passwordResetCreate($db, (int)$user['id']);
                if ($raw !== '') {
                    $link = APP_URL . '/auth/reset_password.php?token=' . $raw;
                    $safeLink = sanitize($link);
                    notifyUser($db, (int)$user['id'],
                        'Восстановление пароля — ' . getSetting('site_name', 'AutoDoc'),
                        '<p>Здравствуйте, ' . sanitize($user['username']) . '!</p>'
                        . '<p>Вы (или кто-то от вашего имени) запросили сброс пароля на сайте '
                        . '<b>' . sanitize(getSetting('site_name', 'AutoDoc')) . '</b>. '
                        . 'Чтобы задать новый пароль, нажмите кнопку ниже:</p>'
                        . '<p style="margin:22px 0;"><a href="' . $safeLink . '" '
                        . 'style="background:#C70909;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:700;display:inline-block;">Задать новый пароль</a></p>'
                        . '<p style="color:#777;font-size:13px;">Ссылка действует <b>1 час</b> и сработает один раз. '
                        . 'Если кнопка не открывается, скопируйте адрес в браузер:<br>'
                        . '<span style="word-break:break-all;">' . $safeLink . '</span></p>'
                        . '<p style="color:#777;font-size:13px;">Если вы не запрашивали сброс — просто проигнорируйте это письмо, пароль останется прежним.</p>',
                        'Восстановление пароля');
                }
            }
            // Ответ всегда одинаковый — не выдаём, есть ли такой email.
            $done = true;
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Восстановление пароля';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?= breadcrumb([
    ['label' => t('home'),  'url' => APP_URL . '/index.php'],
    ['label' => t('login'), 'url' => APP_URL . '/auth/login.php'],
    ['label' => 'Восстановление пароля', 'url' => ''],
]) ?>

<div class="login_page_bg">
    <div class="container">
        <div class="customer_login">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="account_form login">
                        <h2>Восстановление пароля</h2>

                        <?php if ($done): ?>
                            <div class="alert alert-success" role="alert" style="margin-bottom:16px;">
                                Если аккаунт с таким email существует, мы отправили на него письмо со ссылкой для сброса пароля.
                                Проверьте почту (и папку «Спам»). Ссылка действует 1 час.
                            </div>
                            <div class="login_submit">
                                <a href="<?= APP_URL ?>/auth/login.php" class="button">Вернуться ко входу</a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger" role="alert" style="margin-bottom:16px;">
                                    <?php foreach ($errors as $err): ?><div><?= sanitize($err) ?></div><?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <p style="color:#666;margin-bottom:16px;">
                                Укажите email, на который зарегистрирован аккаунт — мы пришлём ссылку для сброса пароля.
                            </p>
                            <form method="post" action="">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                <p>
                                    <label>Email <span>*</span></label>
                                    <input type="email" name="email" value="<?= sanitize($email) ?>"
                                           placeholder="you@example.com" required autofocus>
                                </p>
                                <div class="login_submit">
                                    <a href="<?= APP_URL ?>/auth/login.php">Вспомнили пароль? Войти</a>
                                    <button type="submit">Отправить ссылку</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
