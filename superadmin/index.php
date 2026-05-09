<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db = getDB();

$stats = [
    'users'    => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active'   => (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'buyers'   => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'")->fetchColumn(),
    'managers' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn(),
    'admins'   => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'orders'   => (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'revenue'  => (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn(),
    'parts'    => (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1")->fetchColumn(),
];

$allUsers = $db->query(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
     FROM users u ORDER BY u.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Суперадминистратор';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">
      СУПЕРАДМИНИСТРАТОР
      <span class="dash-heading-badge" style="background:#9b59b6;border-color:#9b59b6;">superadmin</span>
    </div>

    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
      <div class="stat-card">
        <div class="stat-label">Всего пользователей</div>
        <div class="stat-value"><?= $stats['users'] ?></div>
        <div class="stat-sub"><?= $stats['active'] ?> активных</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Роли</div>
        <div class="stat-value" style="font-size:1rem;line-height:1.6;">
          <span class="role-badge buyer"><?= $stats['buyers'] ?> buyers</span><br>
          <span class="role-badge manager"><?= $stats['managers'] ?> managers</span><br>
          <span class="role-badge admin"><?= $stats['admins'] ?> admins</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Заказов / Товаров</div>
        <div class="stat-value"><?= $stats['orders'] ?></div>
        <div class="stat-sub"><?= $stats['parts'] ?> позиций в каталоге</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Выручка</div>
        <div class="stat-value" style="font-size:1.4rem;"><?= formatPrice($stats['revenue']) ?></div>
        <div class="stat-sub">Без отменённых</div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="grid-3 mb-24">
      <a href="<?= APP_URL ?>/superadmin/users.php?action=new" class="card" style="padding:20px;text-decoration:none;border-color:#9b59b644;">
        <div class="label-mono mb-8" style="color:#9b59b6;">// Пользователи</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">+ СОЗДАТЬ АККАУНТ</div>
      </a>
      <a href="<?= APP_URL ?>/superadmin/users.php" class="card" style="padding:20px;text-decoration:none;border-color:#9b59b644;">
        <div class="label-mono mb-8" style="color:#9b59b6;">// Управление</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">ВСЕ ПОЛЬЗОВАТЕЛИ</div>
      </a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="card" style="padding:20px;text-decoration:none;border-color:#9b59b644;">
        <div class="label-mono mb-8" style="color:#9b59b6;">// Система</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">НАСТРОЙКИ САЙТА</div>
      </a>
    </div>

    <!-- Recent users -->
    <div class="card">
      <div class="card-header">
        <h3>ПОСЛЕДНИЕ ПОЛЬЗОВАТЕЛИ</h3>
        <a href="<?= APP_URL ?>/superadmin/users.php" class="btn btn-outline btn-sm">Все</a>
      </div>
      <div class="table-wrap" style="border:none;border-radius:0;">
        <table class="data-table">
          <thead><tr><th>#</th><th>Имя</th><th>Email</th><th>Роль</th><th>Заказов</th><th>Зарег.</th><th>Статус</th></tr></thead>
          <tbody>
            <?php foreach ($allUsers as $u): ?>
            <tr>
              <td><span class="mono"><?= $u['id'] ?></span></td>
              <td style="font-weight:500;"><?= sanitize($u['username']) ?></td>
              <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($u['email']) ?></td>
              <td><span class="role-badge <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
              <td style="font-family:var(--font-mono);"><?= $u['order_count'] ?></td>
              <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
              <td><span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $u['is_active'] ? 'Активен' : 'Заблок.' ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
