<?php
/**
 * Публичная страница магазина продавца: /seller_shop.php?slug=<slug>
 * Показывает название магазина и его опубликованные (active) товары.
 */
require_once __DIR__ . '/config/config.php';

$slug = trim($_GET['slug'] ?? '');
$db = getDB();

$seller = null;
if ($slug !== '') {
    $st = $db->prepare("SELECT * FROM sellers WHERE slug = ? AND status = 'approved' LIMIT 1");
    $st->execute([$slug]);
    $seller = $st->fetch();
}
if (!$seller) {
    flashMessage('danger', 'Магазин не найден.');
    redirect(APP_URL . '/catalog/index.php');
}

$sid  = (int)$seller['id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 12;
$off  = ($page - 1) * $per;

$cnt = $db->prepare("SELECT COUNT(*) FROM parts WHERE seller_id = ? AND is_active = 1 AND moderation_status = 'active'");
$cnt->execute([$sid]);
$total = (int)$cnt->fetchColumn();
$pages = max(1, ceil($total / $per));

$st = $db->prepare(
    "SELECT p.*, b.name AS brand_name
       FROM parts p LEFT JOIN brands b ON b.id = p.brand_id
      WHERE p.seller_id = ? AND p.is_active = 1 AND p.moderation_status = 'active'
   ORDER BY p.created_at DESC LIMIT $per OFFSET $off"
);
$st->execute([$sid]);
$parts = $st->fetchAll();

$pageTitle = sanitize($seller['shop_name']) . ' — ' . getSetting('site_name');
require_once __DIR__ . '/includes/header.php';
?>

<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>$seller['shop_name']]]) ?>

<div class="shop_area" style="padding:40px 0;">
  <div class="container">
    <div class="sl-shop-head">
      <div class="sl-shop-logo"><i class="fa fa-briefcase"></i></div>
      <div>
        <h1 class="sl-shop-name"><?= sanitize($seller['shop_name']) ?></h1>
        <p class="sl-shop-meta">Продавец на маркетплейсе · товаров: <?= $total ?></p>
        <?php if (!empty($seller['description'])): ?>
        <p class="sl-shop-desc"><?= sanitize($seller['description']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($parts)): ?>
    <div style="text-align:center;padding:50px;color:#999;">У этого продавца пока нет товаров в продаже.</div>
    <?php else: ?>
    <div class="row shop_wrapper">
      <?php foreach ($parts as $part):
        $stock = getStockStatus((int)$part['stock']);
        $img   = productImageUrl($part['images']);
      ?>
      <div class="col-lg-3 col-md-4 col-6 mb-4">
        <article class="single_product">
          <figure>
            <div class="product_thumb">
              <a class="primary_img" href="<?= partUrl($part) ?>">
                <img src="<?= $img ?>" alt="<?= sanitize($part['name']) ?>" style="height:200px;object-fit:contain;width:100%">
              </a>
              <?= productBadges($part) ?>
              <div class="quick_button"><a href="<?= partUrl($part) ?>"><i class="icon-eye"></i></a></div>
            </div>
            <div class="product_content grid_content">
              <div class="product_content_inner">
                <p class="manufacture_product"><a href="#"><?= sanitize($part['brand_name']) ?></a></p>
                <h4 class="product_name"><a href="<?= partUrl($part) ?>"><?= sanitize(truncate($part['name'],55)) ?></a></h4>
                <p style="font-size:0.75rem;color:#888;margin:2px 0"><?= sanitize($part['part_number']) ?></p>
                <?= priceBox($part) ?>
                <p class="stock-<?= $stock['class'] ?>" style="font-size:0.75rem"><?= $stock['label'] ?></p>
              </div>
              <div class="action_links">
                <ul>
                  <li class="add_to_cart"><a href="javascript:void(0)" onclick="addToCart(<?= (int)$part['id'] ?>)"><?= t('add_to_cart') ?></a></li>
                  <li class="wishlist"><a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$part['id'] ?>)"><i class="icon-heart"></i></a></li>
                </ul>
              </div>
            </div>
          </figure>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <?= paginationHtml(['pages'=>$pages,'current'=>$page], APP_URL.'/seller_shop.php?'.http_build_query(['slug'=>$slug])) ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
