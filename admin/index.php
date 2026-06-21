<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);

$db = getDB();

$totalUsers  = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn();
$totalParts  = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1")->fetchColumn();
$pendingOrds = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

$recentOrders = $db->query(
    "SELECT o.*, u.username, u.email FROM orders o
     JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Администратор — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('dashboard'); ?>

  <!-- Main -->
  <div class="az-main">
    <div class="az-topbar">
      <h1>Панель администратора</h1>
      <span style="font-size:0.85rem;color:#666;">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?>
        <span style="background:#d32f2f;color:#fff;border-radius:4px;padding:2px 7px;font-size:0.72rem;margin-left:4px;">admin</span>
      </span>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="row mb-24">
        <div class="col-lg-3 col-md-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#e3f2fd;">
              <i class="fa fa-users" style="color:#1976d2;"></i>
            </div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $totalUsers ?></div>
              <div class="az-stat-card-label">Пользователей</div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#fff3e0;">
              <i class="fa fa-shopping-bag" style="color:#f57c00;"></i>
            </div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $totalOrders ?></div>
              <div class="az-stat-card-label">Заказов всего</div>
              <div class="az-stat-card-sub"><?= $pendingOrds ?> ожидает</div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#e8f5e9;">
              <i class="fa fa-money" style="color:#388e3c;"></i>
            </div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value" style="font-size:1.2rem;"><?= formatPrice($totalRevenue) ?></div>
              <div class="az-stat-card-label">Выручка</div>
              <div class="az-stat-card-sub">Без отменённых</div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#fce4ec;">
              <i class="fa fa-cogs" style="color:#c62828;"></i>
            </div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $totalParts ?></div>
              <div class="az-stat-card-label">Запчастей</div>
              <div class="az-stat-card-sub">Активных позиций</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent orders -->
      <div class="az-card">
        <div class="az-card-header">
          <h4 class="az-card-title">Последние заказы</h4>
          <a href="<?= APP_URL ?>/admin/orders.php" class="az-btn az-btn-outline az-btn-sm">Все заказы</a>
        </div>
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Покупатель</th>
                  <th>Email</th>
                  <th>Дата</th>
                  <th>Сумма</th>
                  <th>Статус</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                  <td><strong>#<?= (int)$order['id'] ?></strong></td>
                  <td><?= sanitize($order['username']) ?></td>
                  <td style="font-size:0.8rem;color:#666;"><?= sanitize($order['email']) ?></td>
                  <td style="font-size:0.8rem;color:#888;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                  <td><strong><?= formatPrice($order['total_amount']) ?></strong></td>
                  <td>
                    <span class="badge badge-<?= getOrderStatusClass($order['status']) ?>">
                      <?= getOrderStatusLabel($order['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="<?= APP_URL ?>/admin/orders.php?id=<?= (int)$order['id'] ?>" class="az-btn az-btn-outline az-btn-sm">Просмотр</a>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentOrders)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:24px;">Заказов ещё нет</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
