<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['superadmin']);

$db = getDB();
$counts = [
    'users'   => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'parts'   => (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active=1")->fetchColumn(),
    'orders'  => (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'revenue' => (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status<>'cancelled'")->fetchColumn(),
    'reviews' => (int)$db->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'cars'    => (int)$db->query("SELECT COUNT(*) FROM car_models")->fetchColumn(),
];

$pageTitle = 'Суперадмин';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Суперадмин <span class="dash-heading-badge">root</span></h1>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Пользователи</div><div class="stat-value"><?= $counts['users'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Запчасти</div><div class="stat-value"><?= $counts['parts'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Заказы</div><div class="stat-value"><?= $counts['orders'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Выручка</div><div class="stat-value" style="font-size:1.6rem"><?= money($counts['revenue']) ?></div></div>
      <div class="stat-card"><div class="stat-label">Отзывы</div><div class="stat-value"><?= $counts['reviews'] ?></div></div>
      <div class="stat-card"><div class="stat-label">Модели авто</div><div class="stat-value"><?= $counts['cars'] ?></div></div>
    </div>
    <div class="admin-card">
      <h3 style="margin-bottom:14px">Системная информация</h3>
      <table class="admin-table">
        <tr><th>PHP</th><td><?= phpversion() ?></td></tr>
        <tr><th>База</th><td><?= sanitize(DB_NAME) ?> @ <?= sanitize(DB_HOST) ?></td></tr>
        <tr><th>Активный язык</th><td><?= sanitize(currentLanguage()) ?></td></tr>
        <tr><th>Активная валюта</th><td><?= sanitize(currentCurrency()['code']) ?> (<?= sanitize(currentCurrency()['symbol']) ?>)</td></tr>
        <tr><th>Загрузки</th><td><code><?= sanitize(UPLOAD_PATH) ?></code></td></tr>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
