<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'manager', 'superadmin']);
requirePermission('users');

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/admin/users.php');
    }

    $postAction = $_POST['action'] ?? '';

    // Toggle active/inactive
    if ($postAction === 'toggle') {
        $uid    = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['active'] ?? 0);
        // Admin can only toggle buyer/manager; superadmin can toggle all
        $allowed = hasRole('superadmin') ? [] : ['buyer', 'manager'];
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row && (empty($allowed) || in_array($row['role'], $allowed))) {
            $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$active, $uid]);
            flashMessage('success', 'Статус пользователя обновлён.');
        } else {
            flashMessage('danger', 'Недостаточно прав для изменения этого пользователя.');
        }
        redirect(APP_URL . '/admin/users.php');
    }

    // Change role
    if ($postAction === 'role') {
        $uid  = (int)($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? '';
        $adminRoles  = ['buyer', 'manager'];
        $superRoles  = ['buyer', 'manager', 'admin', 'superadmin'];
        $allowedRoles = hasRole('superadmin') ? $superRoles : $adminRoles;
        if (in_array($role, $allowedRoles)) {
            $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?")->execute([$role, $uid]);
            flashMessage('success', 'Роль пользователя изменена.');
        } else {
            flashMessage('danger', 'Недостаточно прав для назначения этой роли.');
        }
        redirect(APP_URL . '/admin/users.php');
    }

    // Add new user
    if ($postAction === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleNew  = $_POST['role'] ?? 'buyer';
        $errors   = [];

        $allowedRoles = hasRole('superadmin') ? ['buyer','manager','admin','superadmin'] : ['buyer','manager'];
        if (!in_array($roleNew, $allowedRoles)) $roleNew = 'buyer';

        if (mb_strlen($username) < 3) $errors[] = 'Имя слишком короткое.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email.';
        if (mb_strlen($password) < 8) $errors[] = 'Пароль должен быть минимум 8 символов.';

        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) $errors[] = 'Email уже зарегистрирован.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, email, password_hash, role, phone, is_active) VALUES (?, ?, ?, ?, ?, 1)")
               ->execute([$username, $email, $hash, $roleNew, $phone ?: null]);
            flashMessage('success', 'Пользователь создан.');
            redirect(APP_URL . '/admin/users.php');
        }

        // Fall through to show form with errors
        $formErrors = $errors;
        $formData   = $_POST;
    }
}

// ── List users ────────────────────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Admin sees buyer/manager only; superadmin sees all
$baseWhere = hasRole('superadmin') ? [] : ["u.role IN ('buyer','manager')"];
$params = [];
if ($search) {
    $baseWhere[] = '(u.username LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSQL = $baseWhere ? 'WHERE ' . implode(' AND ', $baseWhere) : '';

$cntStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereSQL");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$usersStmt = $db->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
     FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset"
);
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();

$showAddForm = isset($_GET['action']) && $_GET['action'] === 'add';
$formErrors  = $formErrors ?? [];
$formData    = $formData ?? [];

