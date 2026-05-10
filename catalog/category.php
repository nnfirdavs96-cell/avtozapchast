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

<div class="shop_area shop_reverse">
    <div class="container">
        <div class="row">

            <!-- ══ SIDEBAR ══════════════════════════════════════════════════ -->
            <div class="col-lg-3 col-md-4">
                <div class="shop_sidebar_widget">

                    <!-- Price filter -->
                    <div class="single_shop_sidebar">
                        <h3><?= t('filter_by_price') ?></h3>
                        <form method="get" action="">
                            <input type="hidden" name="slug" value="<?= sanitize($slug) ?>">
                            <?php if ($brandId): ?><input type="hidden" name="brand" value="<?= $brandId ?>"><?php endif; ?>
                            <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= sanitize($sort) ?>"><?php endif; ?>
                            <?php if ($inStock): ?><input type="hidden" name="in_stock" value="1"><?php endif; ?>
                            <?php if ($view !== 'grid'): ?><input type="hidden" name="view" value="list"><?php endif; ?>
                            <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
                                <input type="number" name="price_min" class="form-control form-control-sm"
                                       placeholder="<?= t('from') ?>"
                                       value="<?= $priceMin > 0 ? (int)$priceMin : '' ?>" min="0" style="flex:1;">
                                <span style="color:#aaa;">—</span>
                                <input type="number" name="price_max" class="form-control form-control-sm"
                                       placeholder="<?= t('to') ?>"
                                       value="<?= $priceMax > 0 ? (int)$priceMax : '' ?>" min="0" style="flex:1;">
                            </div>
                            <button type="submit" style="background:#d32f2f;color:#fff;border:none;padding:6px 16px;border-radius:4px;width:100%;cursor:pointer;"><?= t('apply') ?></button>
                            <?php if ($priceMin > 0 || $priceMax > 0): ?>
                            <a href="?slug=<?= urlencode($slug) ?><?= $brandId ? '&brand='.$brandId : '' ?><?= $sort !== 'newest' ? '&sort='.sanitize($sort) : '' ?><?= $inStock ? '&in_stock=1' : '' ?>"
                               style="display:block;text-align:center;margin-top:6px;font-size:0.8rem;color:#999;text-decoration:underline;"><?= t('reset') ?></a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Availability -->
                    <div class="single_shop_sidebar">
                        <h3><?= t('availability') ?? 'Наличие' ?></h3>
                        <ul class="sidebar_categories">
                            <li class="<?= !$inStock ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query(array_diff_key($_GET, ['in_stock' => '', 'page' => ''])) ?>">
                                    <?= t('all_products') ?? 'Все товары' ?>
                                </a>
                            </li>
                            <li class="<?= $inStock ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['page' => '']), ['in_stock' => '1'])) ?>">
                                    <?= t('in_stock') ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Brands in this category -->
                    <?php if (!empty($catBrands)): ?>
                    <div class="single_shop_sidebar">
                        <h3><?= t('filter_by_brand') ?></h3>
                        <ul class="sidebar_categories">
                            <li class="<?= !$brandId ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query(array_diff_key($_GET, ['brand' => '', 'page' => ''])) ?>">
                                    <?= t('all_brands') ?? 'Все бренды' ?>
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

                    <!-- Other categories -->
                    <div class="single_shop_sidebar">
                        <h3><?= t('categories') ?></h3>
                        <ul class="sidebar_categories">
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

                </div><!-- /.shop_sidebar_widget -->
            </div><!-- /.col -->

            <!-- ══ MAIN CONTENT ══════════════════════════════════════════════ -->
            <div class="col-lg-9 col-md-8">

                <!-- Category heading -->
                <div style="margin-bottom:20px;">
                    <h1 style="font-size:1.6rem;font-weight:700;color:#222;margin-bottom:4px;"><?= sanitize($catName) ?></h1>
                    <?php if ($category['description']): ?>
                    <p style="color:#666;font-size:0.875rem;"><?= sanitize(truncate($category['description'], 200)) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Toolbar -->
                <div class="shop_toolbar_wrapper">
                    <div class="shop_toolbar_btn">
                        <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])) ?>"
                           data-role="grid_view"
                           class="btn_grid <?= $view === 'grid' ? 'active' : '' ?>">
                            <i class="fa fa-th"></i>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>"
                           data-role="list_view"
                           class="btn_list <?= $view === 'list' ? 'active' : '' ?>">
                            <i class="fa fa-list"></i>
                        </a>
                    </div>
                    <div class="shop_toolbar_result">
                        <p><?= t('showing') ?> <strong><?= count($parts) ?></strong> <?= t('of') ?> <strong><?= $total ?></strong> <?= t('results') ?></p>
                    </div>
                    <div class="toolbar_select">
                        <form method="get" action="" id="sort-form">
                            <input type="hidden" name="slug" value="<?= sanitize($slug) ?>">
                            <?php foreach (array_diff_key($_GET, ['sort' => '', 'page' => '', 'slug' => '']) as $k => $v): ?>
                            <input type="hidden" name="<?= sanitize($k) ?>" value="<?= sanitize($v) ?>">
                            <?php endforeach; ?>
                            <select name="sort" class="nice_Select" onchange="this.form.submit()">
                                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>><?= t('sort_newest') ?? 'Новинки' ?></option>
                                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>><?= t('sort_price_asc') ?? 'Цена: по возрастанию' ?></option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= t('sort_price_desc') ?? 'Цена: по убыванию' ?></option>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Active filters -->
                <?php if ($brandId || $inStock || $priceMin || $priceMax): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;align-items:center;">
                    <span style="font-size:0.8rem;color:#666;"><?= t('filters') ?? 'Фильтры' ?>:</span>
                    <?php if ($currentBrand): ?>
                    <a href="?<?= http_build_query(array_diff_key($_GET, ['brand' => '', 'page' => ''])) ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#333;color:#fff;border-radius:3px;font-size:0.75rem;text-decoration:none;">
                       <?= sanitize($currentBrand['name']) ?> &times;
                    </a>
                    <?php endif; ?>
                    <?php if ($inStock): ?>
                    <a href="?<?= http_build_query(array_diff_key($_GET, ['in_stock' => '', 'page' => ''])) ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#388e3c;color:#fff;border-radius:3px;font-size:0.75rem;text-decoration:none;">
                       <?= t('in_stock') ?> &times;
                    </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($slug) ?>"
                       style="font-size:0.75rem;color:#999;text-decoration:underline;"><?= t('reset_all') ?? 'Сбросить' ?></a>
                </div>
                <?php endif; ?>

                <!-- Products -->
                <?php if (empty($parts)): ?>
                <div style="text-align:center;padding:60px 20px;">
                    <i class="icon-settings" style="font-size:3rem;color:#ddd;display:block;margin-bottom:16px;"></i>
                    <h4 style="color:#666;"><?= t('no_products_found') ?? 'Товары не найдены' ?></h4>
                    <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($slug) ?>"
                       style="display:inline-block;margin-top:16px;background:#d32f2f;color:#fff;padding:8px 24px;border-radius:4px;text-decoration:none;"><?= t('reset_filters') ?? 'Сбросить фильтры' ?></a>
                </div>

                <?php elseif ($view === 'list'): ?>
                <!-- LIST VIEW -->
                <div class="shop_wrapper list_content">
                    <?php foreach ($parts as $part):
                        $stock  = getStockStatus((int)$part['stock']);
                        $imgUrl = productImageUrl($part['images']);
                    ?>
                    <article class="single_product list_single_product">
                        <div class="product_thumb">
                            <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>">
                                <img src="<?= sanitize($imgUrl) ?>"
                                     alt="<?= sanitize($part['name']) ?>"
                                     style="width:120px;height:90px;object-fit:cover;">
                            </a>
                        </div>
                        <div class="product_content list_content" style="flex:1;padding:0 16px;">
                            <p class="manufacture_product">
                                <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                    <?= sanitize($part['brand_name']) ?>
                                </a>
                            </p>
                            <h4 class="product_name">
                                <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>">
                                    <?= sanitize($part['name']) ?>
                                </a>
                            </h4>
                            <p style="font-size:0.78rem;color:#888;margin:2px 0 6px;">
                                <?= t('part_number') ?>: <strong><?= sanitize($part['part_number']) ?></strong>
                            </p>
                            <span style="font-size:0.72rem;padding:3px 8px;border-radius:3px;display:inline-block;background:<?= $stock['class'] === 'success' ? '#e8f5e9' : ($stock['class'] === 'warning' ? '#fff3e0' : '#ffebee') ?>;color:<?= $stock['class'] === 'success' ? '#2e7d32' : ($stock['class'] === 'warning' ? '#e65100' : '#c62828') ?>;">
                                <?= $stock['label'] ?>
                            </span>
                        </div>
                        <div class="action_links" style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;padding-left:16px;">
                            <div class="price_box">
                                <span class="current_price"><?= formatPrice($part['price']) ?></span>
                            </div>
                            <?php if (isLoggedIn()): ?>
                            <button style="background:#d32f2f;color:#fff;border:none;padding:6px 16px;border-radius:4px;cursor:pointer;font-size:0.85rem;"
                                    onclick="addToCart(<?= (int)$part['id'] ?>)">
                                <?= t('add_to_cart') ?>
                            </button>
                            <button style="background:none;border:1px solid #ddd;border-radius:4px;padding:5px 10px;cursor:pointer;color:#666;"
                                    onclick="addToWishlist(<?= (int)$part['id'] ?>)">
                                <i class="icon-heart"></i>
                            </button>
                            <?php else: ?>
                            <a href="<?= APP_URL ?>/auth/login.php"
                               style="background:#333;color:#fff;padding:6px 16px;border-radius:4px;text-decoration:none;font-size:0.85rem;"><?= t('login') ?></a>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>
                <!-- GRID VIEW -->
                <div class="shop_wrapper grid_content">
                    <div class="row">
                        <?php foreach ($parts as $part):
                            $stock  = getStockStatus((int)$part['stock']);
                            $imgUrl = productImageUrl($part['images']);
                        ?>
                        <div class="col-lg-4 col-md-6 col-sm-6 col-6 mb-4">
                            <article class="single_product">
                                <figure>
                                    <div class="product_thumb">
                                        <a class="primary_img" href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>">
                                            <img src="<?= sanitize($imgUrl) ?>"
                                                 alt="<?= sanitize($part['name']) ?>"
                                                 style="height:200px;width:100%;object-fit:cover;">
                                        </a>
                                        <div class="action_links">
                                            <ul>
                                                <?php if (isLoggedIn()): ?>
                                                <li class="wishlist">
                                                    <a href="javascript:void(0)"
                                                       onclick="addToWishlist(<?= (int)$part['id'] ?>)"
                                                       title="<?= t('add_to_wishlist') ?>">
                                                        <i class="icon-heart"></i>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <li class="quick_button">
                                                    <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>"
                                                       title="<?= t('quick_view') ?>">
                                                        <i class="icon-eye"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php if ($part['stock'] <= 0): ?>
                                        <span class="label_product label_sale" style="background:#999;"><?= t('out_of_stock') ?></span>
                                        <?php elseif ($part['stock'] <= 5): ?>
                                        <span class="label_product label_new" style="background:#ff9800;"><?= t('low_stock') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product_content grid_content">
                                        <p class="manufacture_product">
                                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                                <?= sanitize($part['brand_name']) ?>
                                            </a>
                                        </p>
                                        <h4 class="product_name">
                                            <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>">
                                                <?= sanitize(truncate($part['name'], 55)) ?>
                                            </a>
                                        </h4>
                                        <p style="font-size:0.72rem;color:#aaa;margin:2px 0;"><?= sanitize($part['part_number']) ?></p>
                                        <div class="price_box">
                                            <span class="current_price"><?= formatPrice($part['price']) ?></span>
                                        </div>
                                    </div>
                                    <div class="action_links action_links_product">
                                        <ul>
                                            <?php if (isLoggedIn()): ?>
                                            <li class="add_to_cart">
                                                <a href="javascript:void(0)" onclick="addToCart(<?= (int)$part['id'] ?>)">
                                                    <?= t('add_to_cart') ?>
                                                </a>
                                            </li>
                                            <li class="wishlist">
                                                <a href="javascript:void(0)"
                                                   onclick="addToWishlist(<?= (int)$part['id'] ?>)"
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

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="paginatoin-area">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="pagination-box">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                    <li>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                           class="page-link">&lsaquo;</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                    <li class="<?= $p === $page ? 'active' : '' ?>">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                                           class="page-link"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                           class="page-link">&rsaquo;</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /.col main -->
        </div><!-- /.row -->
    </div><!-- /.container -->
</div><!-- /.shop_area -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
