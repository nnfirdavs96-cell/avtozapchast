<?php
require_once __DIR__ . '/../config/config.php';

$pageTitle = t('compare');
$db = getDB();

if (isLoggedIn()) {
    $stmt = $db->prepare(
        "SELECT p.*, b.name AS brand_name, c.name AS category_name FROM compare_list cl
         JOIN parts p ON p.id=cl.part_id
         LEFT JOIN brands b ON b.id=p.brand_id
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE cl.user_id=? AND p.is_active=1
         ORDER BY cl.added_at DESC LIMIT 4"
    );
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt = $db->prepare(
        "SELECT p.*, b.name AS brand_name, c.name AS category_name FROM compare_list cl
         JOIN parts p ON p.id=cl.part_id
         LEFT JOIN brands b ON b.id=p.brand_id
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE cl.session_id=? AND p.is_active=1
         ORDER BY cl.added_at DESC LIMIT 4"
    );
    $stmt->execute([session_id()]);
}
$items = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<div class="page-head"><div class="container">
  <h1><?= t('compare') ?></h1>
  <nav class="breadcrumb">
    <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
    <span class="current"><?= t('compare') ?></span>
  </nav>
</div></div>

<section class="section">
  <div class="container">
    <?php if (empty($items)): ?>
      <div class="empty-state">
        <div class="icon"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18M3 12h18M3 18h12"/></svg></div>
        <h3>Список сравнения пуст</h3>
        <p>Добавьте до 4 товаров, чтобы сравнить их характеристики.</p>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary"><?= t('continue_shopping') ?></a>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="compare-table">
          <thead>
            <tr>
              <th>Параметр</th>
              <?php foreach ($items as $p): ?>
                <th>
                  <img src="<?= sanitize(getPartImage((int)$p['id'])) ?>" alt="">
                  <div style="margin-top:8px"><a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$p['id'] ?>"><?= sanitize($p['name']) ?></a></div>
                  <button class="btn btn-link btn-sm" data-compare="<?= (int)$p['id'] ?>">— Убрать</button>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <tr><th><?= t('price') ?></th>
              <?php foreach ($items as $p): ?><td><strong style="color:var(--primary);font-size:1.15rem"><?= money($p['price']) ?></strong></td><?php endforeach; ?>
            </tr>
            <tr><th><?= t('sku') ?></th>
              <?php foreach ($items as $p): ?><td><?= sanitize($p['part_number']) ?></td><?php endforeach; ?>
            </tr>
            <tr><th><?= t('brand') ?></th>
              <?php foreach ($items as $p): ?><td><?= sanitize($p['brand_name']) ?></td><?php endforeach; ?>
            </tr>
            <tr><th><?= t('category') ?></th>
              <?php foreach ($items as $p): ?><td><?= sanitize($p['category_name']) ?></td><?php endforeach; ?>
            </tr>
            <tr><th><?= t('weight') ?></th>
              <?php foreach ($items as $p): ?><td><?= sanitize($p['weight'] ?? '—') ?> кг</td><?php endforeach; ?>
            </tr>
            <tr><th><?= t('dimensions') ?></th>
              <?php foreach ($items as $p): ?><td><?= sanitize($p['dimensions'] ?? '—') ?></td><?php endforeach; ?>
            </tr>
            <tr><th><?= t('in_stock') ?></th>
              <?php foreach ($items as $p): $s=getStockStatus((int)$p['stock']); ?>
                <td><span class="badge badge-<?= $s['class'] ?>"><?= sanitize($s['label']) ?></span> · <?= (int)$p['stock'] ?> шт</td>
              <?php endforeach; ?>
            </tr>
            <tr><th></th>
              <?php foreach ($items as $p): ?>
                <td><button class="btn btn-primary btn-block" data-add-cart="<?= (int)$p['id'] ?>"><?= t('add_to_cart') ?></button></td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
