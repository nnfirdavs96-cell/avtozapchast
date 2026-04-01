<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);

$db   = getDB();
$csrf = generateCsrfToken();

// Toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF error.'); redirect(APP_URL . '/admin/users.php');
    }
    $uid    = (int)($_POST['user_id'] ?? 0);
    $active = (int)($_POST['active'] ?? 0);
    $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'buyer'")->execute([$active, $uid]);
    flashMessage('success', 'Статус пользователя обновлён.');
    redirect(APP_URL . '/admin/users.php');
}

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where  = ["role IN ('buyer','manager')"];
$params = [];
if ($search) { $where[] = '(username LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM users $whereSQL");
$cntStmt->execute($params);
$total   = (int)$cntStmt->fetchColumn();
$pages   = max(1, ceil($total / $perPage));
$offset  = ($page - 1) * $perPage;

$usersStmt = $db->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
     FROM users u $whereSQL ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset"
);
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();

$pageTitle = 'Пользователи';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">ПОЛЬЗОВАТЕЛИ</div>

    <form method="get" class="flex gap-8 mb-16">
      <input type="text" name="search" class="form-input" style="max-width:300px;" placeholder="Поиск по имени или email..." value="<?= sanitize($search) ?>">
      <button type="submit" class="btn btn-outline btn-sm">Найти</button>
      <?php if ($search): ?><a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
      <span style="margin-left:auto;font-family:var(--font-mono);font-size:0.75rem;color:var(--text-muted);align-self:center;">Всего: <?= $total ?></span>
    </form>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Пользователь</th><th>Email</th><th>Телефон</th><th>Роль</th><th style="text-align:center;">Заказов</th><th>Зарег.</th><th>Статус</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><span class="mono"><?= $u['id'] ?></span></td>
            <td style="font-weight:500;font-size:0.875rem;"><?= sanitize($u['username']) ?></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($u['email']) ?></td>
            <td style="font-family:var(--font-mono);font-size:0.75rem;color:var(--text-muted);"><?= sanitize($u['phone'] ?? '—') ?></td>
            <td><span class="role-badge <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td style="text-align:center;font-family:var(--font-mono);"><?= $u['order_count'] ?></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td>
              <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $u['is_active'] ? 'Активен' : 'Заблокирован' ?>
              </span>
            </td>
            <td>
              <?php if ($u['role'] === 'buyer'): ?>
              <form method="post" action="" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="toggle_active" value="1">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                  <?= $u['is_active'] ? 'Блокировать' : 'Разблокировать' ?>
                </button>
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
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
