<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['manager','admin','superadmin']);

$db = getDB();
$totalParts      = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active=1")->fetchColumn();
$totalCategories = (int)$db->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetchColumn();
$totalBrands     = (int)$db->query("SELECT COUNT(*) FROM brands WHERE is_active=1")->fetchColumn();
$pendingReviews  = (int)$db->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();
$lowStock        = $db->query("SELECT id, part_number, name, stock FROM parts WHERE is_active=1 AND stock<=5 ORDER BY stock LIMIT 8")->fetchAll();

$pageTitle = 'Менеджер';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Панель менеджера <span class="dash-heading-badge">manager</span></h1>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Запчасти</div><div class="stat-value"><?= $totalParts ?></div><div class="stat-sub">активных позиций</div></div>
      <div class="stat-card"><div class="stat-label">Категории</div><div class="stat-value"><?= $totalCategories ?></div></div>
      <div class="stat-card"><div class="stat-label">Бренды</div><div class="stat-value"><?= $totalBrands ?></div></div>
      <div class="stat-card"><div class="stat-label">Отзывы на модерации</div><div class="stat-value" style="color:#fcb700"><?= $pendingReviews ?></div></div>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:16px">⚠ Заканчивается на складе</h3>
      <?php if (empty($lowStock)): ?>
        <p style="color:#888">Все запчасти в достаточном количестве.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Артикул</th><th>Название</th><th>Остаток</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($lowStock as $p): ?>
                <tr>
                  <td><span class="mono"><?= sanitize($p['part_number']) ?></span></td>
                  <td><?= sanitize($p['name']) ?></td>
                  <td><span class="badge badge-<?= $p['stock']==0?'danger':'warning' ?>"><?= (int)$p['stock'] ?> шт</span></td>
                  <td><a href="<?= APP_URL ?>/manager/parts.php?edit=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">Редактировать</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
