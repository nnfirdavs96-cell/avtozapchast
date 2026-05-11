<?php
require_once dirname(__DIR__) . '/config/config.php';

$_getStr = static fn($k, $d = '') => is_scalar($_GET[$k] ?? null) ? (string)$_GET[$k] : $d;
$q       = trim($_getStr('q'));
$catId   = (int)$_getStr('cat', '0');
$page    = max(1, (int)$_getStr('page', '1'));
$perPage = 12;

$pageTitle = t('search') . ($q ? ': ' . $q : '') . ' — ' . getSetting('site_name');

$db     = getDB();
$where  = ['p.is_active=1'];
$params = [];

if ($q !== '') {
    $where[]  = '(p.name LIKE ? OR p.part_number LIKE ? OR b.name LIKE ? OR p.description LIKE ?)';
    $like     = '%'.$q.'%';
    $params   = array_merge($params, [$like,$like,$like,$like]);
}
if ($catId) { $where[] = 'p.category_id=?'; $params[] = $catId; }

$whereSql = 'WHERE ' . implode(' AND ', $where);
$join     = "FROM parts p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN categories c ON c.id=p.category_id";

$total  = (int)$db->prepare("SELECT COUNT(*) $join $whereSql")->execute($params) ? 0 : 0;
$cStmt  = $db->prepare("SELECT COUNT(*) $join $whereSql"); $cStmt->execute($params); $total = (int)$cStmt->fetchColumn();
$pages  = max(1, ceil($total / $perPage));
$offset = ($page-1) * $perPage;

$dStmt  = $db->prepare("SELECT p.*, b.name AS brand_name, c.name AS category_name $join $whereSql ORDER BY p.name LIMIT $perPage OFFSET $offset");
$dStmt->execute($params);
$parts  = $dStmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?php echo generateCsrfToken(); ?>">
<?php echo breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('search')]]); ?>

<div class="shop_area" style="padding:50px 0">
  <div class="container">
    <div class="section_title">
      <h2><?php echo t('search'); ?><?php if ($q): ?>: <em>"<?php echo sanitize($q); ?>"</em><?php endif; ?></h2>
      <p style="color:#888;font-size:0.9rem"><?php echo sprintf('%s %d %s', t('showing'), $total, t('results')); ?></p>
    </div>

    <!-- Search form -->
    <div style="background:#f9f9f9;padding:20px;border-radius:8px;margin-bottom:30px">
      <form action="" method="GET" style="display:flex;gap:12px;flex-wrap:wrap">
        <input type="text" name="q" value="<?php echo sanitize($q); ?>" placeholder="<?php echo t('search_placeholder'); ?>" style="flex:1;min-width:200px;padding:10px 14px;border:1px solid #ddd;border-radius:6px;font-size:0.9rem">
        <select name="cat" style="padding:10px 14px;border:1px solid #ddd;border-radius:6px">
          <option value=""><?php echo t('all_categories'); ?></option>
          <?php foreach (getCategories() as $cat): ?>
          <option value="<?php echo (int)$cat['id']; ?>" <?php echo $catId==$cat['id']?'selected':''; ?>><?php echo sanitize(tField($cat,'name')); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="button"><?php echo t('search'); ?></button>
      </form>
    </div>

    <?php if (empty($parts)): ?>
    <div style="text-align:center;padding:60px">
      <i class="icon-search" style="font-size:4rem;color:#eee;display:block;margin-bottom:20px"></i>
      <p style="color:#aaa"><?php echo t('no_records'); ?></p>
      <a href="<?php echo APP_URL; ?>/catalog/index.php" class="button" style="margin-top:16px"><?php echo t('shop'); ?></a>
    </div>
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
              <a class="primary_img" href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$part['id']; ?>">
                <img src="<?php echo $img; ?>" alt="<?php echo sanitize($part['name']); ?>" style="height:200px;object-fit:contain;width:100%">
              </a>
              <div class="quick_button"><a href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$part['id']; ?>"><i class="icon-eye"></i></a></div>
            </div>
            <div class="product_content grid_content">
              <div class="product_content_inner">
                <p class="manufacture_product"><a href="#"><?php echo sanitize($part['brand_name']); ?></a></p>
                <h4 class="product_name"><a href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$part['id']; ?>"><?php echo sanitize(truncate($part['name'],55)); ?></a></h4>
                <p style="font-size:0.75rem;color:#888;margin:2px 0"><?php echo sanitize($part['part_number']); ?></p>
                <div class="price_box"><span class="current_price"><?php echo formatPrice($part['price']); ?></span></div>
                <p class="stock-<?php echo $stock['class']; ?>" style="font-size:0.75rem"><?php echo $stock['label']; ?></p>
              </div>
              <div class="action_links">
                <ul>
                  <li class="add_to_cart"><a href="javascript:void(0)" onclick="addToCart(<?php echo (int)$part['id']; ?>)"><?php echo t('add_to_cart'); ?></a></li>
                  <li class="wishlist"><a href="javascript:void(0)" onclick="addToWishlist(<?php echo (int)$part['id']; ?>)"><i class="icon-heart"></i></a></li>
                </ul>
              </div>
            </div>
          </figure>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <?php echo paginationHtml(['pages'=>$pages,'current'=>$page], APP_URL.'/search/index.php?'.http_build_query(array_filter(['q'=>$q,'cat'=>$catId?$catId:'']))); ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
