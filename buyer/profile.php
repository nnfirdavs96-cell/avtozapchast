<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('buyer');

$user   = getCurrentUser();
$db     = getDB();
$csrf   = generateCsrfToken();
$errors = [];

// ── POST: update profile ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $username    = trim($_POST['username'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confPass    = $_POST['confirm_new_password'] ?? '';

        // Basic validation
        if (mb_strlen($username) < 2) {
            $errors[] = 'Имя пользователя слишком короткое (минимум 2 символа).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }

        // Email uniqueness (exclude current user)
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$email, $user['id']]);
            if ($chk->fetch()) {
                $errors[] = 'Этот email уже занят другим пользователем.';
            }
        }

        // Password change requested?
        $changePassword = !empty($newPass);
        if ($changePassword) {
            // Verify current password
            if (empty($currentPass)) {
                $errors[] = 'Введите текущий пароль для подтверждения.';
            } else {
                $pwStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
                $pwStmt->execute([$user['id']]);
                $pwRow = $pwStmt->fetch();
                if (!$pwRow || !password_verify($currentPass, $pwRow['password_hash'])) {
                    $errors[] = 'Текущий пароль введён неверно.';
                }
            }
            if (mb_strlen($newPass) < 6) {
                $errors[] = 'Новый пароль должен содержать не менее 6 символов.';
            }
            if ($newPass !== $confPass) {
                $errors[] = 'Новые пароли не совпадают.';
            }
        }

        if (empty($errors)) {
            if ($changePassword) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $db->prepare(
                    "UPDATE users SET username=?, email=?, phone=?, password_hash=?, updated_at=NOW() WHERE id=?"
                )->execute([$username, $email, $phone ?: null, $hash, $user['id']]);
            } else {
                $db->prepare(
                    "UPDATE users SET username=?, email=?, phone=?, updated_at=NOW() WHERE id=?"
                )->execute([$username, $email, $phone ?: null, $user['id']]);
            }

            // Force session user_data refresh
            unset($_SESSION['user_data']);
            $_SESSION['username'] = $username;

            flashMessage('success', 'Профиль успешно обновлён.');
            redirect(APP_URL . '/buyer/profile.php');
        }
    }
}

// Reload fresh user data from DB
$userStmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$user['id']]);
$userData = $userStmt->fetch() ?: $user;

$pageTitle = 'Мой профиль';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav>
            <ul>
                <li><a href="<?= APP_URL ?>/buyer/index.php"><i class="fa fa-dashboard"></i> <?= t('dashboard') ?></a></li>
                <li><a href="<?= APP_URL ?>/buyer/orders.php"><i class="fa fa-list-alt"></i> Мои заказы</a></li>
                <li><a href="<?= APP_URL ?>/buyer/profile.php" class="active"><i class="fa fa-user-o"></i> Профиль</a></li>
                <li><a href="<?= APP_URL ?>/buyer/cart.php"><i class="fa fa-shopping-cart"></i> <?= t('shopping_cart') ?></a></li>
                <li><a href="<?= APP_URL ?>/buyer/wishlist.php"><i class="fa fa-heart-o"></i> <?= t('wishlist') ?></a></li>
                <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:20px;">
                    <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;">
                        <i class="fa fa-sign-out"></i> <?= t('logout') ?>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- ── Main ───────────────────────────────────────────────────── -->
    <main class="az-main">
        <div class="az-topbar">
            <h1>Мой профиль</h1>
            <a href="<?= APP_URL ?>/index.php" style="font-size:0.85rem;color:#d32f2f;text-decoration:none;">
                <i class="fa fa-arrow-left"></i> В магазин
            </a>
        </div>

        <div class="az-content">

            <?php if (!empty($errors)): ?>
                <div class="az-alert az-alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <div><?= sanitize($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="max-width:640px;">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

                    <!-- Basic info -->
                    <div class="az-card">
                        <h3>Основные данные</h3>

                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:12px;background:#f8f9fa;border-radius:8px;">
                            <div style="width:48px;height:48px;background:#d32f2f;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;font-weight:900;">
                                <?= strtoupper(mb_substr($userData['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:700;"><?= sanitize($userData['username']) ?></div>
                                <div style="font-size:0.8rem;color:#888;">
                                    Роль: <span style="background:#d32f2f;color:#fff;border-radius:4px;padding:1px 7px;font-size:0.72rem;"><?= sanitize($userData['role']) ?></span>
                                    &nbsp;·&nbsp;
                                    Регистрация: <?= sanitize(date('d.m.Y', strtotime($userData['created_at']))) ?>
                                </div>
                            </div>
                        </div>

                        <div class="az-form-group">
                            <label>Имя пользователя</label>
                            <input type="text" name="username"
                                   value="<?= sanitize($userData['username']) ?>"
                                   required minlength="2" maxlength="50">
                        </div>
                        <div class="az-form-group">
                            <label>Email</label>
                            <input type="email" name="email"
                                   value="<?= sanitize($userData['email']) ?>"
                                   required>
                        </div>
                        <div class="az-form-group">
                            <label>Телефон</label>
                            <input type="tel" name="phone"
                                   value="<?= sanitize($userData['phone'] ?? '') ?>"
                                   placeholder="+7 (___) ___-__-__">
                        </div>
                    </div>

                    <!-- Password change -->
                    <div class="az-card">
                        <h3>Изменить пароль</h3>
                        <p style="font-size:0.85rem;color:#888;margin-bottom:16px;">
                            Оставьте поля пустыми, если не хотите менять пароль.
                        </p>

                        <div class="az-form-group">
                            <label>Текущий пароль</label>
                            <input type="password" name="current_password"
                                   placeholder="Введите текущий пароль"
                                   autocomplete="current-password">
                        </div>
                        <div class="az-form-group">
                            <label>Новый пароль</label>
                            <input type="password" name="new_password"
                                   placeholder="Минимум 6 символов"
                                   autocomplete="new-password">
                        </div>
                        <div class="az-form-group">
                            <label>Подтверждение нового пароля</label>
                            <input type="password" name="confirm_new_password"
                                   placeholder="Повторите новый пароль"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="az-btn az-btn-primary">
                            <i class="fa fa-save"></i> Сохранить изменения
                        </button>
                        <a href="<?= APP_URL ?>/buyer/index.php" class="az-btn az-btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
