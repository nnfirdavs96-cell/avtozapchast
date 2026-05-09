<?php
require_once dirname(__DIR__) . '/config/config.php';

// Already logged in → redirect
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'buyer';
    switch ($role) {
        case 'superadmin': redirect(APP_URL . '/superadmin/index.php');
        case 'admin':      redirect(APP_URL . '/admin/index.php');
        case 'manager':    redirect(APP_URL . '/manager/index.php');
        default:           redirect(APP_URL . '/buyer/index.php');
    }
}

$errors = [];
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $emailVal = $email;

        if (empty($email) || empty($password)) {
            $errors[] = 'Введите email и пароль.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, username, email, password_hash, role, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Неверный email или пароль.';
            } elseif (!$user['is_active']) {
                $errors[] = 'Ваш аккаунт деактивирован. Обратитесь к администратору.';
            } else {
                // Auth OK
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = $user['role'];
                unset($_SESSION['user_data']); // force reload

                flashMessage('success', 'Добро пожаловать, ' . $user['username'] . '!');

                // Redirect based on role or ?redirect param
                $redirect = $_GET['redirect'] ?? '';
                if ($redirect && strpos($redirect, APP_URL) === 0) {
                    redirect($redirect);
                }
                switch ($user['role']) {
                    case 'superadmin': redirect(APP_URL . '/superadmin/index.php');
                    case 'admin':      redirect(APP_URL . '/admin/index.php');
                    case 'manager':    redirect(APP_URL . '/manager/index.php');
                    default:           redirect(APP_URL . '/buyer/index.php');
                }
            }
        }
    }
}

$pageTitle = 'Вход в систему';
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> | АвтоЗапчасть</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <style>
    body { min-height: 100vh; display: flex; flex-direction: column; justify-content: center; background: var(--bg-primary); }
    .auth-wrap {
      display: flex;
      min-height: 100vh;
    }
    .auth-left {
      flex: 1;
      background: var(--bg-secondary);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 60px 40px;
      border-right: 1px solid var(--border);
      position: relative;
      overflow: hidden;
    }
    .auth-left::before {
      content: '';
      position: absolute;
      top: -100px;
      left: -100px;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
      pointer-events: none;
    }
    .auth-left-logo {
      font-family: var(--font-display);
      font-size: 3rem;
      letter-spacing: 4px;
      color: var(--text-primary);
      margin-bottom: 12px;
    }
    .auth-left-logo span { color: var(--accent); }
    .auth-left-tagline {
      font-family: var(--font-mono);
      font-size: 0.75rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.15em;
      margin-bottom: 48px;
    }
    .auth-left-features { list-style: none; padding: 0; margin: 0; }
    .auth-left-features li {
      display: flex;
      align-items: center;
      gap: 12px;
      color: var(--text-secondary);
      font-size: 0.875rem;
      margin-bottom: 16px;
    }
    .auth-left-features li::before {
      content: '';
      width: 6px;
      height: 6px;
      background: var(--accent);
      border-radius: 50%;
      flex-shrink: 0;
    }
    .auth-right {
      width: 480px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 60px 48px;
      background: var(--bg-primary);
    }
    @media (max-width: 768px) {
      .auth-left { display: none; }
      .auth-right { width: 100%; padding: 40px 24px; }
    }
    .auth-title {
      font-family: var(--font-display);
      font-size: 2.2rem;
      color: var(--text-primary);
      letter-spacing: 2px;
      margin-bottom: 6px;
    }
    .auth-subtitle {
      color: var(--text-muted);
      font-size: 0.85rem;
      margin-bottom: 36px;
      font-family: var(--font-body);
    }
    .error-list {
      background: rgba(231,76,60,0.08);
      border: 1px solid var(--danger);
      border-radius: 4px;
      padding: 12px 16px;
      margin-bottom: 20px;
      list-style: none;
    }
    .error-list li {
      color: var(--danger);
      font-size: 0.825rem;
      padding: 2px 0;
    }
    .auth-footer-link {
      margin-top: 28px;
      text-align: center;
      font-size: 0.85rem;
      color: var(--text-muted);
    }
    .auth-footer-link a { color: var(--accent); text-decoration: none; }
    .auth-footer-link a:hover { text-decoration: underline; }
    .demo-accounts {
      margin-top: 32px;
      padding: 16px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 4px;
    }
    .demo-label {
      font-family: var(--font-mono);
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--text-muted);
      margin-bottom: 10px;
    }
    .demo-row {
      display: flex;
      justify-content: space-between;
      font-size: 0.75rem;
      font-family: var(--font-mono);
      padding: 4px 0;
      border-bottom: 1px solid var(--border);
      color: var(--text-secondary);
    }
    .demo-row:last-child { border-bottom: none; }
    .demo-row .demo-role {
      font-size: 0.65rem;
      padding: 1px 6px;
      border-radius: 2px;
      color: #fff;
    }
    .demo-role.superadmin { background: #9b59b6; }
    .demo-role.admin { background: var(--danger); }
    .demo-role.manager { background: var(--info); }
    .demo-role.buyer { background: var(--success); }
  </style>
</head>
<body>
<div class="auth-wrap">
  <!-- Left panel -->
  <div class="auth-left">
    <div class="auth-left-logo">АВТО<span>ЗАПЧАСТЬ</span></div>
    <div class="auth-left-tagline">Профессиональный склад автозапчастей</div>
    <ul class="auth-left-features">
      <li>Более 50 000 позиций в наличии</li>
      <li>Оригинальные и аналоговые запчасти</li>
      <li>Гарантия на всю продукцию</li>
      <li>Доставка по всей России</li>
      <li>Быстрый подбор по номеру детали</li>
    </ul>
  </div>

  <!-- Right panel (form) -->
  <div class="auth-right">
    <div class="auth-title">ВХОД</div>
    <div class="auth-subtitle">Введите данные вашего аккаунта</div>

    <?php if (!empty($errors)): ?>
      <ul class="error-list">
        <?php foreach ($errors as $err): ?>
          <li><?= sanitize($err) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-input"
          value="<?= sanitize($emailVal) ?>"
          placeholder="admin@avtozapchast.ru"
          required
          autofocus
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Пароль</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-input"
          placeholder="••••••••"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">
        ВОЙТИ
      </button>
    </form>

    <div class="auth-footer-link">
      Нет аккаунта? <a href="<?= APP_URL ?>/auth/register.php">Зарегистрироваться</a>
    </div>

    <!-- Demo accounts -->
    <div class="demo-accounts">
      <div class="demo-label">Демо-аккаунты (пароль: Password123!)</div>
      <div class="demo-row">
        <span>superadmin@avtozapchast.ru</span>
        <span class="demo-role superadmin">superadmin</span>
      </div>
      <div class="demo-row">
        <span>admin@avtozapchast.ru</span>
        <span class="demo-role admin">admin</span>
      </div>
      <div class="demo-row">
        <span>manager@avtozapchast.ru</span>
        <span class="demo-role manager">manager</span>
      </div>
      <div class="demo-row">
        <span>buyer@avtozapchast.ru</span>
        <span class="demo-role buyer">buyer</span>
      </div>
    </div>
  </div>
</div>
</body>
</html>
