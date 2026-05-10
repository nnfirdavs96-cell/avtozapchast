<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = t('home') . ' — ' . getSetting('site_name', t('site_name'));

$db = getDB();

$catStmt = $db->query("SELECT c.*, ci.name AS name_en, ci2.name AS name_tg
    FROM categories c
    LEFT JOIN categories_i18n ci  ON ci.category_id=c.id AND ci.lang='en'
    LEFT JOIN categories_i18n ci2 ON ci2.category_id=c.id AND ci2.lang='tg'
    WHERE c.is_active=1 AND c.parent_id IS NULL ORDER BY c.sort_order LIMIT 6");
$featCategories = $catStmt->fetchAll();

$brandStmt = $db->query("SELECT * FROM brands WHERE is_active=1 ORDER BY name LIMIT 8");
$featBrands = $brandStmt->fetchAll();

$partsStmt = $db->query("SELECT p.*, b.name AS brand_name, c.name AS category_name
    FROM parts p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN categories c ON c.id=p.category_id
    WHERE p.is_active=1 ORDER BY p.created_at DESC LIMIT 8");
$featParts = $partsStmt->fetchAll();

$bestStmt = $db->query("SELECT p.*, b.name AS brand_name FROM parts p
    LEFT JOIN brands b ON b.id=p.brand_id WHERE p.is_active=1 ORDER BY p.stock DESC LIMIT 4");
$bestParts = $bestStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<meta name="csrf" content="<?php echo generateCsrfToken(); ?>">

<div class="slider_area">
  <div class="single_slider slider_bg_color" style="background:#f5f5f5;padding:80px 0">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-7 col-md-6">
          <div class="slider_content">
            <div class="slider_text">
              <div class="slider_text_inner">
                <p><?php echo t('tagline'); ?></p>
                <h1><?php echo getSetting('site_name', t('site_name')); ?></h1>
                <h2><?php echo t('featured_products'); ?> &amp; <?php echo t('new_arrivals'); ?></h2>
              </div>
            </div>
            <div class="slider_btn">
              <a class="button" href="<?php echo APP_URL; ?>/catalog/index.php"><?php echo t('shop'); ?></a>
              <a class="button button_2" href="<?php echo APP_URL; ?>/pages/about.php"><?php echo t('about'); ?></a>
            </div>
          </div>
        </div>
        <div class="col-lg-5 col-md-6 text-center">
          <img src="<?php echo APP_URL; ?>/assets/img/bg/banner1.jpg" alt="" style="max-width:100%;border-radius:12px">
        </div>
      </div>
    </div>
  </div>
</div>

<div class="categories_section section_padding" style="padding:50px 0">
  <div class="container">
    <div class="section_title"><h2><?php echo t('all_categories'); ?></h2></div>
    <div class="row">
      <?php foreach ($featCategories as $cat): ?>
      <div class="col-lg-2 col-md-4 col-6 mb-4 text-center">
        <a href="<?php echo APP_URL; ?>/catalog/category.php?slug=<?php echo urlencode($cat['slug']); ?>" style="text-decoration:none">
          <div style="width:80px;height:80px;background:#d32f2f;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:#fff;font-size:1.8rem">
            <i class="icon-settings"></i>
          </div>
          <p style="font-weight:600;color:#333;font-size:0.875rem;margin:0"><?php echo sanitize(tField($cat,'name')); ?></p>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="product_area" style="background:#f9f9f9;padding:50px 0">
  <div class="container">
    <div class="section_title"><h2><?php echo t('featured_products'); ?></h2></div>
    <div class="row shop_wrapper">
      <?php foreach ($featParts as $part):
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
              <div class="quick_button">
                <a href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$part['id']; ?>" title="<?php echo t('quick_view'); ?>"><i class="icon-eye"></i></a>
              </div>
            </div>
            <div class="product_content grid_content">
              <div class="product_content_inner">
                <p class="manufacture_product"><a href="<?php echo APP_URL; ?>/catalog/index.php?brand=<?php echo (int)$part['brand_id']; ?>"><?php echo sanitize($part['brand_name']); ?></a></p>
                <h4 class="product_name"><a href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$part['id']; ?>"><?php echo sanitize(truncate($part['name'],55)); ?></a></h4>
                <p style="font-size:0.75rem;color:#888;margin:2px 0"><?php echo sanitize($part['part_number']); ?></p>
                <div class="price_box"><span class="current_price"><?php echo formatPrice($part['price']); ?></span></div>
                <p class="stock-<?php echo $stock['class']; ?>" style="font-size:0.75rem;margin:4px 0 0"><?php echo $stock['label']; ?></p>
              </div>
              <div class="action_links">
                <ul>
                  <li class="add_to_cart"><a href="javascript:void(0)" onclick="addToCart(<?php echo (int)$part['id']; ?>)"><?php echo t('add_to_cart'); ?></a></li>
                  <li class="wishlist"><a href="javascript:void(0)" onclick="addToWishlist(<?php echo (int)$part['id']; ?>)" title="<?php echo t('add_to_wishlist'); ?>"><i class="icon-heart"></i></a></li>
                </ul>
              </div>
            </div>
          </figure>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
      <a href="<?php echo APP_URL; ?>/catalog/index.php" class="button"><?php echo t('shop'); ?> &rarr;</a>
    </div>
  </div>
</div>

<div class="banner_section" style="padding:40px 0">
  <div class="container">
    <div class="row">
      <div class="col-lg-6 mb-3">
        <div style="position:relative;overflow:hidden;border-radius:8px">
          <img src="<?php echo APP_URL; ?>/assets/img/bg/banner2.jpg" alt="" style="width:100%;height:220px;object-fit:cover">
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;padding:30px;background:rgba(0,0,0,0.4)">
            <p style="color:rgba(255,255,255,0.85);margin:0 0 6px;font-size:0.85rem"><?php echo t('new_arrivals'); ?></p>
            <h3 style="color:#fff;font-size:1.4rem;margin:0 0 12px"><?php echo t('featured_products'); ?></h3>
            <a href="<?php echo APP_URL; ?>/catalog/index.php" class="button" style="width:fit-content"><?php echo t('shop'); ?></a>
          </div>
        </div>
      </div>
      <div class="col-lg-6 mb-3">
        <div style="position:relative;overflow:hidden;border-radius:8px">
          <img src="<?php echo APP_URL; ?>/assets/img/bg/banner3.jpg" alt="" style="width:100%;height:220px;object-fit:cover">
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;padding:30px;background:rgba(0,0,0,0.4)">
            <p style="color:rgba(255,255,255,0.85);margin:0 0 6px;font-size:0.85rem"><?php echo t('best_sellers'); ?></p>
            <h3 style="color:#fff;font-size:1.4rem;margin:0 0 12px"><?php echo t('warehouse_stock'); ?></h3>
            <a href="<?php echo APP_URL; ?>/catalog/index.php?in_stock=1" class="button" style="width:fit-content"><?php echo t('in_stock'); ?></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="product_area" style="padding:50px 0">
  <div class="container">
    <div class="section_title"><h2><?php echo t('best_sellers'); ?></h2></div>
    <div class="row">
      <?php foreach ($bestParts as $part): $img = productImageUrl($part['images']); ?>
      <div class="col-lg-3 col-md-6 mb-4">
        <article class="single_product">
          <figure>
            <div class="product_thumb">
              <a class="primary_img" href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$part['id']; ?>">
                <img src="<?php echo $img; ?>" alt="<?php echo sanitize($part['name']); ?>" style="height:200px;object-fit:contain;width:100%">
              </a>
            </div>
            <div class="product_content grid_content">
              <div class="product_content_inner">
                <p class="manufacture_product"><a href="#"><?php echo sanitize($part['brand_name']); ?></a></p>
                <h4 class="product_name"><a href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$part['id']; ?>"><?php echo sanitize(truncate($part['name'],55)); ?></a></h4>
                <div class="price_box"><span class="current_price"><?php echo formatPrice($part['price']); ?></span></div>
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
  </div>
</div>

<div style="background:#f9f9f9;padding:40px 0">
  <div class="container">
    <div class="section_title"><h2><?php echo t('brand'); ?></h2></div>
    <div class="row">
      <?php foreach ($featBrands as $brand): ?>
      <div class="col-lg-3 col-md-4 col-6 mb-3">
        <a href="<?php echo APP_URL; ?>/catalog/index.php?brand=<?php echo (int)$brand['id']; ?>" style="text-decoration:none">
          <div style="padding:20px;background:#fff;border-radius:8px;border:1px solid #eee;text-align:center">
            <strong style="color:#333;font-size:1rem"><?php echo sanitize($brand['name']); ?></strong>
            <p style="color:#aaa;font-size:0.75rem;margin:4px 0 0"><?php echo sanitize($brand['country']); ?></p>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
