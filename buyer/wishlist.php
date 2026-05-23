<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['buyer','admin','manager','superadmin']);
$pageTitle = t('wishlist') . ' — ' . getSetting('site_name');

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$stmt = $db->prepare(
    "SELECT w.part_id, p.name, p.price, p.images, p.part_number, p.stock, b.name AS brand_name
     FROM wishlist w
     JOIN parts p ON p.id = w.part_id
     LEFT JOIN brands b ON b.id = p.brand_id
     WHERE w.user_id = ?
     ORDER BY w.added_at DESC"
);
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb([
    ['label' => t('home'), 'url' => APP_URL . '/index.php'],
    ['label' => t('wishlist')],
]) ?>

<!--wishlist area start -->
<div class="wishlist_page_bg">
    <div class="container">
        <?= renderBuyerAccountNav('wishlist') ?>
        <div class="wishlist_area">
            <div class="wishlist_inner">
                <?php if (empty($items)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="table_desc wishlist">
                            <p style="text-align:center;padding:60px 20px;color:#888;"><?= t('cart_empty') ?></p>
                            <div style="text-align:center;padding-bottom:40px;">
                                <a href="<?= APP_URL ?>/catalog/index.php" class="button"><?= t('continue_shopping') ?></a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <form action="#">
                    <div class="row">
                        <div class="col-12">
                            <div class="table_desc wishlist">
                                <div class="cart_page">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th class="product_remove"><?= t('remove') ?></th>
                                                <th class="product_thumb"><?= t('image') ?></th>
                                                <th class="product_name"><?= t('product') ?></th>
                                                <th class="product-price"><?= t('price') ?></th>
                                                <th class="product_quantity"><?= t('stock_status') ?></th>
                                                <th class="product_total"><?= t('add_to_cart') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item):
                                                $stock = getStockStatus((int)$item['stock']);
                                                $img   = productImageUrl($item['images']);
                                            ?>
                                            <tr>
                                                <td class="product_remove">
                                                    <a href="<?= APP_URL ?>/api/wishlist.php?action=remove&part_id=<?= (int)$item['part_id'] ?>&_csrf=<?= generateCsrfToken() ?>"
                                                       title="<?= t('remove') ?>">X</a>
                                                </td>
                                                <td class="product_thumb">
                                                    <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$item['part_id'] ?>">
                                                        <img src="<?= sanitize($img) ?>"
                                                             alt="<?= sanitize($item['name']) ?>">
                                                    </a>
                                                </td>
                                                <td class="product_name">
                                                    <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$item['part_id'] ?>">
                                                        <?= sanitize(truncate($item['name'], 55)) ?>
                                                    </a>
                                                    <p><?= sanitize($item['brand_name']) ?> &bull; <?= sanitize($item['part_number']) ?></p>
                                                </td>
                                                <td class="product-price"><?= formatPrice($item['price']) ?></td>
                                                <td class="product_quantity">
                                                    <span class="stock-<?= sanitize($stock['class']) ?>">
                                                        <?= sanitize($stock['label']) ?>
                                                    </span>
                                                </td>
                                                <td class="product_total">
                                                    <a href="javascript:void(0)"
                                                       onclick="addToCart(<?= (int)$item['part_id'] ?>)">
                                                        <?= t('add_to_cart') ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="wishlist_share">
                        <h4><?= t('share_on') ?></h4>
                        <ul>
                            <li><a href="#"><i class="fa fa-rss"></i></a></li>
                            <li><a href="#"><i class="fa fa-vimeo"></i></a></li>
                            <li><a href="#"><i class="fa fa-tumblr"></i></a></li>
                            <li><a href="#"><i class="fa fa-pinterest"></i></a></li>
                            <li><a href="#"><i class="fa fa-linkedin"></i></a></li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!--wishlist area end -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
