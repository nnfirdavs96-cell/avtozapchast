<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/product_card.php';
requireRole(['buyer','manager','admin','superadmin']);

$pageTitle = t('wishlist');
$db = getDB();
$stmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name FROM wishlist w
     JOIN parts p ON p.id=w.part_id
     LEFT JOIN brands b ON b.id=p.brand_id
     WHERE w.user_id=? AND p.is_active=1
     ORDER BY w.added_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<div class="page-head"><div class="container">
  <h1><?= t('wishlist') ?></h1>
  <nav class="breadcrumb">
    <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
    <span class="current"><?= t('wishlist') ?></span>
  </nav>
</div></div>

<section class="section">
  <div class="container">
    <?php if (empty($items)): ?>
      <div class="empty-state">
        <div class="icon"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></div>
        <h3>Список избранного пуст</h3>
        <p>Сохраняйте понравившиеся товары — они появятся здесь.</p>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary"><?= t('continue_shopping') ?></a>
      </div>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($items as $p) renderProductCard($p); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
