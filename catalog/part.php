<?php
require_once dirname(__DIR__) . '/config/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flashMessage('danger', 'Товар не найден.');
    redirect(APP_URL . '/catalog/index.php');
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, b.country AS brand_country,
            c.name AS category_name, c.slug AS category_slug
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.is_active = 1"
);
$stmt->execute([$id]);
$part = $stmt->fetch();

if (!$part) {
    flashMessage('danger', 'Товар не найден или снят с продажи.');
    redirect(APP_URL . '/catalog/index.php');
}

// Related products (same category)
$relStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     WHERE p.is_active = 1 AND p.id != ? AND p.category_id = ?
     ORDER BY p.created_at DESC
     LIMIT 5"
);
$relStmt->execute([$id, $part['category_id']]);
$related = $relStmt->fetchAll();

// Parse images
$images = is_string($part['images']) ? json_decode($part['images'], true) : ($part['images'] ?? []);
if (!is_array($images)) $images = [];

$mainImage = !empty($images[0]) ? productImageUrl($images, 0) : APP_URL . '/assets/img/product/product1.jpg';

$stock     = getStockStatus((int)$part['stock']);
$pageTitle = $part['name'];
$csrf      = generateCsrfToken();

require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb([
    ['label' => t('home'),  'url' => APP_URL . '/index.php'],
    ['label' => t('shop'),  'url' => APP_URL . '/catalog/index.php'],
    ['label' => sanitize($part['category_name']), 'url' => APP_URL . '/catalog/category.php?slug=' . urlencode($part['category_slug'] ?? '')],
    ['label' => sanitize($part['name'])],
]) ?>