$pageTitle = 'Пользователи — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('users'); ?>

  <!-- Main -->
  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Управление пользователями</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Add user form -->
      <?php if ($showAddForm || !empty($formErrors)): ?>
      <div class="az-card mb-24" style="max-width:640px;">
        <div class="az-card-header">
          <h4 class="az-card-title">Добавить пользователя</h4>
          <a href="<?= APP_URL ?>/admin/users.php" class="az-btn az-btn-outline az-btn-sm">← Отмена</a>
        </div>
        <div class="az-card-body">
          <?php if (!empty($formErrors)): ?>
          <div class="alert alert-danger mb-16">
            <?php foreach ($formErrors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div class="row">
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Имя пользователя *</label>
                  <input type="text" name="username" class="form-control" value="<?= sanitize($formData['username'] ?? '') ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Роль *</label>
                  <select name="role" class="form-control">
                    <option value="buyer" <?= ($formData['role'] ?? '') === 'buyer' ? 'selected' : '' ?>>Покупатель</option>
                    <option value="manager" <?= ($formData['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Менеджер</option>
                    <?php if (hasRole('superadmin')): ?>
                    <option value="admin" <?= ($formData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Администратор</option>
                    <option value="superadmin" <?= ($formData['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Суперадмин</option>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Email *</label>
                  <input type="email" name="email" class="form-control" value="<?= sanitize($formData['email'] ?? '') ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Телефон</label>
                  <input type="tel" name="phone" class="form-control" value="<?= sanitize($formData['phone'] ?? '') ?>">
                </div>
              </div>
              <div class="col-12">
                <div class="az-form-group">
                  <label>Пароль * (мин. 8 символов)</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
              </div>
            </div>
            <button type="submit" class="az-btn az-btn-primary">Создать пользователя</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Search and actions bar -->
      <div class="az-card mb-16">
        <div class="az-card-body">
          <form method="get" action="" class="d-flex align-items-center gap-8 flex-wrap">
            <input type="text" name="search" class="form-control" style="max-width:280px;"
                   placeholder="Поиск по имени или email..." value="<?= sanitize($search) ?>">
            <button type="submit" class="az-btn az-btn-outline">Найти</button>
            <?php if ($search): ?>
            <a href="<?= APP_URL ?>/admin/users.php" class="az-btn az-btn-outline">Сбросить</a>
            <?php endif; ?>
            <span style="margin-left:auto;color:#888;font-size:0.85rem;">Всего: <?= $total ?></span>
            <a href="?action=add" class="az-btn az-btn-primary">+ Добавить</a>
          </form>
        </div>
      </div>

      <!-- Users table -->
      <div class="az-card">
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Пользователь</th>
                  <th>Email</th>
                  <th>Телефон</th>
                  <th>Роль</th>
                  <th>Заказов</th>
                  <th>Зарег.</th>
                  <th>Статус</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int)$u['id'] ?></td>
                  <td><strong><?= sanitize($u['username']) ?></strong></td>
                  <td style="font-size:0.8rem;color:#666;"><?= sanitize($u['email']) ?></td>
                  <td style="font-size:0.8rem;color:#888;"><?= sanitize($u['phone'] ?? '—') ?></td>
                  <td>
                    <!-- Role change form -->
                    <form method="post" action="" style="display:inline-flex;align-items:center;gap:4px;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="role">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <select name="role" class="form-control form-control-sm" style="width:auto;padding:3px 6px;font-size:0.78rem;"
                              onchange="this.form.submit()"
                              <?= ($u['id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>>
                        <option value="buyer"      <?= $u['role'] === 'buyer'      ? 'selected' : '' ?>>buyer</option>
                        <option value="manager"    <?= $u['role'] === 'manager'    ? 'selected' : '' ?>>manager</option>
                        <?php if (hasRole('superadmin')): ?>
                        <option value="admin"      <?= $u['role'] === 'admin'      ? 'selected' : '' ?>>admin</option>
                        <option value="superadmin" <?= $u['role'] === 'superadmin' ? 'selected' : '' ?>>superadmin</option>
                        <?php endif; ?>
                      </select>
                    </form>
                  </td>
                  <td style="text-align:center;"><?= (int)$u['order_count'] ?></td>
                  <td style="font-size:0.8rem;color:#888;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <span class="badge badge-<?= $u['is_active'] ? 'success' : 'danger' ?>">
                      <?= $u['is_active'] ? 'Активен' : 'Заблокирован' ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <form method="post" action="" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                      <button type="submit" class="az-btn az-btn-sm <?= $u['is_active'] ? 'az-btn-danger' : 'az-btn-success' ?>">
                        <?= $u['is_active'] ? 'Блокировать' : 'Разблокировать' ?>
                      </button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.75rem;color:#aaa;">текущий</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="9" style="text-align:center;color:#999;padding:24px;">Пользователи не найдены</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($pages > 1): ?>
        <div class="az-card-footer">
          <div class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++):
              $q = array_merge($_GET, ['page' => $p]); ?>
            <a href="?<?= http_build_query($q) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
