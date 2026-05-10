<?php
require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

// ── GET params ──────────────────────────────────────────────────────────────
$q        = trim($_GET['q'] ?? '');
$catId    = (int)($_GET['cat'] ?? 0);
$brandId  = (int)($_GET['brand'] ?? 0);
$sort     = in_array($_GET['sort'] ?? '', ['price_asc', 'price_desc', 'newest']) ? $_GET['sort'] : 'newest';
$inStock  = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$view     = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
$priceMin = (float)($_GET['price_min'] ?? 0);
$priceMax = (float)($_GET['price_max'] ?? 0);

// ── Build WHERE ─────────────────────────────────────────────────────────────
$where  = ['p.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]  = '(p.name LIKE ? OR p.part_number LIKE ? OR p.description LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($catId) {
    $subStmt = $db->prepare("SELECT id FROM categories WHERE parent_id = ? AND is_active = 1");
    $subStmt->execute([$catId]);
    $subIds   = array_column($subStmt->fetchAll(), 'id');
    $subIds[] = $catId;
    $in       = implode(',', array_fill(0, count($subIds), '?'));
    $where[]  = "p.category_id IN ($in)";
    $params   = array_merge($params, $subIds);
}
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

// ── Count ───────────────────────────────────────────────────────────────────
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

// ── Sort ────────────────────────────────────────────────────────────────────
$orderMap = [
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest'     => 'p.created_at DESC',
];
$orderSQL = $orderMap[$sort] ?? 'p.created_at DESC';

// ── Products ────────────────────────────────────────────────────────────────
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

// ── Sidebar data ────────────────────────────────────────────────────────────
$allCategories = getCategories();
$allBrands     = getBrands();

$currentCat = null;
if ($catId) {
    foreach ($allCategories as $cat) {
        if ((int)$cat['id'] === $catId) { $currentCat = $cat; break; }
    }
}

$currentBrand = null;
if ($brandId) {
    foreach ($allBrands as $b) {
        if ((int)$b['id'] === $brandId) { $currentBrand = $b; break; }
    }
}

$pageTitle = t('shop') . ' — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb(array_merge(
    [['label' => t('home'), 'url' => APP_URL . '/index.php'],
     ['label' => t('shop'), 'url' => APP_URL . '/catalog/index.php']],
    $currentCat
        ? [['label' => tField($currentCat, 'name')]]
        : [['label' => t('shop')]]
)) ?>

