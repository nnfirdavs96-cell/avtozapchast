<?php
require_once dirname(__DIR__) . '/config/config.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    redirect(APP_URL . '/catalog/index.php');
}

$db = getDB();

// Load category by slug
$catStmt = $db->prepare(
    "SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1"
);
$catStmt->execute([$slug]);
$category = $catStmt->fetch();

if (!$category) {
    flashMessage('danger', 'Категория не найдена.');
    redirect(APP_URL . '/catalog/index.php');
}

$catId   = (int)$category['id'];
$catName = tField($category, 'name');

// Load child categories
$childStmt = $db->prepare(
    "SELECT id FROM categories WHERE parent_id = ? AND is_active = 1"
);
$childStmt->execute([$catId]);
$childIds   = array_column($childStmt->fetchAll(), 'id');
$allCatIds  = array_merge([$catId], $childIds);
$inPlaces   = implode(',', array_fill(0, count($allCatIds), '?'));

// GET params
$sort     = in_array($_GET['sort'] ?? '', ['price_asc', 'price_desc', 'newest']) ? $_GET['sort'] : 'newest';
$brandId  = (int)($_GET['brand'] ?? 0);
$inStock  = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$view     = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
$priceMin = (float)($_GET['price_min'] ?? 0);
$priceMax = (float)($_GET['price_max'] ?? 0);

// Build WHERE
$where  = ["p.is_active = 1", "p.category_id IN ($inPlaces)"];
$params = $allCatIds;

if ($brandId) {
    $where[]  = 'p.brand_id = ?';
    $params[] = $brandId;
}
if ($inStock) {
    $where[] = 'p.stock > 0';
}
if ($priceMin > 0) {
    $where[]  = 'p.price >= ?';
    $params[] = $priceMin;
}
if ($priceMax > 0) {
    $where[]  = 'p.price <= ?';
    $params[] = $priceMax;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count
$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSQL"
);
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Sort
$orderMap = [
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest'     => 'p.created_at DESC',
];
$orderSQL = $orderMap[$sort] ?? 'p.created_at DESC';

// Products
$partsStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSQL
     ORDER BY $orderSQL
     LIMIT $perPage OFFSET $offset"
);
$partsStmt->execute($params);
$parts = $partsStmt->fetchAll();
$ratings = getProductRatings(array_column($parts, 'id'));

// Sidebar data (brands for this category)
$allBrands = getBrands();
$brandsInCat = $db->prepare(
    "SELECT DISTINCT b.* FROM brands b
     JOIN parts p ON p.brand_id = b.id
     WHERE p.is_active = 1 AND p.category_id IN ($inPlaces) AND b.is_active = 1
     ORDER BY b.name"
);
$brandsInCat->execute($allCatIds);
$catBrands = $brandsInCat->fetchAll();

$currentBrand = null;
if ($brandId) {
    foreach ($catBrands as $b) {
        if ((int)$b['id'] === $brandId) { $currentBrand = $b; break; }
    }
}

// Parent category for breadcrumb
$parentCat = null;
if ($category['parent_id']) {
    $parStmt = $db->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
    $parStmt->execute([$category['parent_id']]);
    $parentCat = $parStmt->fetch();
}

$pageTitle = $catName . ' — ' . getSetting('site_name');

// ── SEO: per-page meta from the category's own description ────────────────
$catDescRaw = trim(strip_tags((string)tField($category, 'description')));
$pageDescription = $catDescRaw !== ''
    ? mb_substr(preg_replace('/\s+/u', ' ', $catDescRaw), 0, 300)
    : $catName . '. ' . getSetting('meta_description', t('tagline'));
