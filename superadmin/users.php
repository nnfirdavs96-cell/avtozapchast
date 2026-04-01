<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db     = getDB();
$csrf   = generateCsrfToken();
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$errors = [];
$roles  = ['buyer','manager','admin','superadmin'];

// ── POST handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF error.'); redirect(APP_URL . '/superadmin/users.php');
    }
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId === (int)$_SESSION['user_id']) {
            flashMessage('danger', 'Нельзя удалить собственный аккаунт.');
        } else {
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
            $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$active, $uid]);
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

    if (mb_strlen($username) < 3)          $errors[] = 'Имя слишком короткое.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email.';
    if (!$uid && mb_strlen($password) < 8) $errors[] = 'Пароль должен быть ≥ 8 символов.';

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $uid]);
        if ($chk->fetch()) $errors[] = 'Email уже занят.';
    }

    if (empty($errors)) {
        if ($uid) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET username=?,email=?,phone=?,role=?,password_hash=?,updated_at=NOW() WHERE id=?")
                   ->execute([$username, $email, $phone ?: null, $role, $hash, $uid]);
            } else {
                $db->prepare("UPDATE users SET username=?,email=?,phone=?,role=?,updated_at=NOW() WHERE id=?")
                   ->execute([$username, $email, $phone ?: null, $role, $uid]);
            }
            flashMessage('success', 'Пользователь обновлён.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username,email,password_hash,role,phone) VALUES (?,?,?,?,?)")
               ->execute([$username, $email, $hash, $role, $phone ?: null]);
            flashMessage('success', 'Пользователь создан.');
        }
        redirect(APP_URL . '/superadmin/users.php');
    }
    $action = $uid ? 'edit' : 'new';
    $editId = $uid;
}

// Load for edit
$editUser = null;
if ($editId && $action === 'edit') {
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

$pageTitle = 'Управление пользователями';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading flex-between" style="font-size:1.5rem;">
      ВСЕ ПОЛЬЗОВАТЕЛИ
      <?php if ($action === 'list'): ?>
        <a href="?action=new" class="btn btn-primary btn-sm">+ Создать</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/superadmin/users.php" class="btn btn-outline btn-sm">← Список</a>
      <?php endif; ?>
    </div>

    <?php if ($action === 'new' || $action === 'edit'): ?>
    <div style="max-width:600px;">
      <div class="card">
        <div class="card-header"><h3><?= $action === 'edit' ? 'РЕДАКТИРОВАТЬ ПОЛЬЗОВАТЕЛЯ' : 'НОВЫЙ ПОЛЬЗОВАТЕЛЬ' ?></h3></div>
        <div class="card-body">
          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger mb-16">
            <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
            <?php if ($editUser): ?><input type="hidden" name="id" value="<?= $editUser['id'] ?>"><?php endif; ?>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Имя пользователя *</label>
                <input type="text" name="username" class="form-input" value="<?= sanitize($editUser['username'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Роль *</label>
                <select name="role" class="form-select">
                  <?php foreach ($roles as $r): ?>
                  <option value="<?= $r ?>" <?= ($editUser['role'] ?? 'buyer') === $r ? 'selected' : '' ?>><?= $r ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Email *</label>
              <input type="email" name="email" class="form-input" value="<?= sanitize($editUser['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Телефон</label>
              <input type="tel" name="phone" class="form-input" value="<?= sanitize($editUser['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Пароль <?= $editUser ? '(оставьте пустым чтобы не менять)' : '*' ?></label>
              <input type="password" name="password" class="form-input" placeholder="Мин. 8 символов" <?= !$editUser ? 'required' : '' ?>>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editUser ? 'СОХРАНИТЬ' : 'СОЗДАТЬ' ?></button>
          </form>
        </div>
      </div>
    </div>
    <?php else: ?>

    <form method="get" class="flex gap-8 mb-16">
      <input type="text" name="search" class="form-input" style="max-width:300px;" placeholder="Поиск..." value="<?= sanitize($search) ?>">
      <button type="submit" class="btn btn-outline btn-sm">Найти</button>
      <?php if ($search): ?><a href="<?= APP_URL ?>/superadmin/users.php" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
      <span style="margin-left:auto;font-family:var(--font-mono);font-size:0.75rem;color:var(--text-muted);align-self:center;">Всего: <?= $total ?></span>
    </form>

    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>#</th><th>Пользователь</th><th>Email</th><th>Роль</th><th>Заказов</th><th>Зарег.</th><th>Статус</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><span class="mono"><?= $u['id'] ?></span></td>
            <td style="font-weight:500;font-size:0.875rem;"><?= sanitize($u['username']) ?></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($u['email']) ?></td>
            <td><span class="role-badge <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td style="font-family:var(--font-mono);"><?= $u['order_count'] ?></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td><span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $u['is_active'] ? 'Активен' : 'Заблок.' ?></span></td>
            <td style="white-space:nowrap;">
              <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Ред.</a>
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                  <?= $u['is_active'] ? 'Блок.' : 'Разблок.' ?>
                </button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('Удалить пользователя?')">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= $pages; $p++): $q = array_merge($_GET, ['page' => $p]); ?>
      <a href="?<?= http_build_query($q) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
