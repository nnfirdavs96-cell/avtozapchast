<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['admin','superadmin']);

$db = getDB();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status<>'cancelled'")->fetchColumn();
$totalUsers   = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$unreadMsgs   = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
$pendingRev   = (int)$db->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

$recent = $db->query("SELECT o.*, u.username, u.email FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();

$pageTitle = 'Админ-панель';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Админ-панель <span class="dash-heading-badge">admin</span></h1>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Всего заказов</div><div class="stat-value"><?= $totalOrders ?></div></div>
      <div class="stat-card"><div class="stat-label">Новые заказы</div><div class="stat-value" style="color:#fcb700"><?= $pendingOrders ?></div></div>
      <div class="stat-card"><div class="stat-label">Выручка</div><div class="stat-value" style="font-size:1.6rem"><?= money($totalRevenue) ?></div></div>
      <div class="stat-card"><div class="stat-label">Пользователи</div><div class="stat-value"><?= $totalUsers ?></div></div>
      <div class="stat-card"><div class="stat-label">Сообщения</div><div class="stat-value" style="color:#3498db"><?= $unreadMsgs ?></div></div>
      <div class="stat-card"><div class="stat-label">Отзывы на модерации</div><div class="stat-value" style="color:#fcb700"><?= $pendingRev ?></div></div>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:14px">Последние заказы</h3>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>№</th><th>Покупатель</th><th>Сумма</th><th>Статус</th><th>Оплата</th><th>Дата</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recent as $o): ?>
            <tr>
              <td><strong>#<?= (int)$o['id'] ?></strong></td>
              <td><?= sanitize($o['username']) ?> <span style="color:#888;font-size:0.78rem"><?= sanitize($o['email']) ?></span></td>
              <td><strong><?= money($o['total_amount']) ?></strong></td>
              <td><span class="badge badge-<?= getOrderStatusClass($o['status']) ?>"><?= sanitize(getOrderStatusLabel($o['status'])) ?></span></td>
              <td><span class="badge badge-<?= $o['payment_status']==='paid'?'success':'warning' ?>"><?= $o['payment_status']==='paid'?'оплачен':'ждёт' ?></span></td>
              <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
              <td><a href="<?= APP_URL ?>/admin/orders.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">→</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
