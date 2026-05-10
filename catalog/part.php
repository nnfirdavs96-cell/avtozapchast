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
     LIMIT 4"
);
$relStmt->execute([$id, $part['category_id']]);
$related = $relStmt->fetchAll();

// Parse images
$images = is_string($part['images']) ? json_decode($part['images'], true) : ($part['images'] ?? []);
if (!is_array($images)) $images = [];

$mainImage = !empty($images[0]) ? UPLOAD_URL . $images[0] : APP_URL . '/assets/img/product/placeholder.jpg';

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

<!-- Product Details Area -->
<div class="product_details_area">
    <div class="container">
        <div class="row">

            <!-- Product Images -->
            <div class="col-lg-5 col-md-5">
                <div class="product_details">
                    <div class="product_thumb_details">
                        <div class="product_thumb_details_inner">
                            <div class="product_big_img">
                                <div id="main-product-img" style="text-align:center;background:#f9f9f9;border:1px solid #eee;border-radius:6px;padding:16px;min-height:320px;display:flex;align-items:center;justify-content:center;">
                                    <img src="<?= sanitize($mainImage) ?>"
                                         alt="<?= sanitize($part['name']) ?>"
                                         style="max-height:300px;max-width:100%;object-fit:contain;"
                                         id="zoom-img">
                                </div>
                            </div>
                            <?php if (count($images) > 1): ?>
                            <div class="product_thumb_details_small" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
                                <?php foreach ($images as $i => $img): ?>
                                <div style="width:70px;height:70px;border:2px solid <?= $i === 0 ? '#d32f2f' : '#eee' ?>;border-radius:4px;overflow:hidden;cursor:pointer;"
                                     onclick="document.getElementById('zoom-img').src='<?= sanitize(UPLOAD_URL . $img) ?>';this.parentNode.querySelectorAll('div').forEach(function(el){el.style.borderColor='#eee';});this.style.borderColor='#d32f2f';">
                                    <img src="<?= sanitize(UPLOAD_URL . $img) ?>"
                                         alt="<?= sanitize($part['name']) ?> <?= $i + 1 ?>"
                                         style="width:100%;height:100%;object-fit:cover;">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->

            <!-- Product Info -->
            <div class="col-lg-7 col-md-7">
                <div class="product_content_details">

                    <div class="product_details_title">
                        <p class="manufacture_product">
                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                <?= sanitize($part['brand_name']) ?>
                            </a>
                            <?php if ($part['category_name']): ?>
                            &nbsp;&middot;&nbsp;
                            <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($part['category_slug'] ?? '') ?>">
                                <?= sanitize($part['category_name']) ?>
                            </a>
                            <?php endif; ?>
                        </p>
                        <h2><?= sanitize($part['name']) ?></h2>
                        <p style="font-size:0.85rem;color:#888;margin:4px 0 12px;">
                            <?= t('part_number') ?>: <strong style="color:#d32f2f;"><?= sanitize($part['part_number']) ?></strong>
                        </p>
                    </div>

                    <!-- Stock + availability -->
                    <div style="margin-bottom:16px;">
                        <span style="display:inline-block;padding:4px 12px;border-radius:3px;font-size:0.8rem;font-weight:600;
                                     background:<?= $stock['class'] === 'success' ? '#e8f5e9' : ($stock['class'] === 'warning' ? '#fff3e0' : '#ffebee') ?>;
                                     color:<?= $stock['class'] === 'success' ? '#2e7d32' : ($stock['class'] === 'warning' ? '#e65100' : '#c62828') ?>;">
                            <?= $stock['label'] ?>
                        </span>
                        <?php if ($part['stock'] > 0): ?>
                        <span style="margin-left:10px;font-size:0.8rem;color:#888;"><?= (int)$part['stock'] ?> <?= t('quantity') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Price -->
                    <div class="price_box" style="margin-bottom:24px;">
                        <span class="current_price" style="font-size:2rem;font-weight:700;color:#d32f2f;"><?= formatPrice($part['price']) ?></span>
                    </div>

                    <!-- Specifications table -->
                    <div class="product_details" style="margin-bottom:24px;">
                        <table style="width:100%;font-size:0.875rem;border-collapse:collapse;">
                            <tbody>
                                <tr>
                                    <td style="padding:7px 12px 7px 0;color:#888;width:40%;border-bottom:1px solid #f0f0f0;"><?= t('part_number') ?></td>
                                    <td style="padding:7px 0;font-weight:600;color:#333;border-bottom:1px solid #f0f0f0;"><?= sanitize($part['part_number']) ?></td>
                                </tr>
                                <tr>
                                    <td style="padding:7px 12px 7px 0;color:#888;border-bottom:1px solid #f0f0f0;"><?= t('brand') ?></td>
                                    <td style="padding:7px 0;border-bottom:1px solid #f0f0f0;">
                                        <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>" style="color:#d32f2f;text-decoration:none;">
                                            <?= sanitize($part['brand_name']) ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php if ($part['brand_country']): ?>
                                <tr>
                                    <td style="padding:7px 12px 7px 0;color:#888;border-bottom:1px solid #f0f0f0;"><?= t('country') ?? 'Страна' ?></td>
                                    <td style="padding:7px 0;border-bottom:1px solid #f0f0f0;"><?= sanitize($part['brand_country']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="padding:7px 12px 7px 0;color:#888;border-bottom:1px solid #f0f0f0;"><?= t('category') ?></td>
                                    <td style="padding:7px 0;border-bottom:1px solid #f0f0f0;">
                                        <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($part['category_slug'] ?? '') ?>" style="color:#555;text-decoration:none;">
                                            <?= sanitize($part['category_name']) ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php if (!empty($part['weight'])): ?>
                                <tr>
                                    <td style="padding:7px 12px 7px 0;color:#888;border-bottom:1px solid #f0f0f0;"><?= t('weight') ?></td>
                                    <td style="padding:7px 0;border-bottom:1px solid #f0f0f0;"><?= sanitize($part['weight']) ?> кг</td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($part['dimensions'])): ?>
                                <tr>
                                    <td style="padding:7px 12px 7px 0;color:#888;"><?= t('dimensions') ?></td>
                                    <td style="padding:7px 0;"><?= sanitize($part['dimensions']) ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add to cart + wishlist -->
                    <div class="product_count_area">
                        <?php if (isLoggedIn()): ?>
                            <?php if ($part['stock'] > 0): ?>
                            <!-- Add to cart form -->
                            <form method="post" action="<?= APP_URL ?>/api/cart.php" id="add-cart-form">
                                <input type="hidden" name="action"   value="add">
                                <input type="hidden" name="part_id"  value="<?= (int)$part['id'] ?>">
                                <input type="hidden" name="_csrf"    value="<?= sanitize($csrf) ?>">
                                <div class="product_count" style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
                                    <div class="quantity_box" style="display:flex;align-items:center;border:1px solid #e0e0e0;border-radius:4px;overflow:hidden;">
                                        <button type="button"
                                                style="width:36px;height:40px;background:#f5f5f5;border:none;font-size:1.2rem;cursor:pointer;color:#555;"
                                                onclick="var i=document.getElementById('qty-field');i.value=Math.max(1,parseInt(i.value)-1);">
                                            &minus;
                                        </button>
                                        <input type="number" name="quantity" id="qty-field"
                                               value="1" min="1" max="<?= min((int)$part['stock'], 99) ?>"
                                               style="width:56px;height:40px;border:none;text-align:center;font-size:1rem;font-weight:600;outline:none;">
                                        <button type="button"
                                                style="width:36px;height:40px;background:#f5f5f5;border:none;font-size:1.2rem;cursor:pointer;color:#555;"
                                                onclick="var i=document.getElementById('qty-field');i.value=Math.min(<?= min((int)$part['stock'], 99) ?>,parseInt(i.value)+1);">
                                            +
                                        </button>
                                    </div>
                                    <button type="submit"
                                            style="flex:1;min-width:160px;padding:10px 24px;background:#d32f2f;color:#fff;border:none;border-radius:4px;font-size:0.95rem;font-weight:600;cursor:pointer;transition:background 0.2s;"
                                            onmouseover="this.style.background='#b71c1c'" onmouseout="this.style.background='#d32f2f'">
                                        <i class="icon-shopping-bag2" style="margin-right:6px;"></i>
                                        <?= t('add_to_cart') ?>
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <div style="padding:12px 16px;background:#ffebee;border:1px solid #ffcdd2;border-radius:4px;color:#c62828;font-size:0.875rem;margin-bottom:16px;">
                                <?= t('out_of_stock') ?> — <?= t('check_availability') ?? 'Уточните наличие' ?>
                            </div>
                            <?php endif; ?>

                            <!-- Add to wishlist -->
                            <div style="margin-bottom:16px;">
                                <a href="javascript:void(0)"
                                   onclick="addToWishlist(<?= (int)$part['id'] ?>)"
                                   style="display:inline-flex;align-items:center;gap:6px;color:#555;text-decoration:none;font-size:0.875rem;border:1px solid #ddd;padding:8px 16px;border-radius:4px;transition:all 0.2s;"
                                   onmouseover="this.style.borderColor='#d32f2f';this.style.color='#d32f2f'"
                                   onmouseout="this.style.borderColor='#ddd';this.style.color='#555'">
                                    <i class="icon-heart"></i> <?= t('add_to_wishlist') ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="padding:16px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;text-align:center;margin-bottom:16px;">
                                <p style="margin:0 0 10px;color:#555;"><?= t('login_required') ?></p>
                                <a href="<?= APP_URL ?>/auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                   style="background:#d32f2f;color:#fff;padding:8px 24px;border-radius:4px;text-decoration:none;font-weight:600;">
                                    <?= t('login') ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Share / misc actions -->
                    <div style="padding-top:16px;border-top:1px solid #f0f0f0;">
                        <span style="font-size:0.8rem;color:#888;">
                            <i class="icon-settings" style="margin-right:4px;"></i>
                            <?= t('part_number') ?>: <strong><?= sanitize($part['part_number']) ?></strong>
                        </span>
                    </div>

                </div><!-- /.product_content_details -->
            </div><!-- /.col -->
        </div><!-- /.row -->

        <!-- ── TABS: Description / Specifications ─────────────────────── -->
        <div class="row" style="margin-top:48px;">
            <div class="col-12">
                <div class="product_d_info">
                    <!-- Tab navigation -->
                    <ul class="nav product_info_button" id="myTabOne" role="tablist"
                        style="display:flex;gap:0;border-bottom:2px solid #eee;margin-bottom:0;list-style:none;padding:0;">
                        <li role="presentation">
                            <a href="#description" class="nav-link active"
                               style="display:block;padding:12px 24px;font-weight:600;font-size:0.9rem;color:#d32f2f;border-bottom:2px solid #d32f2f;margin-bottom:-2px;text-decoration:none;"
                               id="tab-desc">
                                <?= t('description') ?>
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#specifications" class="nav-link"
                               style="display:block;padding:12px 24px;font-weight:600;font-size:0.9rem;color:#666;border-bottom:2px solid transparent;margin-bottom:-2px;text-decoration:none;"
                               id="tab-specs">
                                <?= t('specifications') ?>
                            </a>
                        </li>
                    </ul>

                    <!-- Tab content -->
                    <div class="tab-content product_d_content" style="padding:28px 0;">
                        <!-- Description -->
                        <div id="description" class="tab-pane active">
                            <?php if ($part['description']): ?>
                            <p style="color:#555;line-height:1.9;font-size:0.9rem;"><?= nl2br(sanitize($part['description'])) ?></p>
                            <?php else: ?>
                            <p style="color:#aaa;font-style:italic;"><?= t('no_description') ?? 'Описание не добавлено.' ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Specifications -->
                        <div id="specifications" class="tab-pane" style="display:none;">
                            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                                <tbody>
                                    <tr style="background:#f9f9f9;">
                                        <td style="padding:10px 16px;color:#666;width:220px;border-bottom:1px solid #eee;"><?= t('part_number') ?></td>
                                        <td style="padding:10px 16px;border-bottom:1px solid #eee;font-weight:600;"><?= sanitize($part['part_number']) ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 16px;color:#666;border-bottom:1px solid #eee;"><?= t('brand') ?></td>
                                        <td style="padding:10px 16px;border-bottom:1px solid #eee;"><?= sanitize($part['brand_name']) ?></td>
                                    </tr>
                                    <?php if ($part['brand_country']): ?>
                                    <tr style="background:#f9f9f9;">
                                        <td style="padding:10px 16px;color:#666;border-bottom:1px solid #eee;"><?= t('country') ?? 'Страна' ?></td>
                                        <td style="padding:10px 16px;border-bottom:1px solid #eee;"><?= sanitize($part['brand_country']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr <?= $part['brand_country'] ? '' : 'style="background:#f9f9f9;"' ?>>
                                        <td style="padding:10px 16px;color:#666;border-bottom:1px solid #eee;"><?= t('category') ?></td>
                                        <td style="padding:10px 16px;border-bottom:1px solid #eee;"><?= sanitize($part['category_name']) ?></td>
                                    </tr>
                                    <?php if (!empty($part['weight'])): ?>
                                    <tr style="background:#f9f9f9;">
                                        <td style="padding:10px 16px;color:#666;border-bottom:1px solid #eee;"><?= t('weight') ?></td>
                                        <td style="padding:10px 16px;border-bottom:1px solid #eee;"><?= sanitize($part['weight']) ?> кг</td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($part['dimensions'])): ?>
                                    <tr>
                                        <td style="padding:10px 16px;color:#666;border-bottom:1px solid #eee;"><?= t('dimensions') ?></td>
                                        <td style="padding:10px 16px;border-bottom:1px solid #eee;"><?= sanitize($part['dimensions']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr style="background:#f9f9f9;">
                                        <td style="padding:10px 16px;color:#666;"><?= t('in_stock') ?></td>
                                        <td style="padding:10px 16px;">
                                            <span style="padding:3px 10px;border-radius:3px;font-size:0.8rem;background:<?= $stock['class'] === 'success' ? '#e8f5e9' : ($stock['class'] === 'warning' ? '#fff3e0' : '#ffebee') ?>;color:<?= $stock['class'] === 'success' ? '#2e7d32' : ($stock['class'] === 'warning' ? '#e65100' : '#c62828') ?>;">
                                                <?= $stock['label'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div><!-- /.tab-content -->
                </div><!-- /.product_d_info -->
            </div>
        </div>

        <!-- ── RELATED PRODUCTS ────────────────────────────────────────── -->
        <?php if (!empty($related)): ?>
        <div class="related_products_area" style="margin-top:56px;padding-top:36px;border-top:2px solid #f0f0f0;">
            <div class="row">
                <div class="col-12" style="margin-bottom:24px;">
                    <h3 style="font-size:1.3rem;font-weight:700;color:#222;"><?= t('related_products') ?? 'Похожие товары' ?></h3>
                </div>
            </div>
            <div class="row shop_wrapper">
                <?php foreach ($related as $rel):
                    $relStock  = getStockStatus((int)$rel['stock']);
                    $relImg    = productImageUrl($rel['images']);
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6 col-6 mb-4">
                    <article class="single_product">
                        <figure>
                            <div class="product_thumb">
                                <a class="primary_img" href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$rel['id'] ?>">
                                    <img src="<?= sanitize($relImg) ?>"
                                         alt="<?= sanitize($rel['name']) ?>"
                                         style="height:180px;width:100%;object-fit:cover;">
                                </a>
                                <div class="action_links">
                                    <ul>
                                        <li class="quick_button">
                                            <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$rel['id'] ?>"
                                               title="<?= t('quick_view') ?>">
                                                <i class="icon-eye"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="product_content grid_content">
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
                                <p style="font-size:0.72rem;color:#aaa;margin:2px 0;"><?= sanitize($rel['part_number']) ?></p>
                                <div class="price_box">
                                    <span class="current_price"><?= formatPrice($rel['price']) ?></span>
                                </div>
                            </div>
                            <div class="action_links action_links_product">
                                <ul>
                                    <?php if (isLoggedIn()): ?>
                                    <li class="add_to_cart">
                                        <a href="javascript:void(0)" onclick="addToCart(<?= (int)$rel['id'] ?>)">
                                            <?= t('add_to_cart') ?>
                                        </a>
                                    </li>
                                    <li class="wishlist">
                                        <a href="javascript:void(0)"
                                           onclick="addToWishlist(<?= (int)$rel['id'] ?>)"
                                           title="<?= t('add_to_wishlist') ?>">
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
                        </figure>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.container -->
</div><!-- /.product_details_area -->

<script>
// Tab switching
document.querySelectorAll('#myTabOne a').forEach(function(tab) {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        var target = this.getAttribute('href').substring(1);
        document.querySelectorAll('.tab-pane').forEach(function(p) { p.style.display = 'none'; });
        document.getElementById(target).style.display = 'block';
        document.querySelectorAll('#myTabOne a').forEach(function(t) {
            t.style.color = '#666';
            t.style.borderBottomColor = 'transparent';
        });
        this.style.color = '#d32f2f';
        this.style.borderBottomColor = '#d32f2f';
    });
});

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
