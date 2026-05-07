<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = 'Каталог запчастей';
$db = getDB();

// ── Filters from GET ──────────────────────────────────────────
$catSlug   = trim($_GET['category'] ?? '');
$brandId   = (int)($_GET['brand'] ?? 0);
$priceMin  = (float)($_GET['price_min'] ?? 0);
$priceMax  = (float)($_GET['price_max'] ?? 0);
$sort      = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','name_asc','newest']) ? $_GET['sort'] : 'newest';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 12;
$view      = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';

// Category by slug
$currentCat = null;
if ($catSlug) {
    $catStmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $catStmt->execute([$catSlug]);
    $currentCat = $catStmt->fetch();
}

// Build query
$where  = ['p.is_active = 1'];
$params = [];

if ($currentCat) {
    // Include subcategories
    $subStmt = $db->prepare("SELECT id FROM categories WHERE parent_id = ? AND is_active = 1");
    $subStmt->execute([$currentCat['id']]);
    $subIds = array_column($subStmt->fetchAll(), 'id');
    $subIds[] = $currentCat['id'];
    $in = implode(',', array_fill(0, count($subIds), '?'));
    $where[]  = "p.category_id IN ($in)";
    $params   = array_merge($params, $subIds);
}
if ($brandId) {
    $where[]  = 'p.brand_id = ?';
    $params[] = $brandId;
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

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM parts p $whereSQL");
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Sort
$orderMap = [
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc'   => 'p.name ASC',
    'newest'     => 'p.created_at DESC',
];
$orderSQL = $orderMap[$sort] ?? 'p.created_at DESC';

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

// Sidebar data
$allCategories = getCategories();
$allBrands     = getBrands();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.catalog-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 24px;
  max-width: 1440px;
  margin: 28px auto;
  padding: 0 24px;
}
@media (max-width: 900px) { .catalog-layout { grid-template-columns: 1fr; } }
.catalog-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.catalog-toolbar select { width: auto; }
.view-toggle { display: flex; gap: 4px; }
.view-btn {
  width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}
.view-btn.active, .view-btn:hover { border-color: var(--accent); color: var(--accent); }
.parts-list { display: flex; flex-direction: column; gap: 12px; }
.part-list-item {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: border-color 0.2s;
}
.part-list-item:hover { border-color: rgba(255,107,53,0.4); }
.part-list-thumb {
  width: 80px;
  height: 60px;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--text-muted);
  opacity: 0.4;
}
.part-list-info { flex: 1; min-width: 0; }
.part-list-num { font-family: var(--font-mono); font-size: 0.72rem; color: var(--accent); margin-bottom: 4px; }
.part-list-name { font-size: 0.9rem; font-weight: 500; color: var(--text-primary); margin-bottom: 4px; }
.part-list-brand { font-family: var(--font-mono); font-size: 0.65rem; color: var(--text-muted); }
.part-list-price { font-family: var(--font-mono); font-size: 1.1rem; color: var(--accent); font-weight: 700; white-space: nowrap; }
</style>

