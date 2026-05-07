<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '')==='toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='buyer'")->execute([$id]);
    }
    redirect(APP_URL . '/admin/users.php');
}

$users = $db->query("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS orders_cnt,
                                  (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=u.id) AS total_spent
                     FROM users u WHERE role='buyer' ORDER BY u.created_at DESC")->fetchAll();

$pageTitle = 'Покупатели';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Покупатели</h1>
    <div class="admin-card">
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>ID</th><th>Логин</th><th>E-mail</th><th>Телефон</th><th>Заказов</th><th>Потрачено</th><th>Регистрация</th><th>Активен</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>#<?= (int)$u['id'] ?></td>
              <td><strong><?= sanitize($u['username']) ?></strong></td>
              <td><?= sanitize($u['email']) ?></td>
              <td><?= sanitize($u['phone'] ?? '—') ?></td>
              <td><?= (int)$u['orders_cnt'] ?></td>
              <td><strong><?= money($u['total_spent']) ?></strong></td>
              <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
              <td><span class="badge badge-<?= $u['is_active']?'success':'danger' ?>"><?= $u['is_active']?'Да':'Нет' ?></span></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-outline btn-sm"><?= $u['is_active']?'🔒':'🔓' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
