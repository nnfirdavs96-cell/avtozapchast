<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);

$db = getDB();

$totalUsers  = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrds = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$revenue     = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn();

$recentOrders = $db->query(
    "SELECT o.*, u.username, u.email FROM orders o
     JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Панель администратора';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">
      АДМИНИСТРАТОР
      <span class="dash-heading-badge">admin</span>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Пользователей</div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-sub">Активных аккаунтов</div>
        <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Всего заказов</div>
        <div class="stat-value"><?= $totalOrders ?></div>
        <div class="stat-sub">За всё время</div>
        <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/></svg></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Новых заказов</div>
        <div class="stat-value"><?= $pendingOrds ?></div>
        <div class="stat-sub">Требуют обработки</div>
        <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Выручка</div>
        <div class="stat-value" style="font-size:1.5rem;"><?= formatPrice($revenue) ?></div>
        <div class="stat-sub">Без учёта отменённых</div>
        <div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>ПОСЛЕДНИЕ ЗАКАЗЫ</h3>
        <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">Все заказы</a>
      </div>
      <div class="table-wrap" style="border:none;border-radius:0;">
        <table class="data-table">
          <thead>
            <tr><th>#</th><th>Покупатель</th><th>Дата</th><th>Сумма</th><th>Статус</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $order): ?>
            <tr>
              <td><span class="mono">#<?= $order['id'] ?></span></td>
              <td>
                <div style="font-size:0.875rem;"><?= sanitize($order['username']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?= sanitize($order['email']) ?></div>
              </td>
              <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
              <td style="font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($order['total_amount']) ?></td>
              <td><span class="badge badge-<?= getOrderStatusClass($order['status']) ?>"><?= getOrderStatusLabel($order['status']) ?></span></td>
              <td><a href="<?= APP_URL ?>/admin/orders.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">Просмотр</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
