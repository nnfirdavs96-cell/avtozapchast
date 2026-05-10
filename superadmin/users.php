<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

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
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = in_array($_POST['role'] ?? '', $roles) ? $_POST['role'] : 'buyer';
    $password = $_POST['password'] ?? '';
    $uid      = (int)($_POST['id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (mb_strlen($username) < 3)          $errors[] = 'Имя слишком короткое (мин. 3 символа).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email.';
    if (!$uid && mb_strlen($password) < 8) $errors[] = 'Пароль должен быть минимум 8 символов.';
    if ($uid && $password && mb_strlen($password) < 8) $errors[] = 'Новый пароль должен быть минимум 8 символов.';

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $uid]);
        if ($chk->fetch()) $errors[] = 'Email уже зарегистрирован.';
    }

    if (empty($errors)) {
        if ($uid) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, is_active=?, password_hash=?, updated_at=NOW() WHERE id=?")
                   ->execute([$username, $email, $phone ?: null, $role, $isActive, $hash, $uid]);
            } else {
                $db->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, is_active=?, updated_at=NOW() WHERE id=?")
                   ->execute([$username, $email, $phone ?: null, $role, $isActive, $uid]);
            }
            flashMessage('success', 'Пользователь обновлён.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, email, password_hash, role, phone, is_active) VALUES (?,?,?,?,?,?)")
               ->execute([$username, $email, $hash, $role, $phone ?: null, $isActive]);
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
  <aside class="az-sidebar" style="background:#1a0533;">
    <div class="az-sidebar-brand" style="background:rgba(155,89,182,0.3);border-bottom-color:rgba(155,89,182,0.3);">
      <span style="color:#ce93d8;">&#x2605;</span> Суперадмин
    </div>
    <nav class="az-sidebar-nav">
      <a href="<?= APP_URL ?>/superadmin/index.php" class="az-sidebar-link"><i class="fa fa-star"></i> Панель</a>
      <a href="<?= APP_URL ?>/admin/users.php" class="az-sidebar-link"><i class="fa fa-users"></i> Пользователи</a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link"><i class="fa fa-shopping-bag"></i> Заказы</a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link"><i class="fa fa-cog"></i> Настройки</a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link"><i class="fa fa-money"></i> Валюты</a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link"><i class="fa fa-language"></i> Языки</a>
      <a href="<?= APP_URL ?>/superadmin/warehouse.php" class="az-sidebar-link"><i class="fa fa-database"></i> Склад API</a>
      <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-sidebar-link"><i class="fa fa-newspaper-o"></i> Блог</a>
      <a href="<?= APP_URL ?>/superadmin/backup.php" class="az-sidebar-link"><i class="fa fa-archive"></i> Бэкапы</a>
      <hr style="border-color:rgba(255,255,255,0.1);margin:12px 0;">
      <a href="<?= APP_URL ?>/index.php" class="az-sidebar-link"><i class="fa fa-home"></i> На сайт</a>
    </nav>
  </aside>

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
                  <label>Email *</label>
                  <input type="email" name="email" class="form-control" value="<?= sanitize($editUser['email'] ?? $_POST['email'] ?? '') ?>" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Телефон</label>
                  <input type="tel" name="phone" class="form-control" value="<?= sanitize($editUser['phone'] ?? $_POST['phone'] ?? '') ?>">
                </div>
              </div>
              <div class="col-12">
                <div class="az-form-group">
                  <label>Пароль <?= $editUser ? '(оставьте пустым чтобы не менять)' : '* (мин. 8 символов)' ?></label>
                  <input type="password" name="password" class="form-control" placeholder="Минимум 8 символов" <?= !$editUser ? 'required' : '' ?>>
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
                  <td><strong><?= sanitize($u['username']) ?></strong><br><small class="text-muted"><?= sanitize($u['phone'] ?? '') ?></small></td>
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
