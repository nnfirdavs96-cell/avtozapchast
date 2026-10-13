<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db = getDB();

$stats = [
    'users'      => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'orders'     => (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'revenue'    => (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn(),
    'parts'      => (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1")->fetchColumn(),
    'categories' => (int)$db->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn(),
    'brands'     => (int)$db->query("SELECT COUNT(*) FROM brands WHERE is_active = 1")->fetchColumn(),
];

$recentUsers = $db->query(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
     FROM users u ORDER BY u.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Суперадминистратор — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <!-- Superadmin Sidebar -->
  <?php renderRoleSidebar('dashboard'); ?>

  <!-- Main -->
  <div class="az-main">
    <div class="az-topbar">
      <h1>Суперадминистратор</h1>
      <span style="font-size:0.85rem;color:#666;">
        <?= sanitize($_SESSION['username'] ?? 'Superadmin') ?>
        <span style="background:#7b1fa2;color:#fff;border-radius:4px;padding:2px 7px;font-size:0.72rem;margin-left:4px;">superadmin</span>
      </span>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="row mb-24">
        <div class="col-lg-2 col-md-4 col-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#ede7f6;"><i class="fa fa-users" style="color:#6a1b9a;"></i></div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $stats['users'] ?></div>
              <div class="az-stat-card-label">Пользователей</div>
            </div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#fff3e0;"><i class="fa fa-shopping-bag" style="color:#f57c00;"></i></div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $stats['orders'] ?></div>
              <div class="az-stat-card-label">Заказов</div>
            </div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#e8f5e9;"><i class="fa fa-ruble" style="color:#2e7d32;"></i></div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value" style="font-size:1rem;"><?= formatPrice($stats['revenue']) ?></div>
              <div class="az-stat-card-label">Выручка</div>
            </div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#fce4ec;"><i class="fa fa-cogs" style="color:#c62828;"></i></div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $stats['parts'] ?></div>
              <div class="az-stat-card-label">Запчастей</div>
            </div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#e3f2fd;"><i class="fa fa-sitemap" style="color:#1565c0;"></i></div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $stats['categories'] ?></div>
              <div class="az-stat-card-label">Категорий</div>
            </div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-16">
          <div class="az-stat-card">
            <div class="az-stat-card-icon" style="background:#f3e5f5;"><i class="fa fa-tag" style="color:#6a1b9a;"></i></div>
            <div class="az-stat-card-body">
              <div class="az-stat-card-value"><?= $stats['brands'] ?></div>
              <div class="az-stat-card-label">Брендов</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Финансовая часть — сводная статистика по всем продавцам -->
      <?php require dirname(__DIR__) . '/includes/admin_finance_block.php'; ?>

      <!-- Recent users (во всю ширину — карточка «Система» вынесена в /admin/system.php) -->
      <div class="row">
        <div class="col-12 mb-24">
          <div class="az-card">
            <div class="az-card-header">
              <h4 class="az-card-title">Последние пользователи</h4>
              <a href="<?= APP_URL ?>/superadmin/users.php" class="az-btn az-btn-outline az-btn-sm">Все</a>
            </div>
            <div class="az-card-body p-0">
              <div class="table-responsive">
                <table class="az-table">
                  <thead>
                    <tr><th>#</th><th>Имя</th><th>Email</th><th>Роль</th><th>Заказов</th><th>Зарег.</th><th>Статус</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentUsers as $u): ?>
                    <tr>
                      <td><?= (int)$u['id'] ?></td>
                      <td><?= sanitize($u['username']) ?></td>
                      <td style="font-size:0.8rem;color:#888;"><?= sanitize($u['email']) ?></td>
                      <td><span class="badge badge-secondary" style="font-size:0.7rem;"><?= sanitize($u['role']) ?></span></td>
                      <td style="text-align:center;"><?= (int)$u['order_count'] ?></td>
                      <td style="font-size:0.8rem;color:#888;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                      <td><span class="badge badge-<?= $u['is_active'] ? 'success' : 'danger' ?>"><?= $u['is_active'] ? 'Активен' : 'Заблок.' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