<div class="catalog-layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <form method="get" action="" id="filter-form">
      <?php if ($view !== 'grid'): ?><input type="hidden" name="view" value="<?= sanitize($view) ?>"><?php endif; ?>

      <!-- Categories -->
      <div class="filter-section">
        <div class="filter-title">Категории</div>
        <ul class="filter-list">
          <li>
            <a href="<?= APP_URL ?>/catalog/index.php" class="<?= !$catSlug ? 'active' : '' ?>">
              Все категории
            </a>
          </li>
          <?php foreach ($allCategories as $cat): if ($cat['parent_id'] !== null) continue; ?>
          <li>
            <a href="?category=<?= sanitize($cat['slug']) ?><?= $brandId ? '&brand='.$brandId : '' ?>"
               class="<?= $catSlug === $cat['slug'] ? 'active' : '' ?>">
              <?= sanitize($cat['name']) ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Brands -->
      <div class="filter-section">
        <div class="filter-title">Бренды</div>
        <ul class="filter-list">
          <li>
            <a href="?<?= $catSlug ? 'category='.$catSlug : '' ?>" class="<?= !$brandId ? 'active' : '' ?>">Все бренды</a>
          </li>
          <?php foreach ($allBrands as $b): ?>
          <li>
            <a href="?<?= $catSlug ? 'category='.$catSlug.'&' : '' ?>brand=<?= $b['id'] ?>"
               class="<?= $brandId === (int)$b['id'] ? 'active' : '' ?>">
              <?= sanitize($b['name']) ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Price range -->
      <div class="filter-section">
        <div class="filter-title">Цена (₽)</div>
        <div class="price-inputs">
          <input type="number" id="price-min" name="price_min" class="form-input" placeholder="от"
                 value="<?= $priceMin > 0 ? $priceMin : '' ?>" min="0">
          <span class="price-sep">—</span>
          <input type="number" id="price-max" name="price_max" class="form-input" placeholder="до"
                 value="<?= $priceMax > 0 ? $priceMax : '' ?>" min="0">
        </div>
        <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= sanitize($catSlug) ?>"><?php endif; ?>
        <?php if ($brandId): ?><input type="hidden" name="brand" value="<?= $brandId ?>"><?php endif; ?>
        <input type="hidden" name="sort" value="<?= sanitize($sort) ?>">
        <button type="submit" class="btn btn-primary btn-sm btn-block" style="margin-top:12px;">Применить</button>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-outline btn-sm btn-block" style="margin-top:6px;">Сбросить</a>
      </div>
    </form>
  </aside>

  <!-- Main area -->
  <div>
    <!-- Heading -->
    <div class="flex-between mb-16" style="flex-wrap:wrap;gap:8px;">
      <div>
        <h1 class="section-heading" style="font-size:1.8rem;">
          <?= $currentCat ? sanitize($currentCat['name']) : 'Все запчасти' ?>
        </h1>
        <span class="label-mono"><?= $total ?> позиций</span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="catalog-toolbar">
      <select class="form-select" name="sort" style="width:auto;" data-auto-submit
              onchange="document.getElementById('sort-form').submit()">
      </select>
      <form id="sort-form" method="get" action="">
        <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= sanitize($catSlug) ?>"><?php endif; ?>
        <?php if ($brandId): ?><input type="hidden" name="brand" value="<?= $brandId ?>"><?php endif; ?>
        <?php if ($priceMin): ?><input type="hidden" name="price_min" value="<?= $priceMin ?>"><?php endif; ?>
        <?php if ($priceMax): ?><input type="hidden" name="price_max" value="<?= $priceMax ?>"><?php endif; ?>
        <input type="hidden" name="view" value="<?= sanitize($view) ?>">
        <select name="sort" class="form-select" onchange="this.form.submit()">
          <option value="newest"     <?= $sort==='newest'     ?'selected':'' ?>>Новинки</option>
          <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':'' ?>>Цена ↑</option>
          <option value="price_desc" <?= $sort==='price_desc' ?'selected':'' ?>>Цена ↓</option>
          <option value="name_asc"   <?= $sort==='name_asc'   ?'selected':'' ?>>Название А-Я</option>
        </select>
      </form>
      <!-- View toggle -->
      <div class="view-toggle" style="margin-left:auto;">
        <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>"
           class="view-btn <?= $view==='grid'?'active':'' ?>" title="Сетка">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'list'])) ?>"
           class="view-btn <?= $view==='list'?'active':'' ?>" title="Список">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </a>
      </div>
    </div>

    <!-- Parts -->
    <?php if (empty($parts)): ?>
      <div class="no-data">
        <div class="no-data-icon">⚙</div>
        <p>По вашему запросу ничего не найдено.</p>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-outline btn-sm" style="margin-top:16px;">Сбросить фильтры</a>
      </div>
    <?php elseif ($view === 'list'): ?>
      <div class="parts-list">
        <?php foreach ($parts as $part):
          $stock = getStockStatus((int)$part['stock']);
          $img   = getPartFirstImage($part['images'] ?? null);
        ?>
        <div class="part-list-item">
          <div class="part-list-thumb">
            <?php if ($img): ?>
              <img src="<?= sanitize($img) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">
            <?php else: ?>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="2" y="7" width="20" height="10" rx="1"/><path d="M8 7V5M16 7V5"/></svg>
            <?php endif; ?>
          </div>
          <div class="part-list-info">
            <div class="part-list-num"><?= sanitize($part['part_number']) ?></div>
            <div class="part-list-name">
              <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $part['id'] ?>" style="color:inherit;text-decoration:none;">
                <?= sanitize($part['name']) ?>
              </a>
            </div>
            <div class="part-list-brand"><?= sanitize($part['brand_name']) ?> · <?= sanitize($part['category_name']) ?></div>
          </div>
          <div style="display:flex;align-items:center;gap:16px;flex-shrink:0;">
            <span class="badge badge-<?= $stock['class'] ?>"><?= $stock['label'] ?></span>
            <span class="part-list-price"><?= formatPrice($part['price']) ?></span>
            <?php if (isLoggedIn()): ?>
              <button class="btn btn-primary btn-sm" data-add-cart="<?= $part['id'] ?>">В корзину</button>
            <?php else: ?>
              <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-sm">Войти</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="grid-4">
        <?php foreach ($parts as $part):
          $stock = getStockStatus((int)$part['stock']);
          $img   = getPartFirstImage($part['images'] ?? null);
        ?>
        <div class="part-card">
          <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $part['id'] ?>" style="display:block;text-decoration:none;">
            <div class="part-card-img">
              <?php if ($img): ?>
                <img src="<?= sanitize($img) ?>" alt="<?= sanitize($part['name']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
              <?php else: ?>
              <div class="part-card-img-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="0.8"><rect x="2" y="7" width="20" height="10" rx="1"/><path d="M8 7V5M16 7V5M4 12h2M18 12h2"/></svg>
              </div>
              <?php endif; ?>
              <span class="part-number-badge"><?= sanitize($part['part_number']) ?></span>
            </div>
          </a>
          <div class="part-card-body">
            <div class="part-card-brand"><?= sanitize($part['brand_name']) ?></div>
            <div class="part-card-name">
              <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $part['id'] ?>" style="color:inherit;text-decoration:none;">
                <?= sanitize(truncate($part['name'], 55)) ?>
              </a>
            </div>
            <div class="part-card-meta">
              <span class="part-card-price"><?= formatPrice($part['price']) ?></span>
              <span class="badge badge-<?= $stock['class'] ?>"><?= $stock['label'] ?></span>
            </div>
          </div>
          <div class="part-card-footer">
            <?php if (isLoggedIn()): ?>
              <button class="btn btn-primary btn-sm btn-block" data-add-cart="<?= $part['id'] ?>">В корзину</button>
            <?php else: ?>
              <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-sm btn-block">Войдите для заказа</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
      $qParams = $_GET;
      if ($page > 1):
        $qParams['page'] = $page - 1;
      ?>
      <a href="?<?= http_build_query($qParams) ?>" class="page-link">‹</a>
      <?php endif; ?>

      <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++):
        $qParams['page'] = $p;
      ?>
      <a href="?<?= http_build_query($qParams) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>

      <?php if ($page < $totalPages):
        $qParams['page'] = $page + 1;
      ?>
      <a href="?<?= http_build_query($qParams) ?>" class="page-link">›</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
