<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('buyer');

$user   = getCurrentUser();
$db     = getDB();
$csrf   = generateCsrfToken();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $newPass  = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (mb_strlen($username) < 3) $errors[] = 'Имя пользователя слишком короткое.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Введите корректный email.';

        // Email uniqueness (exclude current user)
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $user['id']]);
            if ($chk->fetch()) $errors[] = 'Этот email уже занят.';
        }

        if (!empty($newPass)) {
            if (mb_strlen($newPass) < 8) $errors[] = 'Пароль должен быть не менее 8 символов.';
            if ($newPass !== $confPass)   $errors[] = 'Пароли не совпадают.';
        }

        if (empty($errors)) {
            if (!empty($newPass)) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET username=?, email=?, phone=?, password_hash=?, updated_at=NOW() WHERE id=?")
                   ->execute([$username, $email, $phone ?: null, $hash, $user['id']]);
            } else {
                $db->prepare("UPDATE users SET username=?, email=?, phone=?, updated_at=NOW() WHERE id=?")
                   ->execute([$username, $email, $phone ?: null, $user['id']]);
            }
            unset($_SESSION['user_data']); // force reload
            flashMessage('success', 'Профиль успешно обновлён.');
            redirect(APP_URL . '/buyer/profile.php');
        }
    }
}

// Reload user
$userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$user['id']]);
$userData = $userStmt->fetch();

$pageTitle = 'Мой профиль';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">МОЙ ПРОФИЛЬ</div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-16">
      <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="max-width:600px;">
      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <div class="card mb-24">
          <div class="card-header"><h3>ОСНОВНЫЕ ДАННЫЕ</h3></div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Роль</label>
              <div style="display:flex;align-items:center;gap:8px;padding:10px 0;">
                <span class="role-badge <?= $userData['role'] ?>"><?= sanitize($userData['role']) ?></span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--text-muted);">
                  Зарегистрирован <?= date('d.m.Y', strtotime($userData['created_at'])) ?>
                </span>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="username">Имя пользователя</label>
              <input type="text" id="username" name="username" class="form-input"
                     value="<?= sanitize($userData['username']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-input"
                     value="<?= sanitize($userData['email']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="phone">Телефон</label>
              <input type="tel" id="phone" name="phone" class="form-input"
                     value="<?= sanitize($userData['phone'] ?? '') ?>" placeholder="+7 (___) ___-__-__">
            </div>
          </div>
        </div>

        <div class="card mb-24">
          <div class="card-header"><h3>ИЗМЕНИТЬ ПАРОЛЬ</h3></div>
          <div class="card-body">
            <p class="form-help mb-16">Оставьте поля пустыми, если не хотите менять пароль.</p>
            <div class="form-group">
              <label class="form-label" for="new_password">Новый пароль</label>
              <input type="password" id="new_password" name="new_password" class="form-input"
                     placeholder="Мин. 8 символов">
            </div>
            <div class="form-group">
              <label class="form-label" for="confirm_password">Подтверждение пароля</label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                     placeholder="••••••••">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">СОХРАНИТЬ ИЗМЕНЕНИЯ</button>
        <a href="<?= APP_URL ?>/buyer/index.php" class="btn btn-outline" style="margin-left:8px;">Отмена</a>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
