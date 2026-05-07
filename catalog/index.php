<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/product_card.php';

$db = getDB();

// Filters
$categorySlug = $_GET['category'] ?? '';
$brandId      = isset($_GET['brand'])    ? (int)$_GET['brand']    : 0;
$makeId       = isset($_GET['make_id'])  ? (int)$_GET['make_id']  : 0;
$modelId      = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
$year         = isset($_GET['year'])     ? (int)$_GET['year']     : 0;
$priceMin     = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$priceMax     = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 0;
$q            = trim((string)($_GET['q'] ?? ''));
$sort         = $_GET['sort'] ?? 'newest';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 12;
$offset       = ($page - 1) * $perPage;

$where  = ['p.is_active = 1'];
$params = [];

if ($categorySlug) {
    $where[] = 'c.slug = ?';
    $params[] = $categorySlug;
}
if ($brandId) {
    $where[] = 'p.brand_id = ?';
    $params[] = $brandId;
}
if ($modelId) {
    $where[] = 'EXISTS (SELECT 1 FROM part_compatibility pc WHERE pc.part_id=p.id AND pc.model_id=?)';
    $params[] = $modelId;
} elseif ($makeId) {
    $where[] = 'EXISTS (SELECT 1 FROM part_compatibility pc JOIN car_models cm ON cm.id=pc.model_id WHERE pc.part_id=p.id AND cm.make_id=?)';
    $params[] = $makeId;
}
if ($priceMin > 0) { $where[] = 'p.price >= ?'; $params[] = $priceMin; }
if ($priceMax > 0) { $where[] = 'p.price <= ?'; $params[] = $priceMax; }
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.part_number LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$orderBy = match ($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name'       => 'p.name ASC',
    default      => 'p.created_at DESC',
};

