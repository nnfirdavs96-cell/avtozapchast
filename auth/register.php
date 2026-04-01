<?php
require_once dirname(__DIR__) . '/config/config.php';

if (isLoggedIn()) redirect(APP_URL . '/buyer/index.php');

$errors = [];
$vals   = ['username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $username        = trim($_POST['username'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        $vals = compact('username', 'email', 'phone');

        // Validation
        if (mb_strlen($username) < 3 || mb_strlen($username) > 80) {
            $errors[] = 'Имя пользователя должно быть от 3 до 80 символов.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Пароль должен содержать минимум 8 символов.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Пароли не совпадают.';
        }

        if (empty($errors)) {
            $db = getDB();
            // Check email uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'Этот email уже зарегистрирован.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins  = $db->prepare(
                    "INSERT INTO users (username, email, password_hash, role, phone) VALUES (?, ?, ?, 'buyer', ?)"
                );
                $ins->execute([$username, $email, $hash, $phone ?: null]);

                flashMessage('success', 'Регистрация прошла успешно! Войдите в систему.');
                redirect(APP_URL . '/auth/login.php');
            }
        }
    }
}

$pageTitle = 'Регистрация';
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
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg-primary); padding: 40px 16px; }
    .auth-box {
      width: 100%;
      max-width: 520px;
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 40px 48px;
    }
    @media (max-width: 600px) { .auth-box { padding: 28px 24px; } }
    .auth-box-logo {
      font-family: var(--font-display);
      font-size: 1.8rem;
      letter-spacing: 2px;
      color: var(--text-primary);
      margin-bottom: 4px;
      text-decoration: none;
      display: block;
    }
    .auth-box-logo span { color: var(--accent); }
    .auth-title {
      font-family: var(--font-display);
      font-size: 2rem;
      color: var(--text-primary);
      letter-spacing: 2px;
      margin-top: 24px;
      margin-bottom: 4px;
    }
    .auth-subtitle { color: var(--text-muted); font-size: 0.82rem; margin-bottom: 28px; }
    .error-list {
      background: rgba(231,76,60,0.08);
      border: 1px solid var(--danger);
      border-radius: 4px;
      padding: 10px 14px;
      margin-bottom: 18px;
      list-style: none;
    }
    .error-list li { color: var(--danger); font-size: 0.82rem; padding: 2px 0; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }
    .auth-footer-link { margin-top: 24px; text-align: center; font-size: 0.85rem; color: var(--text-muted); }
    .auth-footer-link a { color: var(--accent); text-decoration: none; }
    .auth-footer-link a:hover { text-decoration: underline; }
    .role-note {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 10px 14px;
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 16px;
      font-family: var(--font-mono);
    }
  </style>
</head>
<body>
<div class="auth-box">
  <a href="<?= APP_URL ?>/index.php" class="auth-box-logo">АВТО<span>ЗАПЧАСТЬ</span></a>
  <div class="auth-title">РЕГИСТРАЦИЯ</div>
  <div class="auth-subtitle">Создайте покупательский аккаунт</div>

  <?php if (!empty($errors)): ?>
    <ul class="error-list">
      <?php foreach ($errors as $err): ?><li><?= sanitize($err) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="username">Имя пользователя *</label>
        <input type="text" id="username" name="username" class="form-input"
               value="<?= sanitize($vals['username']) ?>" placeholder="ivanov_auto" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="phone">Телефон</label>
        <input type="tel" id="phone" name="phone" class="form-input"
               value="<?= sanitize($vals['phone']) ?>" placeholder="+7 (___) ___-__-__">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="email">Email *</label>
      <input type="email" id="email" name="email" class="form-input"
             value="<?= sanitize($vals['email']) ?>" placeholder="your@email.ru" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="password">Пароль *</label>
        <input type="password" id="password" name="password" class="form-input"
               placeholder="Мин. 8 символов" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="password_confirm">Повтор пароля *</label>
        <input type="password" id="password_confirm" name="password_confirm" class="form-input"
               placeholder="••••••••" required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">
      СОЗДАТЬ АККАУНТ
    </button>
  </form>

  <div class="role-note">// Регистрация доступна только для покупателей. Роли менеджера и администратора назначаются супер-администратором.</div>

  <div class="auth-footer-link">
    Уже есть аккаунт? <a href="<?= APP_URL ?>/auth/login.php">Войти</a>
  </div>
</div>
</body>
</html>
