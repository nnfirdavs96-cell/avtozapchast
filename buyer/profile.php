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
        $avatarPath  = trim($_POST['avatar_path'] ?? '') ?: null;
        // Accept only app-relative paths or http(s) URLs (blocks javascript:/data: schemes)
        if ($avatarPath !== null && !preg_match('~^(/|https?://)~i', $avatarPath)) {
            $avatarPath = null;
        }
        $firstName   = trim($_POST['first_name'] ?? '') ?: null;
        $lastName    = trim($_POST['last_name'] ?? '') ?: null;
        $address     = trim($_POST['address'] ?? '') ?: null;
        $city        = trim($_POST['city'] ?? '') ?: null;
        $zipCode     = trim($_POST['zip_code'] ?? '') ?: null;
        $country     = trim($_POST['country'] ?? '') ?: null;
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confPass    = $_POST['confirm_new_password'] ?? '';

        // Basic validation
        if (mb_strlen($username) < 2) {
            $errors[] = 'Имя пользователя слишком короткое (минимум 2 символа).';
        }
        // Email is optional (phone-registered users may not have one);
        // validate only when provided.
        $email = $email ?: null;
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }

        // Email uniqueness (exclude current user)
        if (empty($errors) && $email !== null) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$email, $user['id']]);
            if ($chk->fetch()) {
                $errors[] = 'Этот email уже занят другим пользователем.';
            }
        }

        // Does this account already have a password set?
        $pwStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $pwStmt->execute([$user['id']]);
        $existingHash = $pwStmt->fetchColumn() ?: '';
        $hasPassword  = $existingHash !== '';

        // Password change / set requested?
        $changePassword = !empty($newPass);
        if ($changePassword) {
            // Users who already have a password must confirm the current one.
            // Phone-registered users without a password can set one directly.
            if ($hasPassword) {
                if (empty($currentPass)) {
                    $errors[] = 'Введите текущий пароль для подтверждения.';
                } elseif (!password_verify($currentPass, $existingHash)) {
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
            $hash    = $changePassword ? password_hash($newPass, PASSWORD_DEFAULT) : null;
            $phoneN  = $phone ?: null;
            $phoneE  = $phoneN ? (normalizePhone($phoneN) ?: null) : null;
            try {
                if ($changePassword) {
                    $db->prepare(
                        "UPDATE users SET username=?, email=?, phone=?, phone_e164=?, avatar_path=?, first_name=?, last_name=?, address=?, city=?, zip_code=?, country=?, password_hash=?, updated_at=NOW() WHERE id=?"
                    )->execute([$username, $email, $phoneN, $phoneE, $avatarPath, $firstName, $lastName, $address, $city, $zipCode, $country, $hash, $user['id']]);
                } else {
                    $db->prepare(
                        "UPDATE users SET username=?, email=?, phone=?, phone_e164=?, avatar_path=?, first_name=?, last_name=?, address=?, city=?, zip_code=?, country=?, updated_at=NOW() WHERE id=?"
                    )->execute([$username, $email, $phoneN, $phoneE, $avatarPath, $firstName, $lastName, $address, $city, $zipCode, $country, $user['id']]);
                }
            } catch (PDOException $e) {
                // New profile columns may not exist yet (migration not run) — save core fields only
                if ($changePassword) {
                    $db->prepare(
                        "UPDATE users SET username=?, email=?, phone=?, password_hash=?, updated_at=NOW() WHERE id=?"
                    )->execute([$username, $email, $phone ?: null, $hash, $user['id']]);
                } else {
                    $db->prepare(
                        "UPDATE users SET username=?, email=?, phone=?, updated_at=NOW() WHERE id=?"
                    )->execute([$username, $email, $phone ?: null, $user['id']]);
                }
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
$hasPwd   = !empty($userData['password_hash']);

$pageTitle = 'Мой профиль';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb([
    ['label' => t('home'), 'url' => APP_URL . '/index.php'],
    ['label' => 'Мой профиль'],
]) ?>

<div class="az-account">
    <div class="container">
        <?= renderBuyerAccountNav('profile') ?>
        <div class="az-account-body">

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

                        <?php $curAvatar = $userData['avatar_path'] ?? ''; ?>
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:14px;background:#f8f9fa;border-radius:8px;">
                            <div style="position:relative;">
                                <div id="avatarCircle"
                                     style="width:72px;height:72px;background:#C70909;border-radius:50%;display:<?= $curAvatar ? 'none' : 'flex' ?>;align-items:center;justify-content:center;color:#fff;font-size:1.9rem;font-weight:900;">
                                    <?= strtoupper(mb_substr($userData['username'], 0, 1)) ?>
                                </div>
                                <img id="avatarImg" src="<?= sanitize($curAvatar) ?>" alt=""
                                     style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.15);display:<?= $curAvatar ? 'block' : 'none' ?>;">
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:700;font-size:1.05rem;"><?= sanitize($userData['username']) ?></div>
                                <div style="font-size:0.8rem;color:#888;margin-bottom:8px;">
                                    Роль: <span style="background:#C70909;color:#fff;border-radius:4px;padding:1px 7px;font-size:0.72rem;"><?= sanitize($userData['role']) ?></span>
                                    &nbsp;·&nbsp;
                                    Регистрация: <?= sanitize(date('d.m.Y', strtotime($userData['created_at']))) ?>
                                </div>
                                <input type="hidden" name="avatar_path" id="avatarPath" value="<?= sanitize($curAvatar) ?>">
                                <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#fff;border:1px solid #ced4da;border-radius:6px;cursor:pointer;font-size:0.8rem;color:#555;">
                                    <i class="fa fa-camera"></i> Сменить фото
                                    <input type="file" id="avatarFile" accept="image/*" style="display:none;" onchange="uploadAvatar(this)">
                                </label>
                                <button type="button" id="avatarRemoveBtn" onclick="removeAvatar()"
                                        style="display:<?= $curAvatar ? 'inline-flex' : 'none' ?>;align-items:center;gap:5px;padding:6px 10px;background:#fff;border:1px solid #f0c0c0;color:#c0392b;border-radius:6px;cursor:pointer;font-size:0.8rem;margin-left:6px;">
                                    <i class="fa fa-trash-o"></i> Убрать
                                </button>
                                <span id="avatarStatus" style="font-size:0.78rem;color:#0a7;margin-left:8px;"></span>
                                <div style="font-size:0.72rem;color:#aaa;margin-top:6px;">
                                    Квадратное фото, рекомендуется 200&times;200&nbsp;px (JPG/PNG/WEBP, до&nbsp;5&nbsp;МБ)
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
                            <label>Email <span style="color:#aaa;font-weight:400;">(необязательно)</span></label>
                            <input type="email" name="email"
                                   value="<?= sanitize($userData['email'] ?? '') ?>"
                                   placeholder="your@email.com">
                        </div>
                        <div class="az-form-group">
                            <label>Телефон</label>
                            <input type="tel" name="phone" data-phone="tj"
                                   value="<?= sanitize($userData['phone'] ?? '') ?>"
                                   placeholder="+992 (__) ___-__-__">
                        </div>
                    </div>

                    <!-- Delivery address -->
                    <div class="az-card">
                        <h3>Адрес доставки</h3>
                        <p style="font-size:0.85rem;color:#888;margin-bottom:16px;">
                            Сохранённый адрес автоматически подставится при оформлении заказа.
                        </p>

                        <div style="display:flex;gap:12px;">
                            <div class="az-form-group" style="flex:1;">
                                <label>Имя</label>
                                <input type="text" name="first_name"
                                       value="<?= sanitize($userData['first_name'] ?? '') ?>"
                                       maxlength="80" placeholder="Иван">
                            </div>
                            <div class="az-form-group" style="flex:1;">
                                <label>Фамилия</label>
                                <input type="text" name="last_name"
                                       value="<?= sanitize($userData['last_name'] ?? '') ?>"
                                       maxlength="80" placeholder="Иванов">
                            </div>
                        </div>
                        <div class="az-form-group">
                            <label>Адрес (улица, дом, квартира)</label>
                            <input type="text" name="address"
                                   value="<?= sanitize($userData['address'] ?? '') ?>"
                                   maxlength="255" placeholder="ул. Автомобильная, д. 1, кв. 5">
                        </div>
                        <div style="display:flex;gap:12px;">
                            <div class="az-form-group" style="flex:2;">
                                <label>Город</label>
                                <input type="text" name="city"
                                       value="<?= sanitize($userData['city'] ?? '') ?>"
                                       maxlength="120" placeholder="Худжанд">
                            </div>
                            <div class="az-form-group" style="flex:1;">
                                <label>Индекс</label>
                                <input type="text" name="zip_code"
                                       value="<?= sanitize($userData['zip_code'] ?? '') ?>"
                                       maxlength="20" placeholder="735700">
                            </div>
                        </div>
                        <div class="az-form-group">
                            <label>Страна</label>
                            <input type="text" name="country"
                                   value="<?= sanitize($userData['country'] ?? '') ?>"
                                   maxlength="80" placeholder="Таджикистан">
                        </div>
                    </div>

                    <!-- Password change / set -->
                    <div class="az-card">
                        <h3><?= $hasPwd ? 'Изменить пароль' : 'Задать пароль' ?></h3>
                        <p style="font-size:0.85rem;color:#888;margin-bottom:16px;">
                            <?php if ($hasPwd): ?>
                                Оставьте поля пустыми, если не хотите менять пароль.
                            <?php else: ?>
                                Вы вошли по номеру телефона. Задайте пароль, чтобы можно было входить и по паролю (вход по SMS-коду продолжит работать).
                            <?php endif; ?>
                        </p>

                        <?php if ($hasPwd): ?>
                        <div class="az-form-group">
                            <label>Текущий пароль</label>
                            <input type="password" name="current_password"
                                   placeholder="Введите текущий пароль"
                                   autocomplete="current-password">
                        </div>
                        <?php endif; ?>
                        <div class="az-form-group">
                            <label><?= $hasPwd ? 'Новый пароль' : 'Пароль' ?></label>
                            <input type="password" name="new_password"
                                   placeholder="Минимум 6 символов"
                                   autocomplete="new-password">
                        </div>
                        <div class="az-form-group">
                            <label>Подтверждение пароля</label>
                            <input type="password" name="confirm_new_password"
                                   placeholder="Повторите пароль"
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

        </div><!-- /.az-account-body -->
    </div><!-- /.container -->
</div><!-- /.az-account -->

<script>
async function uploadAvatar(input) {
    const file = input.files[0];
    if (!file) return;
    const status = document.getElementById('avatarStatus');
    status.style.color = '#0a7';
    status.textContent = 'Загрузка...';
    const fd = new FormData();
    fd.append('file', file);
    try {
        const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=avatars', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.url) {
            document.getElementById('avatarPath').value = data.url;
            const img = document.getElementById('avatarImg');
            img.src = data.url;
            img.style.display = 'block';
            document.getElementById('avatarCircle').style.display = 'none';
            document.getElementById('avatarRemoveBtn').style.display = 'inline-flex';
            status.textContent = 'Загружено — не забудьте сохранить';
        } else {
            status.style.color = '#c30f0f';
            status.textContent = data.error || 'Ошибка загрузки';
        }
    } catch (e) {
        status.style.color = '#c30f0f';
        status.textContent = 'Ошибка сети: ' + e.message;
    }
    input.value = '';
}
function removeAvatar() {
    document.getElementById('avatarPath').value = '';
    document.getElementById('avatarImg').style.display = 'none';
    document.getElementById('avatarCircle').style.display = 'flex';
    document.getElementById('avatarRemoveBtn').style.display = 'none';
    document.getElementById('avatarStatus').textContent = 'Фото убрано — сохраните изменения';
    document.getElementById('avatarStatus').style.color = '#0a7';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
