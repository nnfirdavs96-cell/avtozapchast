<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = t('home') . ' — ' . getSetting('site_name', t('site_name'));

$db = getDB();

$categories = $db->query("SELECT * FROM categories WHERE is_active=1 AND parent_id IS NULL ORDER BY sort_order LIMIT 7")->fetchAll();

$featParts = $db->query("SELECT p.*, b.name AS brand_name, c.name AS category_name
    FROM parts p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN categories c ON c.id=p.category_id
    WHERE p.is_active=1 ORDER BY p.created_at DESC LIMIT 10")->fetchAll();

$bestParts = $db->query("SELECT p.*, b.name AS brand_name FROM parts p
    LEFT JOIN brands b ON b.id=p.brand_id WHERE p.is_active=1 ORDER BY p.stock DESC LIMIT 10")->fetchAll();

$newParts = $db->query("SELECT p.*, b.name AS brand_name FROM parts p
    LEFT JOIN brands b ON b.id=p.brand_id WHERE p.is_active=1 ORDER BY p.id DESC LIMIT 10")->fetchAll();

$ratings = getProductRatings(array_merge(
    array_column($featParts, 'id'),
    array_column($bestParts, 'id'),
    array_column($newParts, 'id')
));

$blogPosts = $db->query("SELECT * FROM blog_posts WHERE is_published=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();

$sliders = $db->query("SELECT * FROM sliders WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<!--top tags area start-->
<div class="top_tags_area">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="tags_content">
                    <ul>
                        <li><span><?= t('all_categories') ?>:</span></li>
                        <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
                        <li><a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>"><?= sanitize(tField($cat,'name')) ?></a></li>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                        <li><a href="<?= APP_URL ?>/catalog/index.php"><?= t('shop') ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!--top tags area end-->

<!--slider area start-->
<?php if (!empty($sliders)): ?>
<section class="slider_section mb-80">
    <div class="slider_area slider_carousel owl-carousel">
        <?php foreach ($sliders as $sl):
            $imgUrl  = $sl['image_url'] ?? '';
            if ($imgUrl && $imgUrl[0] !== '/' && !preg_match('~^https?://~i', $imgUrl)) {
                $imgUrl = APP_URL . '/' . ltrim($imgUrl, '/');
            } elseif ($imgUrl && $imgUrl[0] === '/') {
                $imgUrl = APP_URL . $imgUrl;
            }
            $linkUrl = $sl['link_url'] ?: (APP_URL . '/catalog/index.php');
        ?>
        <div class="single_slider d-flex align-items-center" data-bgimg="<?= sanitize($imgUrl) ?>">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="slider_content">
                            <?php if (!empty($sl['title'])): ?>
                                <h1><?= sanitize($sl['title']) ?></h1>
                            <?php endif; ?>
                            <?php if (!empty($sl['subtitle'])): ?>
                                <p><?= sanitize($sl['subtitle']) ?></p>
                            <?php endif; ?>
                            <a class="button" href="<?= sanitize($linkUrl) ?>"><?= t('shop') ?> <i class="fa fa-angle-double-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<!--slider area end-->

<!--banner area start-->
<div class="banner_area mb-80">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="welcome_title">
                    <h3><?= t('tagline') ?></h3>
                    <h2><?= sanitize(getSetting('site_name', t('site_name'))) ?> <span><?= t('shop') ?></span></h2>
                    <p><?= t('about_desc') ?></p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-4 col-md-4">
                <figure class="single_banner">
                    <div class="banner_thumb">
                        <a href="<?= APP_URL ?>/catalog/index.php"><img src="<?= APP_URL ?>/assets/img/bg/banner1.jpg" alt=""></a>
                    </div>
                </figure>
            </div>
            <div class="col-lg-4 col-md-4">
                <figure class="single_banner">
                    <div class="banner_thumb">
                        <a href="<?= APP_URL ?>/catalog/index.php"><img src="<?= APP_URL ?>/assets/img/bg/banner2.jpg" alt=""></a>
                    </div>
                </figure>
            </div>
            <div class="col-lg-4 col-md-4">
                <figure class="single_banner">
                    <div class="banner_thumb">
                        <a href="<?= APP_URL ?>/catalog/index.php"><img src="<?= APP_URL ?>/assets/img/bg/banner3.jpg" alt=""></a>
                    </div>
                </figure>
            </div>
        </div>
    </div>
</div>
<!--banner area end-->

<!--categories product area start-->
<div class="categories_product_area mb-80">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="categories_product_inner categories_column7 owl-carousel">
                    <?php
                    $catImages = ['category1.jpg','category2.jpg','category3.jpg','category4.jpg','category5.jpg','category6.jpg','category7.jpg'];
                    foreach ($categories as $i => $cat):
                        $img = $catImages[$i % count($catImages)];
                        $catImgSrc = !empty($cat['image_path'])
                            ? APP_URL . '/' . ltrim($cat['image_path'], '/')
                            : APP_URL . '/assets/img/s-product/' . $img;
                    ?>
                    <div class="single_categories_product">
                        <div class="categories_product_thumb">
                            <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>">
                                <img src="<?= sanitize($catImgSrc) ?>" alt="<?= sanitize(tField($cat,'name')) ?>">
                            </a>
                        </div>
                        <div class="categories_product_content">
                            <h4><a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>"><?= sanitize(tField($cat,'name')) ?></a></h4>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                    <?php foreach ($catImages as $img): ?>
                    <div class="single_categories_product">
                        <div class="categories_product_thumb">
                            <a href="<?= APP_URL ?>/catalog/index.php"><img src="<?= APP_URL ?>/assets/img/s-product/<?= $img ?>" alt=""></a>
                        </div>
                        <div class="categories_product_content"><h4><a href="<?= APP_URL ?>/catalog/index.php"><?= t('shop') ?></a></h4></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--categories product area end-->

<!--home section bg area start-->
<div class="home_section_bg">
    <!--product area start-->
    <div class="product_area">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="section_title">
                        <h2><span><?= t('shop') ?></span> <?= t('featured_products') ?></h2>
                    </div>
                    <div class="product_tab_btn">
                        <ul class="nav" role="tablist" id="nav-tab">
                            <li><a class="active" data-bs-toggle="tab" href="#Sellers" role="tab"><?= t('best_sellers') ?></a></li>
                            <li><a data-bs-toggle="tab" href="#Featured" role="tab"><?= t('featured_products') ?></a></li>
                            <li><a data-bs-toggle="tab" href="#Arrivals" role="tab"><?= t('new_arrivals') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="tab-content">
                <?php
                $tabs = [
                    'Sellers'  => $bestParts,
                    'Featured' => $featParts,
                    'Arrivals' => $newParts,
                ];
                $productImages = array_map(fn($n) => "product{$n}.jpg", range(1, 15));
                foreach ($tabs as $tabId => $parts): ?>
                <div class="tab-pane fade <?= $tabId==='Sellers'?'show active':'' ?>" id="<?= $tabId ?>" role="tabpanel">
                    <div class="product_carousel product_column5 owl-carousel">
                        <?php
                        // Group products in pairs for the product_items layout (2 per carousel item)
                        $chunks = array_chunk($parts, 2);
                        foreach ($chunks as $ci => $chunk): ?>
                        <div class="col-lg-3">
                            <div class="product_items">
                                <?php foreach ($chunk as $pi => $part):
                                    $img = productImageUrl($part['images']);
                                    $imgIdx = ($ci * 2 + $pi) % count($productImages);
                                    $fallbackImg = APP_URL . '/assets/img/product/' . $productImages[$imgIdx];
                                    $stock = getStockStatus((int)$part['stock']);
                                ?>
                                <article class="single_product">
                                    <figure>
                                        <div class="product_thumb">
                                            <a class="primary_img" href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>">
                                                <img src="<?= $img ?>" alt="<?= sanitize($part['name']) ?>" onerror="this.src='<?= $fallbackImg ?>'">
                                            </a>
                                            <?php if ($part['stock'] > 0): ?>
                                            <div class="label_product"><span class="label_new"><?= t('in_stock') ?></span></div>
                                            <?php endif; ?>
                                            <div class="quick_button">
                                                <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>" title="<?= t('quick_view') ?>"><i class="icon-eye"></i></a>
                                            </div>
                                        </div>
                                        <div class="product_content">
                                            <div class="product_content_inner">
                                                <p class="manufacture_product"><a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>"><?= sanitize($part['brand_name'] ?? '') ?></a></p>
                                                <h4 class="product_name"><a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$part['id'] ?>"><?= sanitize(truncate($part['name'],45)) ?></a></h4>
                                                <div class="price_box">
                                                    <span class="current_price"><?= formatPrice($part['price']) ?></span>
                                                </div>
                                                <?= productStarsInline((int)$part['id'], $ratings) ?>
                                            </div>
                                            <div class="action_links">
                                                <ul>
                                                    <li class="add_to_cart"><a href="javascript:void(0)" onclick="addToCart(<?= (int)$part['id'] ?>)" title="<?= t('add_to_cart') ?>"><?= t('add_to_cart') ?></a></li>
                                                    <li class="wishlist"><a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$part['id'] ?>)" title="<?= t('add_to_wishlist') ?>"><i class="icon-heart"></i></a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </figure>
                                </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($parts)): ?>
                        <div class="col-12 text-center py-5"><p style="color:#aaa"><?= t('no_records') ?></p></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!--product area end-->

    <!--blog area start-->
    <?php if (!empty($blogPosts)): ?>
    <div class="blog_area">
        <div class="container">
            <div class="section_title">
                <h2><span><?= t('blog') ?></span></h2>
            </div>
            <div class="blog_container blog_column4 owl-carousel">
                <?php
                $blogImgs = ['blog1.jpg','blog2.jpg','blog3.jpg','blog4.jpg','blog6.jpg'];
                foreach ($blogPosts as $bi => $post):
                    $bTitle   = tField($post,'title');
                    $bExcerpt = tField($post,'excerpt');
                    $bImg     = $blogImgs[$bi % count($blogImgs)];
                    $bDate    = date('d M', strtotime($post['created_at']));
                ?>
                <div class="col-lg-3">
                    <article class="single_blog">
                        <figure>
                            <div class="blog_thumb">
                                <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>">
                                    <img src="<?= APP_URL ?>/assets/img/blog/<?= $bImg ?>" alt="<?= sanitize($bTitle) ?>">
                                </a>
                            </div>
                            <figcaption class="blog_content">
                                <h4><a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>"><?= sanitize(truncate($bTitle,60)) ?></a></h4>
                                <div class="post_meta"><p><a href="<?= APP_URL ?>/pages/blog.php"><?= t('blog') ?></a> / <?= $bDate ?></p></div>
                                <?php if ($bExcerpt): ?>
                                <div class="post_desc"><p><?= sanitize(truncate($bExcerpt,80)) ?></p></div>
                                <?php endif; ?>
                                <footer class="post_readmore">
                                    <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>"><?= t('read_more') ?></a>
                                </footer>
                            </figcaption>
                        </figure>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!--blog area end-->
</div>
<!--home section bg area end-->

<!--brand area start-->
<div class="brand_area">
    <div class="container">
        <div class="col-12">
            <div class="brand_container owl-carousel">
                <?php
                $brandImages = ['brand1.jpg','brand2.jpg','brand3.jpg','brand4.jpg','brand5.jpg','brand6.jpg','brand7.jpg','brand8.jpg'];
                try {
                    $brands = $db->query("SELECT * FROM brands WHERE is_active=1 ORDER BY name LIMIT 8")->fetchAll();
                } catch (Exception $e) { $brands = []; }
                if (!empty($brands)):
                    $brandPairs = array_chunk($brands, 2);
                    foreach ($brandPairs as $bpair):
                ?>
                <div class="brand_list">
                    <?php foreach ($bpair as $bi => $brand):
                        $bImg = $brandImages[$bi % count($brandImages)];
                        $brandLogoSrc = !empty($brand['logo_path'])
                            ? APP_URL . '/' . ltrim($brand['logo_path'], '/')
                            : APP_URL . '/assets/img/brand/' . $bImg;
                    ?>
                    <div class="single_brand">
                        <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$brand['id'] ?>">
                            <img src="<?= sanitize($brandLogoSrc) ?>" alt="<?= sanitize($brand['name']) ?>" title="<?= sanitize($brand['name']) ?>">
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach;
                else:
                    foreach (array_chunk($brandImages, 2) as $bpair): ?>
                <div class="brand_list">
                    <?php foreach ($bpair as $bImg): ?>
                    <div class="single_brand">
                        <a href="<?= APP_URL ?>/catalog/index.php"><img src="<?= APP_URL ?>/assets/img/brand/<?= $bImg ?>" alt=""></a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
<!--brand area end-->

<!--social/contact strip-->
<div class="newsletter_area">
    <div class="container">
        <div class="newsletter_inner">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="newsletter_container">
                        <h3><?= t('follow_us') ?></h3>
                        <p><?= t('newsletter_text') ?></p>
                        <div class="footer_social">
                            <ul>
                                <?php
                                $socUrl = function (string $v, string $base): string {
                                    $v = trim($v);
                                    return preg_match('~^https?://~i', $v) ? $v : $base . ltrim($v, '@/');
                                };
                                ?>
                                <?php if (getSetting('site_telegram')): ?>
                                <li><a href="<?= sanitize($socUrl(getSetting('site_telegram'), 'https://t.me/')) ?>" class="twitter" target="_blank" rel="noopener noreferrer" aria-label="Telegram"><i class="fa fa-telegram"></i></a></li>
                                <?php endif; ?>
                                <?php if (getSetting('site_whatsapp')): ?>
                                <li><a href="https://wa.me/<?= sanitize(preg_replace('/\D/', '', getSetting('site_whatsapp'))) ?>" class="facebook" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fa fa-whatsapp"></i></a></li>
                                <?php endif; ?>
                                <?php if (getSetting('site_instagram')): ?>
                                <li><a href="<?= sanitize($socUrl(getSetting('site_instagram'), 'https://instagram.com/')) ?>" class="instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa fa-instagram"></i></a></li>
                                <?php endif; ?>
                                <?php if (getSetting('site_facebook')): ?>
                                <li><a href="<?= sanitize($socUrl(getSetting('site_facebook'), 'https://facebook.com/')) ?>" class="facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa fa-facebook"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="newsletter_container">
                        <h3><?= t('newsletter') ?></h3>
                        <p><?= t('newsletter_text') ?></p>
                        <div class="subscribe_form">
                            <form>
                                <input type="email" placeholder="<?= t('your_email') ?>...">
                                <button type="submit"><?= t('subscribe') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-7">
                    <div class="newsletter_container col_3">
                        <h3><?= t('contact_us') ?></h3>
                        <p><?= sanitize(getSetting('site_phone','+7 (495) 123-45-67')) ?></p>
                        <div class="app_img">
                            <ul>
                                <li><a href="<?= APP_URL ?>/pages/contact.php" class="az-contact-btn"><?= t('contact') ?></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!--end social/contact strip-->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