$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) FROM parts p
             LEFT JOIN brands b ON b.id=p.brand_id
             LEFT JOIN categories c ON c.id=p.category_id
             WHERE {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

$sql = "SELECT p.*, b.name AS brand_name, c.name AS category_name, c.slug AS category_slug
        FROM parts p
        LEFT JOIN brands b ON b.id=p.brand_id
        LEFT JOIN categories c ON c.id=p.category_id
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

$categories = getCategories();
$brands     = getBrands();
$makes      = getCarMakes();
$models     = $makeId ? getCarModels($makeId) : [];

$pageTitle = $categorySlug
    ? array_reduce($categories, fn($a,$c)=> $c['slug']===$categorySlug ? $c['name'] : $a, t('catalog'))
    : t('catalog');

require_once __DIR__ . '/../includes/header.php';
?>

<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<div class="page-head">
  <div class="container">
    <h1><?= sanitize($pageTitle) ?></h1>
    <nav class="breadcrumb">
      <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a>
      <span class="sep">/</span>
      <span class="current"><?= sanitize($pageTitle) ?></span>
    </nav>
  </div>
</div>

<section class="section">
  <div class="container catalog-layout">

    <aside class="sidebar">
      <form method="get" action="">
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= sanitize($q) ?>"><?php endif; ?>

        <div class="filter-block">
          <h4>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            <?= t('category') ?>
          </h4>
          <ul class="filter-list">
            <li><a href="<?= APP_URL ?>/catalog/index.php" class="<?= !$categorySlug ? 'active' : '' ?>"><?= t('all_categories') ?></a></li>
            <?php foreach ($categories as $c): if ($c['parent_id']!==null) continue;
              $cnt = (int)$db->query("SELECT COUNT(*) FROM parts p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND c.slug=" . $db->quote($c['slug']))->fetchColumn();
            ?>
              <li><a href="?category=<?= sanitize($c['slug']) ?>" class="<?= $categorySlug===$c['slug'] ? 'active' : '' ?>">
                <?= sanitize(tField('category',(int)$c['id'],'name',$c['name'])) ?>
                <span class="count"><?= $cnt ?></span>
              </a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="filter-block">
          <h4>🚗 <?= t('find_by_car') ?></h4>
          <select name="make_id" id="vin-make" class="form-select" onchange="this.form.submit()">
            <option value=""><?= t('select_make') ?></option>
            <?php foreach ($makes as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= $makeId===(int)$m['id']?'selected':'' ?>><?= sanitize($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($makeId): ?>
            <select name="model_id" id="vin-model" class="form-select mt-8" onchange="this.form.submit()">
              <option value=""><?= t('select_model') ?></option>
              <?php foreach ($models as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= $modelId===(int)$m['id']?'selected':'' ?>><?= sanitize($m['name']) ?> (<?= $m['year_from'] ?>–<?= $m['year_to'] ?>)</option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <div class="filter-block">
          <h4><?= t('brand') ?></h4>
          <ul class="filter-list">
            <li><a href="?<?= http_build_query(array_diff_key($_GET, ['brand'=>1, 'page'=>1])) ?>" class="<?= !$brandId ? 'active' : '' ?>">— все —</a></li>
            <?php foreach ($brands as $b): ?>
              <li><a href="?<?= http_build_query(array_merge($_GET, ['brand'=>$b['id'], 'page'=>1])) ?>" class="<?= $brandId===(int)$b['id'] ? 'active' : '' ?>">
                <?= sanitize($b['name']) ?>
              </a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="filter-block">
          <h4><?= t('price_range') ?></h4>
          <div class="price-range">
            <input type="number" name="price_min" value="<?= $priceMin ?: '' ?>" placeholder="0" class="form-input">
            <span>—</span>
            <input type="number" name="price_max" value="<?= $priceMax ?: '' ?>" placeholder="∞" class="form-input">
          </div>
          <button type="submit" class="btn btn-outline btn-sm btn-block mt-16"><?= t('apply') ?></button>
          <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-link btn-sm mt-8"><?= t('reset') ?></a>
        </div>
      </form>
    </aside>

    <div>
      <div class="toolbar">
        <span class="count">Найдено <strong><?= $totalItems ?></strong> товаров</span>
        <form method="get" style="display:flex;gap:8px;align-items:center" id="sort-form">
          <?php foreach ($_GET as $k=>$v): if ($k==='sort'||$k==='page') continue; ?>
            <input type="hidden" name="<?= sanitize($k) ?>" value="<?= sanitize((string)$v) ?>">
          <?php endforeach; ?>
          <label style="font-size:0.85rem;color:var(--muted)"><?= t('sort_by') ?>:</label>
          <select name="sort" onchange="this.form.submit()">
            <option value="newest"     <?= $sort==='newest'?'selected':'' ?>><?= t('sort_newest') ?></option>
            <option value="price_asc"  <?= $sort==='price_asc'?'selected':'' ?>><?= t('sort_price_asc') ?></option>
            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>><?= t('sort_price_desc') ?></option>
            <option value="name"       <?= $sort==='name'?'selected':'' ?>><?= t('sort_name') ?></option>
          </select>
        </form>
      </div>

      <?php if (empty($parts)): ?>
        <div class="empty-state">
          <div class="icon"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div>
          <h3><?= t('no_products_found') ?></h3>
          <p>Попробуйте изменить фильтры или поискать по другому запросу.</p>
          <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary"><?= t('reset') ?></a>
        </div>
      <?php else: ?>
        <div class="products-grid">
          <?php foreach ($parts as $p) renderProductCard($p); ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php
          $base = $_GET; unset($base['page']);
          $qsbase = http_build_query($base);
          $qsbase = $qsbase ? $qsbase . '&' : '';
          $prev = max(1, $page - 1);
          $next = min($totalPages, $page + 1);
          ?>
          <a href="?<?= $qsbase ?>page=<?= $prev ?>" class="page-link <?= $page<=1?'disabled':'' ?>">←</a>
          <?php for ($i=1; $i<=$totalPages; $i++):
            if ($i!==1 && $i!==$totalPages && abs($i-$page)>2) {
              if ($i===2 || $i===$totalPages-1) echo '<span class="page-link disabled">…</span>';
              continue;
            }
          ?>
            <a href="?<?= $qsbase ?>page=<?= $i ?>" class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a href="?<?= $qsbase ?>page=<?= $next ?>" class="page-link <?= $page>=$totalPages?'disabled':'' ?>">→</a>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
