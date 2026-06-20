<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

ensurePhoneAuthSchema();
ensureStaffPinSchema();

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];
$roles  = ['buyer', 'manager', 'admin', 'superadmin'];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/users.php');
    }
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId === (int)$_SESSION['user_id']) {
            flashMessage('danger', 'Нельзя удалить собственный аккаунт.');
        } else {
            // Delete related data first
            $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$delId]);
            $db->prepare("DELETE FROM wishlist WHERE user_id = ?")->execute([$delId]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
            flashMessage('success', 'Пользователь удалён.');
        }
        redirect(APP_URL . '/superadmin/users.php');
    }

    if ($postAction === 'toggle') {
        $uid    = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['active'] ?? 0);
        if ($uid === (int)$_SESSION['user_id']) {
            flashMessage('danger', 'Нельзя деактивировать собственный аккаунт.');
        } else {
            $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$active, $uid]);
            flashMessage('success', 'Статус обновлён.');
        }
        redirect(APP_URL . '/superadmin/users.php');
    }

    // Create or Edit
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $phoneE164 = normalizePhone($phone);
    $role      = in_array($_POST['role'] ?? '', $roles) ? $_POST['role'] : 'buyer';
    $password  = $_POST['password'] ?? '';
    $pin       = trim($_POST['pin'] ?? '');
    $clearPin  = isset($_POST['clear_pin']);
    $uid       = (int)($_POST['id'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if (mb_strlen($username) < 3) $errors[] = 'Имя слишком короткое (мин. 3 символа).';
    // Email optional — но если указан, должен быть корректным
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email.';
    // Телефон опционален — но если указан, должен нормализоваться
    if ($phone !== '' && $phoneE164 === '') $errors[] = 'Некорректный номер телефона.';
    // PIN опционален — если указан, 4–6 цифр
    if ($pin !== '' && !preg_match('/^\d{4,6}$/', $pin)) $errors[] = 'PIN-код должен состоять из 4–6 цифр.';
    if ($password !== '' && mb_strlen($password) < 8) $errors[] = 'Пароль должен быть минимум 8 символов.';

    // На создании: нужен способ входа (пароль или PIN) и идентификатор (email или телефон)
    if (!$uid) {
        if ($password === '' && $pin === '') $errors[] = 'Задайте пароль или PIN-код для входа.';
        if ($email === '' && $phoneE164 === '') $errors[] = 'Укажите email или номер телефона.';
    }

    // Уникальность email
    if (empty($errors) && $email !== '') {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $uid]);
        if ($chk->fetch()) $errors[] = 'Email уже зарегистрирован.';
    }
    // Уникальность телефона (по нормализованному ключу)
    if (empty($errors) && $phoneE164 !== '') {
        $chk = $db->prepare("SELECT id FROM users WHERE phone_e164 = ? AND id != ?");
        $chk->execute([$phoneE164, $uid]);
        if ($chk->fetch()) $errors[] = 'Этот номер телефона уже используется.';
    }

    if (empty($errors)) {
        if ($uid) {
            $sql    = "UPDATE users SET username=?, email=?, phone=?, phone_e164=?, role=?, is_active=?";
            $params = [$username, $email ?: null, $phone ?: null, $phoneE164 ?: null, $role, $isActive];
            if ($password !== '') { $sql .= ", password_hash=?"; $params[] = password_hash($password, PASSWORD_DEFAULT); }
            if ($clearPin)        { $sql .= ", pin_hash=NULL"; }
            elseif ($pin !== '')  { $sql .= ", pin_hash=?"; $params[] = password_hash($pin, PASSWORD_DEFAULT); }
            $sql .= ", updated_at=NOW() WHERE id=?";
            $params[] = $uid;
            $db->prepare($sql)->execute($params);
            flashMessage('success', 'Пользователь обновлён.');
        } else {
            $passHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
            $pinHash  = $pin !== ''      ? password_hash($pin, PASSWORD_DEFAULT)      : null;
            $db->prepare("INSERT INTO users (username, email, password_hash, pin_hash, role, phone, phone_e164, is_active)
                          VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$username, $email ?: null, $passHash, $pinHash, $role, $phone ?: null, $phoneE164 ?: null, $isActive]);
            flashMessage('success', 'Пользователь создан.');
        }
        redirect(APP_URL . '/superadmin/users.php');
    }
    $action = $uid ? 'edit' : 'new';
    $editId = $uid;
}

