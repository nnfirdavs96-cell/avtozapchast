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

// System info
$phpVersion   = phpversion();
$mysqlVersion = 'N/A';
try {
    $mysqlVersion = $db->query("SELECT VERSION()")->fetchColumn();
} catch (Exception $e) {}
$diskTotal = disk_total_space('/');
$diskFree  = disk_free_space('/');
$diskUsed  = $diskTotal - $diskFree;

$recentUsers = $db->query(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
     FROM users u ORDER BY u.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Суперадминистратор — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';

function formatBytes($bytes, $precision = 1): string {
    $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
?>

<div class="az-panel">
  <!-- Superadmin Sidebar -->
  <aside class="az-sidebar" style="background:#1a0533;">
    <div class="az-sidebar-brand" style="background:rgba(155,89,182,0.3);border-bottom-color:rgba(155,89,182,0.3);">
      <span style="color:#ce93d8;">&#x2605;</span> Суперадмин
    </div>
    <nav class="az-sidebar-nav">
      <a href="<?= APP_URL ?>/superadmin/index.php" class="az-sidebar-link active" style="color:#ce93d8;">
        <i class="fa fa-star"></i> Панель
      </a>
      <a href="<?= APP_URL ?>/superadmin/users.php" class="az-sidebar-link">
        <i class="fa fa-users"></i> Пользователи
      </a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link">
        <i class="fa fa-shopping-bag"></i> Заказы
      </a>
      <a href="<?= APP_URL ?>/admin/products.php" class="az-sidebar-link">
        <i class="fa fa-cogs"></i> Товары
      </a>
      <a href="<?= APP_URL ?>/admin/sliders.php" class="az-sidebar-link">
        <i class="fa fa-picture-o"></i> Слайдер
      </a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link">
        <i class="fa fa-cog"></i> Настройки
      </a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link">
        <i class="fa fa-money"></i> Валюты
      </a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link">
        <i class="fa fa-language"></i> Языки
      </a>
      <a href="<?= APP_URL ?>/superadmin/warehouse.php" class="az-sidebar-link">
        <i class="fa fa-database"></i> Склад API
      </a>
      <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-sidebar-link">
        <i class="fa fa-newspaper-o"></i> Блог
      </a>
      <a href="<?= APP_URL ?>/superadmin/backup.php" class="az-sidebar-link">
        <i class="fa fa-archive"></i> Бэкапы
      </a>
      <hr style="border-color:rgba(255,255,255,0.1);margin:12px 0;">
      <a href="<?= APP_URL ?>/index.php" class="az-sidebar-link">
        <i class="fa fa-home"></i> На сайт
      </a>
      <a href="<?= APP_URL ?>/auth/logout.php" class="az-sidebar-link" style="color:rgba(255,100,100,0.85)!important;">
        <i class="fa fa-sign-out"></i> Выйти
      </a>
    </nav>
  </aside>

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

      <!-- System info + Recent users -->
      <div class="row">
        <!-- System info -->
        <div class="col-lg-4 mb-24">
          <div class="az-card h-100">
            <div class="az-card-header">
              <h4 class="az-card-title">Система</h4>
            </div>
            <div class="az-card-body">
              <table class="table table-sm table-borderless" style="font-size:0.85rem;">
                <tr>
                  <td class="text-muted">PHP</td>
                  <td><strong><?= sanitize($phpVersion) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">MySQL</td>
                  <td><strong><?= sanitize($mysqlVersion) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Диск всего</td>
                  <td><strong><?= formatBytes($diskTotal) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Использовано</td>
                  <td><strong><?= formatBytes($diskUsed) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Свободно</td>
                  <td><strong><?= formatBytes($diskFree) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Загрузка</td>
                  <td>
                    <?php $diskPct = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100) : 0; ?>
                    <div style="background:#eee;border-radius:4px;height:6px;width:100%;overflow:hidden;">
                      <div style="background:<?= $diskPct > 80 ? '#c62828' : '#388e3c' ?>;height:100%;width:<?= $diskPct ?>%;"></div>
                    </div>
                    <small class="text-muted"><?= $diskPct ?>%</small>
                  </td>
                </tr>
                <tr>
                  <td class="text-muted">APP_URL</td>
                  <td><code><?= sanitize(APP_URL ?: '(relative)') ?></code></td>
                </tr>
                <tr>
                  <td class="text-muted">Сайт</td>
                  <td><strong><?= sanitize(getSetting('site_name')) ?></strong></td>
                </tr>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent users -->
        <div class="col-lg-8 mb-24">
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