$canonical = APP_URL . '/catalog/category.php?slug=' . urlencode($category['slug']);
if (!empty($category['image_path'])) {
    $ci = $category['image_path'];
    if (preg_match('#^https?://#i', $ci))      $ogImage = $ci;
    elseif ($ci[0] === '/')                    $ogImage = rtrim(APP_URL, '/') . $ci;
    else                                       $ogImage = UPLOAD_URL . ltrim($ci, '/');
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">
<?php
// Build breadcrumb
$bcItems = [
    ['label' => t('home'), 'url' => APP_URL . '/index.php'],
    ['label' => t('shop'), 'url' => APP_URL . '/catalog/index.php'],
];
if ($parentCat) {
    $bcItems[] = ['label' => tField($parentCat, 'name'), 'url' => APP_URL . '/catalog/category.php?slug=' . urlencode($parentCat['slug'])];
}
$bcItems[] = ['label' => $catName];
?>

<?= breadcrumb($bcItems) ?>

<!--shop area start-->
<div class="shop_area">
    <div class="container">
        <div class="row">
            <?php ob_start(); /* capture sidebar — render after main column per shop-right-sidebar.html */ ?>
            <div class="col-lg-3 col-md-12">
                <!--sidebar widget start-->
                <aside class="sidebar_widget">

                    <!-- Category heading -->
                    <div class="widget_list widget_categories">
                        <h3><?= sanitize($catName) ?></h3>
                        <?php if ($category['description']): ?>
                        <p style="font-size:0.875rem;color:#666;margin-bottom:12px;"><?= sanitize(truncate($category['description'], 160)) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Price filter widget -->
                    <div class="widget_list widget_filter">
                        <h3><?= t('filter_by_price') ?></h3>
                        <form method="get" action="">
                            <input type="hidden" name="slug" value="<?= sanitize($slug) ?>">
                            <?php if ($brandId): ?><input type="hidden" name="brand" value="<?= $brandId ?>"><?php endif; ?>
                            <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= sanitize($sort) ?>"><?php endif; ?>
                            <?php if ($inStock): ?><input type="hidden" name="in_stock" value="1"><?php endif; ?>
                            <?php if ($view !== 'grid'): ?><input type="hidden" name="view" value="list"><?php endif; ?>
                            <div class="price_range_inputs" style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
                                <input type="number" name="price_min" class="form-control form-control-sm"
                                       placeholder="<?= t('from') ?>"
                                       value="<?= $priceMin > 0 ? (int)$priceMin : '' ?>" min="0"
                                       style="flex:1;">
                                <span>—</span>
                                <input type="number" name="price_max" class="form-control form-control-sm"
                                       placeholder="<?= t('to') ?>"
                                       value="<?= $priceMax > 0 ? (int)$priceMax : '' ?>" min="0"
                                       style="flex:1;">
                            </div>
                            <button type="submit"><?= t('apply') ?></button>
                            <?php if ($priceMin > 0 || $priceMax > 0): ?>
                            <a href="?slug=<?= urlencode($slug) ?><?= $brandId ? '&brand='.$brandId : '' ?><?= $sort !== 'newest' ? '&sort='.sanitize($sort) : '' ?><?= $inStock ? '&in_stock=1' : '' ?>"
                               style="display:block;text-align:center;margin-top:6px;font-size:0.8rem;color:#999;">
                               <?= t('reset') ?>
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Brands in this category -->
                    <?php if (!empty($catBrands)): ?>
                    <div class="widget_list widget_categories">
                        <h3><?= t('filter_by_brand') ?></h3>
                        <ul>
                            <li class="<?= !$brandId ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query(array_diff_key($_GET, ['brand' => '', 'page' => ''])) ?>">
                                    <?= t('all_brands') ?>
                                </a>
                            </li>
                            <?php foreach ($catBrands as $b):
                                $qArr = array_merge(array_diff_key($_GET, ['page' => '']), ['brand' => $b['id']]);
                            ?>
                            <li class="<?= $brandId === (int)$b['id'] ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query($qArr) ?>">
                                    <?= sanitize($b['name']) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Availability widget -->
                    <div class="widget_list widget_categories">
                        <h3><?= t('availability') ?></h3>
                        <ul>
                            <li class="<?= !$inStock ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query(array_diff_key($_GET, ['in_stock' => '', 'page' => ''])) ?>">
                                    <?= t('all_products') ?>
                                </a>
                            </li>
                            <li class="<?= $inStock ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['page' => '']), ['in_stock' => '1'])) ?>">
                                    <?= t('in_stock') ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Other categories -->
                    <div class="widget_list widget_categories">
                        <h3><?= t('categories') ?></h3>
                        <ul>
                            <?php
                            $allCats = getCategories();
                            foreach ($allCats as $cat):
                                if ($cat['parent_id'] !== null) continue;
                            ?>
                            <li class="<?= (int)$cat['id'] === $catId ? 'active_categorie' : '' ?>">
                                <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>">
                                    <?= sanitize(tField($cat, 'name')) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Active filters tags -->
                    <?php if ($brandId || $inStock || $priceMin || $priceMax): ?>
                    <div class="widget_list tags_widget">
                        <h3><?= t('active_filters') ?? t('filters') ?></h3>
                        <div class="tag_cloud">
                            <?php if ($currentBrand): ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['brand' => '', 'page' => ''])) ?>">
                                <?= sanitize($currentBrand['name']) ?> &times;
                            </a>
                            <?php endif; ?>
                            <?php if ($inStock): ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['in_stock' => '', 'page' => ''])) ?>">
                                <?= t('in_stock') ?> &times;
                            </a>
                            <?php endif; ?>
                            <?php if ($priceMin > 0 || $priceMax > 0): ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['price_min' => '', 'price_max' => '', 'page' => ''])) ?>">
                                <?= $priceMin > 0 ? (int)$priceMin : '' ?><?= ($priceMin > 0 && $priceMax > 0) ? '–' : '' ?><?= $priceMax > 0 ? (int)$priceMax : '' ?> &times;
                            </a>
                            <?php endif; ?>
                            <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($slug) ?>"><?= t('reset_all') ?></a>
                        </div>
                    </div>
                    <?php endif; ?>

                </aside>
                <!--sidebar widget end-->
            </div>
            <?php $sidebarHtml = ob_get_clean(); ?>

            <div class="col-lg-9 col-md-12">

                <!--shop banner area start-->
                <div class="shop_banner_area mb-30">
                    <div class="row">
                        <div class="col-12">
                            <div class="shop_banner_thumb">
                                <img src="<?= APP_URL ?>/assets/img/bg/banner23.jpg" alt="<?= sanitize($catName) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <!--shop banner area end-->

                <!--shop toolbar start-->
                <div class="shop_toolbar_wrapper">
                    <div class="shop_toolbar_btn">
                        <button data-role="grid_4" type="button"
                                class="<?= $view === 'grid' ? 'active ' : '' ?>btn-grid-4"
                                onclick="window.location='?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])) ?>'"
                                title="Grid"></button>
                        <button data-role="grid_list" type="button"
                                class="<?= $view === 'list' ? 'active ' : '' ?>btn-list"
                                onclick="window.location='?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>'"
                                title="List"></button>
                    </div>
                    <div class="niceselect_option">
                        <form class="select_option" method="get" action="" id="sort-form">
                            <input type="hidden" name="slug" value="<?= sanitize($slug) ?>">
                            <?php foreach (array_diff_key($_GET, ['sort' => '', 'page' => '', 'slug' => '']) as $k => $v): ?>
                            <input type="hidden" name="<?= sanitize($k) ?>" value="<?= sanitize($v) ?>">
                            <?php endforeach; ?>
                            <select name="sort" id="short" onchange="this.form.submit()">
                                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>><?= t('sort_newest') ?></option>
                                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>><?= t('sort_price_asc') ?></option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= t('sort_price_desc') ?></option>
                            </select>
                        </form>
                    </div>
                    <div class="page_amount">
                        <p><?= t('showing') ?> <?= count($parts) ?>–<?= $total ?> <?= t('results') ?></p>
                    </div>
                </div>
                <!--shop toolbar end-->

                <?php if (empty($parts)): ?>
                <div class="row">
                    <div class="col-12" style="text-align:center;padding:60px 20px;">
                        <i class="icon-settings" style="font-size:3rem;color:#ddd;display:block;margin-bottom:16px;"></i>
                        <h4><?= t('no_products_found') ?></h4>
                        <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($slug) ?>"
                           class="button" style="margin-top:16px;display:inline-block;">
                            <?= t('reset_filters') ?>
                        </a>
                    </div>
                </div>

                <?php elseif ($view === 'list'): ?>
                <!--list view start-->
                <div class="row shop_wrapper">
                    <?php foreach ($parts as $part):
                        $stock  = getStockStatus((int)$part['stock']);
                        $imgUrl = productImageUrl($part['images']);
                    ?>
                    <div class="col-12">
                        <article class="single_product">
                            <figure>
                                <div class="product_thumb">
                                    <a class="primary_img" href="<?= partUrl($part) ?>">
                                        <img src="<?= sanitize($imgUrl) ?>" alt="<?= sanitize($part['name']) ?>">
                                    </a>
                                    <?= productBadges($part) ?>
                                    <div class="quick_button">
                                        <a href="<?= partUrl($part) ?>" title="<?= t('quick_view') ?>">
                                            <i class="icon-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="product_content grid_content">
                                    <div class="product_content_inner">
                                        <p class="manufacture_product">
                                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                                <?= sanitize($part['brand_name']) ?>
                                            </a>
                                        </p>
                                        <h4 class="product_name">
                                            <a href="<?= partUrl($part) ?>">
                                                <?= sanitize($part['name']) ?>
                                            </a>
                                        </h4>
                                        <?= priceBox($part) ?>
                                        <?= productStarsInline((int)$part['id'], $ratings) ?>
                                    </div>
                                    <div class="action_links">
                                        <ul>
                                            <?php if (isLoggedIn()): ?>
                                            <li class="add_to_cart">
                                                <a href="javascript:void(0)" onclick="addToCart(<?= (int)$part['id'] ?>)" title="<?= t('add_to_cart') ?>">
                                                    <?= t('add_to_cart') ?>
                                                </a>
                                            </li>
                                            <li class="wishlist">
                                                <a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$part['id'] ?>)" title="<?= t('add_to_wishlist') ?>">
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
                                <div class="product_content list_content">
                                    <div class="left_caption">
                                        <p class="manufacture_product">
                                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                                <?= sanitize($part['brand_name']) ?>
                                            </a>
                                        </p>
                                        <h4 class="product_name">
                                            <a href="<?= partUrl($part) ?>">
                                                <?= sanitize($part['name']) ?>
                                            </a>
                                        </h4>
                                        <?= priceBox($part) ?>
                                        <?= productStarsInline((int)$part['id'], $ratings) ?>
                                        <div class="product_desc">
                                            <p><?= t('part_number') ?>: <?= sanitize($part['part_number']) ?></p>
                                        </div>
                                    </div>
                                    <div class="right_caption">
                                        <p class="text_available"><?= t('availability') ?>: <span><?= $stock['label'] ?></span></p>
                                        <div class="action_links">
                                            <ul>
                                                <?php if (isLoggedIn()): ?>
                                                <li class="add_to_cart">
                                                    <a href="javascript:void(0)" onclick="addToCart(<?= (int)$part['id'] ?>)" title="<?= t('add_to_cart') ?>">
                                                        <?= t('add_to_cart') ?>
                                                    </a>
                                                </li>
                                                <li class="wishlist">
                                                    <a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$part['id'] ?>)" title="<?= t('add_to_wishlist') ?>">
                                                        <i class="icon-heart"></i> <?= t('add_to_wishlist') ?>
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
                                </div>
                            </figure>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!--list view end-->

                <?php else: ?>
                <!--grid view start-->
                <div class="row shop_wrapper">
                    <?php foreach ($parts as $part):
                        $stock  = getStockStatus((int)$part['stock']);
                        $imgUrl = productImageUrl($part['images']);
                    ?>
                    <div class="col-lg-3 col-md-4 col-12">
                        <article class="single_product">
                            <figure>
                                <div class="product_thumb">
                                    <a class="primary_img" href="<?= partUrl($part) ?>">
                                        <img src="<?= sanitize($imgUrl) ?>" alt="<?= sanitize($part['name']) ?>">
                                    </a>
                                    <a class="secondary_img" href="<?= partUrl($part) ?>">
                                        <img src="<?= sanitize($imgUrl) ?>" alt="<?= sanitize($part['name']) ?>">
                                    </a>
                                    <?= productBadges($part) ?>
                                    <div class="quick_button">
                                        <a href="<?= partUrl($part) ?>" title="<?= t('quick_view') ?>">
                                            <i class="icon-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="product_content grid_content">
                                    <div class="product_content_inner">
                                        <p class="manufacture_product">
                                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                                <?= sanitize($part['brand_name']) ?>
                                            </a>
                                        </p>
                                        <h4 class="product_name">
                                            <a href="<?= partUrl($part) ?>">
                                                <?= sanitize(truncate($part['name'], 55)) ?>
                                            </a>
                                        </h4>
                                        <?= priceBox($part) ?>
                                        <?= productStarsInline((int)$part['id'], $ratings) ?>
                                    </div>
                                    <div class="action_links">
                                        <ul>
                                            <?php if (isLoggedIn()): ?>
                                            <li class="add_to_cart">
                                                <a href="javascript:void(0)" onclick="addToCart(<?= (int)$part['id'] ?>)" title="<?= t('add_to_cart') ?>">
                                                    <?= t('add_to_cart') ?>
                                                </a>
                                            </li>
                                            <li class="wishlist">
                                                <a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$part['id'] ?>)" title="<?= t('add_to_wishlist') ?>">
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
                                <div class="product_content list_content">
                                    <div class="left_caption">
                                        <p class="manufacture_product">
                                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                                <?= sanitize($part['brand_name']) ?>
                                            </a>
                                        </p>
                                        <h4 class="product_name">
                                            <a href="<?= partUrl($part) ?>">
                                                <?= sanitize($part['name']) ?>
                                            </a>
                                        </h4>
                                        <?= priceBox($part) ?>
                                        <?= productStarsInline((int)$part['id'], $ratings) ?>
                                        <div class="product_desc">
                                            <p><?= t('part_number') ?>: <?= sanitize($part['part_number']) ?></p>
                                        </div>
                                    </div>
                                    <div class="right_caption">
                                        <p class="text_available"><?= t('availability') ?>: <span><?= $stock['label'] ?></span></p>
                                        <div class="action_links">
                                            <ul>
                                                <?php if (isLoggedIn()): ?>
                                                <li class="add_to_cart">
                                                    <a href="javascript:void(0)" onclick="addToCart(<?= (int)$part['id'] ?>)" title="<?= t('add_to_cart') ?>">
                                                        <?= t('add_to_cart') ?>
                                                    </a>
                                                </li>
                                                <li class="wishlist">
                                                    <a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$part['id'] ?>)" title="<?= t('add_to_wishlist') ?>">
                                                        <i class="icon-heart"></i> <?= t('add_to_wishlist') ?>
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
                                </div>
                            </figure>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!--grid view end-->
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="paginatoin-area">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="pagination-box">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                    <li>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">
                                            <i class="fa fa-angle-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                    <li class="<?= $p === $page ? 'active' : '' ?>">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-link"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">
                                            <i class="fa fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /.col main -->
            <?= $sidebarHtml /* sidebar follows main per shop-right-sidebar.html */ ?>
        </div><!-- /.row -->
    </div><!-- /.container -->
</div><!-- /.shop_area -->
<!--shop area end-->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