// Load for edit
$editUser = null;
if ($editId && in_array($action, ['edit'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}

// List
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where   = [];
$params  = [];
if ($search) { $where[] = '(username LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cntStmt = $db->prepare("SELECT COUNT(*) FROM users $whereSQL");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$usersStmt = $db->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS order_count
     FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset"
);
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();

$pageTitle = 'Все пользователи — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('users'); ?>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Все пользователи</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Form: create / edit -->
      <?php if (in_array($action, ['new', 'edit'])): ?>
      <div class="az-card mb-24" style="max-width:640px;">
        <div class="az-card-header">
          <h4 class="az-card-title"><?= $action === 'edit' ? 'Редактировать пользователя' : 'Новый пользователь' ?></h4>
          <a href="<?= APP_URL ?>/superadmin/users.php" class="az-btn az-btn-outline az-btn-sm">← Список</a>
        </div>
        <div class="az-card-body">
          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger mb-16">
            <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
            <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>
            <div class="row">
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Имя пользователя *</label>
                  <input type="text" name="username" class="form-control" value="<?= sanitize($editUser['username'] ?? $_POST['username'] ?? '') ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Роль *</label>
                  <select name="role" class="form-control">
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= ($editUser['role'] ?? $_POST['role'] ?? 'buyer') === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Email <span style="color:#aaa;font-weight:400;">(необязательно)</span></label>
                  <input type="email" name="email" class="form-control" value="<?= sanitize($editUser['email'] ?? $_POST['email'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Телефон <span style="color:#aaa;font-weight:400;">(для входа по номеру)</span></label>
                  <input type="tel" name="phone" data-phone="tj" placeholder="+992 (__) ___-__-__" class="form-control" value="<?= sanitize($editUser['phone'] ?? $_POST['phone'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Пароль <?= $editUser ? '(пусто — не менять)' : '(мин. 8 символов)' ?></label>
                  <input type="password" name="password" class="form-control" placeholder="Минимум 8 символов" autocomplete="new-password">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>PIN-код сотрудника <span style="color:#aaa;font-weight:400;">(4–6 цифр)</span></label>
                  <input type="text" name="pin" inputmode="numeric" maxlength="6" class="form-control"
                         placeholder="<?= !empty($editUser['pin_hash']) ? 'PIN задан — пусто, чтобы не менять' : 'напр. 1234' ?>"
                         autocomplete="off">
                  <?php if ($editUser && !empty($editUser['pin_hash'])): ?>
                  <div class="form-check mt-8">
                    <input type="checkbox" name="clear_pin" id="clear_pin" class="form-check-input" value="1">
                    <label for="clear_pin" class="form-check-label" style="font-size:0.85rem;">Удалить PIN (запретить вход по PIN)</label>
                  </div>
                  <?php endif; ?>
                  <small style="color:#888;display:block;margin-top:4px;">Сотрудник входит по номеру телефона + PIN. Для этого задайте телефон и PIN.</small>
                </div>
              </div>
              <?php if ($editUser): ?>
              <div class="col-12">
                <div class="az-form-group">
                  <div class="form-check">
                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
                           <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="is_active" class="form-check-label">Активен</label>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <button type="submit" class="az-btn az-btn-primary"><?= $editUser ? 'Сохранить изменения' : 'Создать пользователя' ?></button>
            <?php if ($editUser && in_array($editUser['role'] ?? '', ['admin','manager'], true)): ?>
            <a href="<?= APP_URL ?>/superadmin/permissions.php?user_id=<?= (int)$editUser['id'] ?>"
               class="az-btn az-btn-outline" style="margin-left:8px;border-color:#9b59b6;color:#6a1b9a;">
              <i class="fa fa-shield"></i> Права доступа
            </a>
            <?php endif; ?>
          </form>
        </div>
      </div>
      <?php else: ?>

      <!-- List header -->
      <div class="az-card mb-16">
        <div class="az-card-body">
          <form method="get" class="d-flex align-items-center gap-8 flex-wrap">
            <input type="text" name="search" class="form-control" style="max-width:280px;" placeholder="Поиск..." value="<?= sanitize($search) ?>">
            <button type="submit" class="az-btn az-btn-outline">Найти</button>
            <?php if ($search): ?><a href="<?= APP_URL ?>/superadmin/users.php" class="az-btn az-btn-outline">Сбросить</a><?php endif; ?>
            <span style="margin-left:auto;color:#888;font-size:0.85rem;">Всего: <?= $total ?></span>
            <a href="?action=new" class="az-btn az-btn-primary">+ Создать</a>
          </form>
        </div>
      </div>

      <div class="az-card">
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr><th>#</th><th>Пользователь</th><th>Email</th><th>Роль</th><th>Заказов</th><th>Зарег.</th><th>Статус</th><th>Действия</th></tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int)$u['id'] ?></td>
                  <td><strong><?= sanitize($u['username']) ?></strong>
                      <?php if (!empty($u['pin_hash'])): ?><span class="badge badge-info" style="font-size:0.62rem;vertical-align:middle;">PIN</span><?php endif; ?>
                      <br><small class="text-muted"><?= sanitize($u['phone'] ?? '') ?></small></td>
                  <td style="font-size:0.8rem;color:#666;"><?= sanitize($u['email']) ?></td>
                  <td><span class="badge badge-secondary" style="font-size:0.7rem;"><?= sanitize($u['role']) ?></span></td>
                  <td style="text-align:center;"><?= (int)$u['order_count'] ?></td>
                  <td style="font-size:0.8rem;color:#888;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                  <td><span class="badge badge-<?= $u['is_active'] ? 'success' : 'danger' ?>"><?= $u['is_active'] ? 'Активен' : 'Заблок.' ?></span></td>
                  <td style="white-space:nowrap;">
                    <a href="?action=edit&id=<?= (int)$u['id'] ?>" class="az-btn az-btn-outline az-btn-sm">Ред.</a>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                      <button type="submit" class="az-btn az-btn-sm <?= $u['is_active'] ? 'az-btn-danger' : 'az-btn-success' ?>">
                        <?= $u['is_active'] ? 'Блок.' : 'Разблок.' ?>
                      </button>
                    </form>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Удалить пользователя «<?= sanitize($u['username']) ?>»? Это действие необратимо.')">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button type="submit" class="az-btn az-btn-danger az-btn-sm">Удалить</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.75rem;color:#aaa;">текущий</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" style="text-align:center;color:#999;padding:24px;">Пользователи не найдены</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($pages > 1): ?>
        <div class="az-card-footer">
          <div class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++): $q = array_merge($_GET, ['page' => $p]); ?>
            <a href="?<?= http_build_query($q) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