<!--product details area start-->
<div class="product_page_bg">
    <div class="container">
        <!--product details start-->
        <div class="product_details">
            <div class="row">
                <!-- Product Images -->
                <div class="col-lg-5 col-md-6">
                    <div class="product-details-tab">
                        <div id="img-1" class="zoomWrapper single-zoom">
                            <a href="#">
                                <img id="zoom1"
                                     src="<?= sanitize($mainImage) ?>"
                                     data-zoom-image="<?= sanitize($mainImage) ?>"
                                     alt="<?= sanitize($part['name']) ?>">
                            </a>
                        </div>
                        <?php if (count($images) > 1): ?>
                        <div class="single-zoom-thumb">
                            <ul class="s-tab-zoom owl-carousel single-product-active" id="gallery_01">
                                <?php foreach ($images as $i => $img): $imgU = productImageUrl($images, $i); ?>
                                <li>
                                    <a href="#"
                                       class="elevatezoom-gallery <?= $i === 0 ? 'active' : '' ?>"
                                       data-update=""
                                       data-image="<?= sanitize($imgU) ?>"
                                       data-zoom-image="<?= sanitize($imgU) ?>">
                                        <img src="<?= sanitize($imgU) ?>"
                                             alt="<?= sanitize($part['name']) ?> <?= $i + 1 ?>"/>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                        <div class="single-zoom-thumb">
                            <ul class="s-tab-zoom owl-carousel single-product-active" id="gallery_01">
                                <li>
                                    <a href="#" class="elevatezoom-gallery active"
                                       data-update=""
                                       data-image="<?= sanitize($mainImage) ?>"
                                       data-zoom-image="<?= sanitize($mainImage) ?>">
                                        <img src="<?= sanitize($mainImage) ?>"
                                             alt="<?= sanitize($part['name']) ?>"/>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- /product images -->

                <!-- Product Info -->
                <div class="col-lg-7 col-md-6">
                    <div class="product_d_right">
                        <form action="<?= APP_URL ?>/api/cart.php" method="post" id="add-cart-form">
                            <input type="hidden" name="action"  value="add">
                            <input type="hidden" name="part_id" value="<?= (int)$part['id'] ?>">
                            <input type="hidden" name="_csrf"   value="<?= sanitize($csrf) ?>">

                            <h3>
                                <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>">
                                    <?= sanitize($part['name']) ?>
                                </a>
                            </h3>

                            <div class="product_meta" style="margin-bottom:10px;">
                                <span>
                                    <?= t('brand') ?>:
                                    <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                        <?= sanitize($part['brand_name']) ?>
                                    </a>
                                </span>
                                <?php if ($part['category_name']): ?>
                                &nbsp;&nbsp;
                                <span>
                                    <?= t('category') ?>:
                                    <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($part['category_slug'] ?? '') ?>">
                                        <?= sanitize($part['category_name']) ?>
                                    </a>
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="price_box">
                                <span class="current_price"><?= formatPrice($part['price']) ?></span>
                            </div>

                            <div class="product_desc" style="margin-bottom:16px;">
                                <?php if ($part['description']): ?>
                                <p><?= nl2br(sanitize(truncate($part['description'], 300))) ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Availability -->
                            <div class="product_variant" style="margin-bottom:16px;">
                                <p class="text_available">
                                    <?= t('availability') ?>:
                                    <span class="<?= $stock['class'] === 'success' ? 'in_stock' : 'out_stock' ?>">
                                        <?= $stock['label'] ?>
                                    </span>
                                    <?php if ($part['stock'] > 0): ?>
                                    <small style="color:#888;margin-left:8px;">(<?= (int)$part['stock'] ?> <?= t('quantity') ?>)</small>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <!-- Quantity + Add to Cart -->
                            <?php if (isLoggedIn()): ?>
                                <?php if ($part['stock'] > 0): ?>
                                <div class="product_variant quantity">
                                    <label><?= t('quantity') ?></label>
                                    <input type="number" name="quantity" id="qty-field"
                                           value="1" min="1" max="<?= min((int)$part['stock'], 99) ?>">
                                    <button class="button" type="submit">
                                        <?= t('add_to_cart') ?>
                                    </button>
                                </div>
                                <?php else: ?>
                                <div class="product_variant" style="margin-bottom:16px;">
                                    <p style="color:#c62828;font-weight:600;"><?= t('out_of_stock') ?></p>
                                </div>
                                <?php endif; ?>

                                <div class="product_d_action">
                                    <ul>
                                        <li>
                                            <a href="javascript:void(0)"
                                               onclick="addToWishlist(<?= (int)$part['id'] ?>)"
                                               title="<?= t('add_to_wishlist') ?>">
                                                + <?= t('add_to_wishlist') ?>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="product_variant" style="margin-bottom:16px;">
                                    <a href="<?= APP_URL ?>/auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                       class="button">
                                        <?= t('login') ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- SKU / Part number -->
                            <div class="product_meta">
                                <span><?= t('part_number') ?>: <a href="#"><?= sanitize($part['part_number']) ?></a></span>
                                <?php if ($part['brand_country']): ?>
                                &nbsp;&nbsp;
                                <span><?= t('country') ?? 'Country' ?>: <?= sanitize($part['brand_country']) ?></span>
                                <?php endif; ?>
                            </div>

                        </form>
                    </div>
                </div>
                <!-- /product info -->

            </div>
        </div>
        <!--product details end-->

        <!--product info start-->
        <div class="product_d_info">
            <div class="row">
                <div class="col-12">
                    <div class="product_d_inner">
                        <div class="product_info_button">
                            <ul class="nav" role="tablist" id="nav-tab">
                                <li>
                                    <a class="active" data-bs-toggle="tab" href="#info" role="tab"
                                       aria-controls="info" aria-selected="true">
                                        <?= t('description') ?>
                                    </a>
                                </li>
                                <li>
                                    <a data-bs-toggle="tab" href="#sheet" role="tab"
                                       aria-controls="sheet" aria-selected="false">
                                        <?= t('specifications') ?>
                                    </a>
                                </li>
                                <li>
                                    <a data-bs-toggle="tab" href="#reviews" role="tab"
                                       aria-controls="reviews" aria-selected="false">
                                        <?= t('reviews') ?? 'Reviews' ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-content">
                            <!-- Description tab -->
                            <div class="tab-pane fade show active" id="info" role="tabpanel">
                                <div class="product_info_content">
                                    <?php if ($part['description']): ?>
                                    <p><?= nl2br(sanitize($part['description'])) ?></p>
                                    <?php else: ?>
                                    <p><em><?= t('no_description') ?></em></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Specifications tab -->
                            <div class="tab-pane fade" id="sheet" role="tabpanel">
                                <div class="product_d_table">
                                    <form action="#">
                                        <table>
                                            <tbody>
                                                <tr>
                                                    <td class="first_child"><?= t('part_number') ?></td>
                                                    <td><?= sanitize($part['part_number']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="first_child"><?= t('brand') ?></td>
                                                    <td><?= sanitize($part['brand_name']) ?></td>
                                                </tr>
                                                <?php if ($part['brand_country']): ?>
                                                <tr>
                                                    <td class="first_child"><?= t('country') ?? 'Country' ?></td>
                                                    <td><?= sanitize($part['brand_country']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="first_child"><?= t('category') ?></td>
                                                    <td><?= sanitize($part['category_name']) ?></td>
                                                </tr>
                                                <?php if (!empty($part['weight'])): ?>
                                                <tr>
                                                    <td class="first_child"><?= t('weight') ?></td>
                                                    <td><?= sanitize($part['weight']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($part['dimensions'])): ?>
                                                <tr>
                                                    <td class="first_child"><?= t('dimensions') ?></td>
                                                    <td><?= sanitize($part['dimensions']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="first_child"><?= t('in_stock') ?></td>
                                                    <td><?= $stock['label'] ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </form>
                                </div>
                            </div>

                            <!-- Reviews tab (placeholder) -->
                            <div class="tab-pane fade" id="reviews" role="tabpanel">
                                <div class="reviews_wrapper">
                                    <div class="comment_title">
                                        <h2><?= t('reviews') ?? 'Reviews' ?></h2>
                                        <p><?= t('no_reviews') ?? 'No reviews yet.' ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--product info end-->

        <!--related products start-->
        <?php if (!empty($related)): ?>
        <section class="product_area related_products">
            <div class="row">
                <div class="col-12">
                    <div class="section_title title_style2">
                        <div class="title_content">
                            <h2><span><?= t('related') ?? 'Related' ?></span> <?= t('products') ?? 'Products' ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="product_carousel product_details_column5 owl-carousel">
                    <?php foreach ($related as $rel):
                        $relStock = getStockStatus((int)$rel['stock']);
                        $relImg   = productImageUrl($rel['images']);
                    ?>
                    <div class="col-lg-3">
                        <article class="single_product">
                            <figure>
                                <div class="product_thumb">
                                    <a class="primary_img" href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$rel['id'] ?>">
                                        <img src="<?= sanitize($relImg) ?>" alt="<?= sanitize($rel['name']) ?>">
                                    </a>
                                    <a class="secondary_img" href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$rel['id'] ?>">
                                        <img src="<?= sanitize($relImg) ?>" alt="<?= sanitize($rel['name']) ?>">
                                    </a>
                                    <?php if ($rel['stock'] <= 0): ?>
                                    <div class="label_product">
                                        <span class="label_sale"><?= t('out_of_stock') ?></span>
                                    </div>
                                    <?php elseif ($rel['stock'] <= 5): ?>
                                    <div class="label_product">
                                        <span class="label_new"><?= t('low_stock') ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="quick_button">
                                        <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$rel['id'] ?>" title="<?= t('quick_view') ?>">
                                            <i class="icon-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="product_content">
                                    <div class="product_content_inner">
                                        <p class="manufacture_product">
                                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$rel['brand_id'] ?>">
                                                <?= sanitize($rel['brand_name']) ?>
                                            </a>
                                        </p>
                                        <h4 class="product_name">
                                            <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$rel['id'] ?>">
                                                <?= sanitize(truncate($rel['name'], 55)) ?>
                                            </a>
                                        </h4>
                                        <div class="price_box">
                                            <span class="current_price"><?= formatPrice($rel['price']) ?></span>
                                        </div>
                                    </div>
                                    <div class="action_links">
                                        <ul>
                                            <?php if (isLoggedIn()): ?>
                                            <li class="add_to_cart">
                                                <a href="javascript:void(0)" onclick="addToCart(<?= (int)$rel['id'] ?>)" title="<?= t('add_to_cart') ?>">
                                                    <?= t('add_to_cart') ?>
                                                </a>
                                            </li>
                                            <li class="wishlist">
                                                <a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$rel['id'] ?>)" title="<?= t('add_to_wishlist') ?>">
                                                    <i class="icon-heart"></i>
                                                </a>
                                            </li>
                                            <?php else: ?>
                                            <li class="add_to_cart">
                                                <a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </figure>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        <!--related products end-->

    </div><!-- /.container -->
</div><!-- /.product_page_bg -->
<!--product details area end-->

<script>
// Add to cart via form interception (for qty sync)
var cartForm = document.getElementById('add-cart-form');
if (cartForm) {
    cartForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var qty = document.getElementById('qty-field') ? parseInt(document.getElementById('qty-field').value) : 1;
        addToCart(<?= (int)$part['id'] ?>, qty);
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