<div class="shop_area shop_reverse">
    <div class="container">
        <div class="row">

            <!-- ══ LEFT SIDEBAR ════════════════════════════════════════════ -->
            <div class="col-lg-3 col-md-4">
                <div class="shop_sidebar_widget">

                    <!-- Active search -->
                    <?php if ($q): ?>
                    <div class="single_shop_sidebar">
                        <h3><?= t('search') ?></h3>
                        <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;font-size:0.85rem;">
                            <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= sanitize($q) ?></span>
                            <a href="<?= APP_URL ?>/catalog/index.php" style="color:#999;text-decoration:none;font-size:1.1rem;line-height:1;">&times;</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Price filter -->
                    <div class="single_shop_sidebar">
                        <h3><?= t('filter_by_price') ?></h3>
                        <form method="get" action="">
                            <?php if ($q): ?><input type="hidden" name="q" value="<?= sanitize($q) ?>"><?php endif; ?>
                            <?php if ($catId): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
                            <?php if ($brandId): ?><input type="hidden" name="brand" value="<?= $brandId ?>"><?php endif; ?>
                            <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= sanitize($sort) ?>"><?php endif; ?>
                            <?php if ($inStock): ?><input type="hidden" name="in_stock" value="1"><?php endif; ?>
                            <?php if ($view !== 'grid'): ?><input type="hidden" name="view" value="list"><?php endif; ?>
                            <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
                                <input type="number" name="price_min" class="form-control form-control-sm"
                                       placeholder="<?= t('from') ?>"
                                       value="<?= $priceMin > 0 ? (int)$priceMin : '' ?>" min="0"
                                       style="flex:1;">
                                <span style="color:#aaa;">—</span>
                                <input type="number" name="price_max" class="form-control form-control-sm"
                                       placeholder="<?= t('to') ?>"
                                       value="<?= $priceMax > 0 ? (int)$priceMax : '' ?>" min="0"
                                       style="flex:1;">
                            </div>
                            <button type="submit" class="btn btn-sm" style="background:#d32f2f;color:#fff;border:none;padding:6px 16px;border-radius:4px;width:100%;"><?= t('apply') ?></button>
                            <?php if ($priceMin > 0 || $priceMax > 0): ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['price_min' => '', 'price_max' => ''])) ?>"
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

                    <!-- Category filter -->
                    <div class="single_shop_sidebar">
                        <h3><?= t('filter_by_category') ?></h3>
                        <ul class="sidebar_categories">
                            <li class="<?= !$catId ? 'active_categorie' : '' ?>">
                                <a href="<?= APP_URL ?>/catalog/index.php<?= $q ? '?q=' . urlencode($q) : '' ?>">
                                    <?= t('all_categories') ?>
                                </a>
                            </li>
                            <?php foreach ($allCategories as $cat):
                                if ($cat['parent_id'] !== null) continue;
                                $qArr = array_merge(array_diff_key($_GET, ['page' => '']), ['cat' => $cat['id']]);
                            ?>
                            <li class="<?= $catId === (int)$cat['id'] ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query($qArr) ?>">
                                    <?= sanitize(tField($cat, 'name')) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Brand filter -->
                    <div class="single_shop_sidebar">
                        <h3><?= t('filter_by_brand') ?></h3>
                        <ul class="sidebar_categories">
                            <li class="<?= !$brandId ? 'active_categorie' : '' ?>">
                                <a href="?<?= http_build_query(array_diff_key($_GET, ['brand' => '', 'page' => ''])) ?>">
                                    <?= t('all_brands') ?? 'Все бренды' ?>
                                </a>
                            </li>
                            <?php foreach ($allBrands as $b):
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

                </div><!-- /.shop_sidebar_widget -->
            </div><!-- /.col -->

            <!-- ══ MAIN CONTENT ════════════════════════════════════════════ -->
            <div class="col-lg-9 col-md-8">

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
                            <?php foreach (array_diff_key($_GET, ['sort' => '', 'page' => '']) as $k => $v): ?>
                            <input type="hidden" name="<?= sanitize($k) ?>" value="<?= sanitize($v) ?>">
                            <?php endforeach; ?>
                            <select name="sort" class="nice_Select" onchange="this.form.submit()">
                                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>><?= t('sort_newest') ?? 'Новинки' ?></option>
                                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>><?= t('sort_price_asc') ?? 'Цена: по возрастанию' ?></option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= t('sort_price_desc') ?? 'Цена: по убыванию' ?></option>
                            </select>
                        </form>
                    </div>
                </div><!-- /.shop_toolbar_wrapper -->

                <!-- Active filters -->
                <?php if ($q || $catId || $brandId || $inStock || $priceMin || $priceMax): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;align-items:center;">
                    <span style="font-size:0.8rem;color:#666;"><?= t('filters') ?? 'Фильтры' ?>:</span>
                    <?php if ($q): ?>
                    <a href="?<?= http_build_query(array_diff_key($_GET, ['q' => '', 'page' => ''])) ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#d32f2f;color:#fff;border-radius:3px;font-size:0.75rem;text-decoration:none;">
                       "<?= sanitize(truncate($q, 20)) ?>" &times;
                    </a>
                    <?php endif; ?>
                    <?php if ($currentCat): ?>
                    <a href="?<?= http_build_query(array_diff_key($_GET, ['cat' => '', 'page' => ''])) ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#333;color:#fff;border-radius:3px;font-size:0.75rem;text-decoration:none;">
                       <?= sanitize(tField($currentCat, 'name')) ?> &times;
                    </a>
                    <?php endif; ?>
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
                    <?php if ($priceMin > 0 || $priceMax > 0): ?>
                    <a href="?<?= http_build_query(array_diff_key($_GET, ['price_min' => '', 'price_max' => '', 'page' => ''])) ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#1565c0;color:#fff;border-radius:3px;font-size:0.75rem;text-decoration:none;">
                       <?= $priceMin > 0 ? (int)$priceMin . ' ₽' : '' ?><?= ($priceMin > 0 && $priceMax > 0) ? ' — ' : '' ?><?= $priceMax > 0 ? (int)$priceMax . ' ₽' : '' ?> &times;
                    </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/catalog/index.php"
                       style="font-size:0.75rem;color:#999;text-decoration:underline;"><?= t('reset_all') ?? 'Сбросить всё' ?></a>
                </div>
                <?php endif; ?>

                <!-- Products -->
                <?php if (empty($parts)): ?>
                <div style="text-align:center;padding:60px 20px;">
                    <i class="icon-settings" style="font-size:3rem;color:#ddd;display:block;margin-bottom:16px;"></i>
                    <h4 style="color:#666;"><?= t('no_products_found') ?? 'Товары не найдены' ?></h4>
                    <a href="<?= APP_URL ?>/catalog/index.php" class="btn" style="margin-top:16px;background:#d32f2f;color:#fff;padding:8px 24px;border-radius:4px;text-decoration:none;"><?= t('reset_filters') ?? 'Сбросить фильтры' ?></a>
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
                                <?php if ($part['category_name']): ?>
                                &nbsp;&middot;&nbsp; <?= sanitize($part['category_name']) ?>
                                <?php endif; ?>
                            </p>
                            <span class="badge" style="font-size:0.72rem;padding:3px 8px;border-radius:3px;background:<?= $stock['class'] === 'success' ? '#e8f5e9' : ($stock['class'] === 'warning' ? '#fff3e0' : '#ffebee') ?>;color:<?= $stock['class'] === 'success' ? '#2e7d32' : ($stock['class'] === 'warning' ? '#e65100' : '#c62828') ?>;">
                                <?= $stock['label'] ?>
                            </span>
                        </div>
                        <div class="action_links" style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;padding-left:16px;">
                            <div class="price_box">
                                <span class="current_price"><?= formatPrice($part['price']) ?></span>
                            </div>
                            <?php if (isLoggedIn()): ?>
                            <button class="btn btn-sm" style="background:#d32f2f;color:#fff;border:none;padding:6px 16px;border-radius:4px;cursor:pointer;"
                                    onclick="addToCart(<?= (int)$part['id'] ?>)">
                                <?= t('add_to_cart') ?>
                            </button>
                            <button style="background:none;border:1px solid #ddd;border-radius:4px;padding:5px 10px;cursor:pointer;color:#666;"
                                    onclick="addToWishlist(<?= (int)$part['id'] ?>)" title="<?= t('add_to_wishlist') ?>">
                                <i class="icon-heart"></i>
                            </button>
                            <?php else: ?>
                            <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-sm" style="background:#333;color:#fff;border:none;padding:6px 16px;border-radius:4px;text-decoration:none;"><?= t('login') ?></a>
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
                                        <span class="label_product label_sale" style="background:#999;">
                                            <?= t('out_of_stock') ?>
                                        </span>
                                        <?php elseif ($part['stock'] <= 5): ?>
                                        <span class="label_product label_new" style="background:#ff9800;">
                                            <?= t('low_stock') ?>
                                        </span>
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
                                        <p style="font-size:0.72rem;color:#aaa;margin:2px 0;">
                                            <?= sanitize($part['part_number']) ?>
                                        </p>
                                        <div class="price_box">
                                            <span class="current_price"><?= formatPrice($part['price']) ?></span>
                                        </div>
                                    </div>
                                    <div class="action_links action_links_product">
                                        <ul>
                                            <?php if (isLoggedIn()): ?>
                                            <li class="add_to_cart">
                                                <a href="javascript:void(0)"
                                                   onclick="addToCart(<?= (int)$part['id'] ?>)">
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
                                                <a href="<?= APP_URL ?>/auth/login.php">
                                                    <?= t('login') ?>
                                                </a>
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
                                           class="page-link">
                                            &lsaquo;
                                        </a>
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
                                           class="page-link">
                                            &rsaquo;
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
        </div><!-- /.row -->
    </div><!-- /.container -->
</div><!-- /.shop_area -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
