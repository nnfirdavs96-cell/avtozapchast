<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'superadmin']);

$db = getDB();

$totalParts = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1")->fetchColumn();
$lowStock   = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1 AND stock <= 5 AND stock > 0")->fetchColumn();
$outStock   = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1 AND stock = 0")->fetchColumn();
$totalCats  = (int)$db->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();
$totalBrands= (int)$db->query("SELECT COUNT(*) FROM brands WHERE is_active = 1")->fetchColumn();

$recentParts = $db->query(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name
     FROM parts p LEFT JOIN brands b ON b.id = p.brand_id LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 ORDER BY p.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Панель менеджера';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">
      МЕНЕДЖЕР
      <span class="dash-heading-badge">manager</span>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Всего позиций</div>
        <div class="stat-value"><?= $totalParts ?></div>
        <div class="stat-sub">Активных товаров</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Заканчивается</div>
        <div class="stat-value" style="color:var(--warning);"><?= $lowStock ?></div>
        <div class="stat-sub">Остаток ≤ 5 шт</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Нет в наличии</div>
        <div class="stat-value" style="color:var(--danger);"><?= $outStock ?></div>
        <div class="stat-sub">Требуют пополнения</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Категорий / Брендов</div>
        <div class="stat-value"><?= $totalCats ?> / <?= $totalBrands ?></div>
        <div class="stat-sub">Активных</div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="grid-3 mb-24">
      <a href="<?= APP_URL ?>/manager/parts.php?action=new" class="card" style="padding:20px;text-decoration:none;">
        <div class="label-mono mb-8">// Товары</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">+ ДОБАВИТЬ ТОВАР</div>
      </a>
      <a href="<?= APP_URL ?>/manager/categories.php" class="card" style="padding:20px;text-decoration:none;">
        <div class="label-mono mb-8">// Категории</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">КАТЕГОРИИ</div>
      </a>
      <a href="<?= APP_URL ?>/manager/brands.php" class="card" style="padding:20px;text-decoration:none;">
        <div class="label-mono mb-8">// Бренды</div>
        <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:1px;">БРЕНДЫ</div>
      </a>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>ПОСЛЕДНИЕ ДОБАВЛЕННЫЕ</h3>
        <a href="<?= APP_URL ?>/manager/parts.php" class="btn btn-outline btn-sm">Все товары</a>
      </div>
      <div class="table-wrap" style="border:none;border-radius:0;">
        <table class="data-table">
          <thead>
            <tr><th>Артикул</th><th>Название</th><th>Бренд</th><th>Категория</th><th style="text-align:right;">Цена</th><th style="text-align:center;">Остаток</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentParts as $p):
              $st = getStockStatus((int)$p['stock']);
            ?>
            <tr>
              <td><span class="mono"><?= sanitize($p['part_number']) ?></span></td>
              <td style="font-size:0.875rem;"><?= sanitize(truncate($p['name'], 45)) ?></td>
              <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($p['brand_name']) ?></td>
              <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($p['category_name']) ?></td>
              <td style="text-align:right;font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($p['price']) ?></td>
              <td style="text-align:center;"><span class="badge badge-<?= $st['class'] ?>"><?= $p['stock'] ?> шт</span></td>
              <td>
                <a href="<?= APP_URL ?>/manager/parts.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Ред.</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
