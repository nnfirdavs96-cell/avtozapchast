<?php
// Ensure config is loaded
if (!function_exists('t')) require_once dirname(__DIR__) . '/config/config.php';

$lang        = getLang();
$currency    = getActiveCurrency();
$currencies  = getCurrencies();
$cartCount   = getCartCount();
$wishCount   = getWishlistCount();
$miniCart    = getMiniCart();
$miniTotal   = getMiniCartTotal();
$currentUser = getCurrentUser();
// One-time seed: populate subcategories & product images on first page load after deploy
if (!getSetting('cat_subseed_v2', '')) {
    seedCategorySubcategories();
    setSetting('cat_subseed_v2', '1');
}
if (!getSetting('prod_imgseed_done', '')) {
    fillMissingProductImages();
    setSetting('prod_imgseed_done', '1');
}
if (!getSetting('brands_seed_done', '')) {
    seedBrands();
    setSetting('brands_seed_done', '1');
}
ensurePhoneAuthSchema();
ensureStaffPinSchema();
if (!getSetting('banners_seed_done', '')) {
    seedBanners();
    setSetting('banners_seed_done', '1');
}
seedSliderTemplate(); // swaps only slider photos to template images (self-guarded, one-time)
seedSliderText();     // presentable copy for demo slides only (self-guarded, one-time)
seedDemoProducts();   // stocks the catalogue with editable demo products (self-guarded, one-time)

$categories  = getCategories();
$catTree     = getCategoryTree($categories);
$siteName    = getSetting('site_name', t('site_name'));
$sitePhone   = getSetting('site_phone', '+992 92 646-46-46');
$pageTitle   = isset($pageTitle) ? $pageTitle : $siteName;

$langs = [
    'ru' => ['name'=>'Русский', 'flag'=>'RU'],
    'tg' => ['name'=>'Тоҷикӣ', 'flag'=>'TJ'],
    'en' => ['name'=>'English', 'flag'=>'EN'],
];

$currentUrl  = strtok($_SERVER['REQUEST_URI'], '?');
$queryParams = $_GET;
unset($queryParams['lang'], $queryParams['currency']);

// ── SEO meta (each page may override via $pageDescription/$canonical/$ogImage) ──
$metaDescription = (isset($pageDescription) && trim((string)$pageDescription) !== '')
    ? (string)$pageDescription
    : getSetting('meta_description', t('tagline'));
$metaDescription = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($metaDescription))), 0, 300);
$canonicalUrl = (isset($canonical) && $canonical !== '')
    ? $canonical
    : (APP_URL !== '' ? APP_URL . $currentUrl : $currentUrl);
$ogImageUrl = (isset($ogImage) && $ogImage !== '')
    ? $ogImage
    : APP_URL . '/assets/img/logo/avtodoc-favicon.png';
$ogType    = $ogType    ?? 'website';
$headExtra = $headExtra ?? '';   // raw HTML (e.g. JSON-LD) injected before </head>
?>
<!doctype html>
<html class="no-js" lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title><?= sanitize($pageTitle) ?></title>
    <meta name="description" content="<?= sanitize($metaDescription) ?>">
    <meta name="keywords" content="<?= sanitize(getSetting('meta_keywords', '')) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($canonicalUrl !== ''): ?><link rel="canonical" href="<?= sanitize($canonicalUrl) ?>">
    <?php endif; ?><meta property="og:type" content="<?= sanitize($ogType) ?>">
    <meta property="og:title" content="<?= sanitize($pageTitle) ?>">
    <meta property="og:description" content="<?= sanitize($metaDescription) ?>">
    <meta property="og:site_name" content="<?= sanitize($siteName) ?>">
    <?php if ($canonicalUrl !== ''): ?><meta property="og:url" content="<?= sanitize($canonicalUrl) ?>">
    <?php endif; ?><?php if ($ogImageUrl !== ''): ?><meta property="og:image" content="<?= sanitize($ogImageUrl) ?>">
    <?php endif; ?><meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo/avtodoc-favicon.png?v=<?= @filemtime(APP_ROOT.'/assets/img/logo/avtodoc-favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/logo/avtodoc-favicon.png">
    <link rel="stylesheet" href="<?= MAZLAY_CSS ?>/plugins.css">
    <link rel="stylesheet" href="<?= MAZLAY_CSS ?>/style.css">
    <?php $cssV = @filemtime(APP_ROOT . '/assets/css/custom.css') ?: time(); ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css?v=<?= $cssV ?>">
    <?= $headExtra ?>
</head>
<body>

<?php if ($flash = getFlashMessage()): ?>
<div id="flash-global" style="position:fixed;top:0;left:0;right:0;z-index:9999;padding:12px 20px;text-align:center;font-weight:600;background:<?= $flash['type']==='success'?'#28a745':($flash['type']==='danger'?'#dc3545':'#ffc107') ?>;color:<?= $flash['type']==='warning'?'#333':'#fff' ?>">
    <?= sanitize($flash['message']) ?>
    <button onclick="this.parentNode.remove()" style="float:right;background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer">&times;</button>
</div>
<script>setTimeout(()=>{const f=document.getElementById('flash-global');if(f)f.remove();},8000)</script>
<?php endif; ?>

<!-- Offcanvas -->
<div class="off_canvars_overlay"></div>
<div class="offcanvas_menu">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="canvas_open"><a href="javascript:void(0)"><i class="ion-navicon"></i></a></div>
                <div class="offcanvas_menu_wrapper">
                    <div class="canvas_close"><a href="javascript:void(0)"><i class="ion-android-close"></i></a></div>
                    <div class="call_support">
                        <p><i class="icon-phone-call"></i> <span><?= t('call_us') ?>: <a href="tel:<?= sanitize($sitePhone) ?>"><?= sanitize($sitePhone) ?></a></span></p>
                    </div>
                    <div class="header_account">
                        <ul>
                            <li class="language">
                                <a href="#"><?= $langs[$lang]['flag'] ?> <?= $langs[$lang]['name'] ?> <i class="ion-chevron-down"></i></a>
                                <ul class="dropdown_language">
                                    <?php foreach ($langs as $lCode => $lData):
                                        $qHdr = array_merge($queryParams, ['lang'=>$lCode]);
                                    ?>
                                    <li><a href="<?= $currentUrl ?>?<?= http_build_query($qHdr) ?>"><?= $lData['flag'] ?> <?= $lData['name'] ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="header_top_links">
                        <ul>
                            <?php if ($currentUser): ?>
                            <li><a href="<?= APP_URL ?>/<?= $currentUser['role'] ?>/index.php"><?= t('my_account') ?></a></li>
                            <li><a href="<?= APP_URL ?>/auth/logout.php"><?= t('logout') ?></a></li>
                            <?php else: ?>
                            <li><a href="<?= APP_URL ?>/auth/register.php"><?= t('register') ?></a></li>
                            <li><a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a></li>
                            <?php endif; ?>
                            <li><a href="<?= APP_URL ?>/buyer/cart.php"><?= t('shopping_cart') ?></a></li>
                        </ul>
                    </div>
                    <div class="search_container">
                        <form action="<?= APP_URL ?>/search/index.php" method="GET">
                            <div class="hover_category">
                                <select class="select_option" name="cat">
                                    <option value=""><?= t('all_categories') ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"><?= sanitize(tField($cat,'name')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="search_box">
                                <input name="q" placeholder="<?= t('search_placeholder') ?>" type="text" value="<?= sanitize($_GET['q'] ?? '') ?>">
                                <button type="submit"><?= t('search') ?></button>
                            </div>
                        </form>
                    </div>
                    <div id="menu" class="text-left">
                        <ul class="offcanvas_main_menu">
                            <li><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a></li>
                            <li class="menu-item-has-children">
                                <a href="<?= APP_URL ?>/catalog/index.php"><?= t('shop') ?></a>
                                <ul class="sub-menu">
                                    <?php foreach ($catTree as $cat): ?>
                                    <li><a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>"><?= sanitize(tField($cat,'name')) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                            <li><a href="<?= APP_URL ?>/pages/vin.php"><i class="fa fa-search"></i> VIN</a></li>
                            <li><a href="<?= APP_URL ?>/pages/blog.php"><?= t('blog') ?></a></li>
                            <li><a href="<?= APP_URL ?>/pages/about.php"><?= t('about') ?></a></li>
                            <li><a href="<?= APP_URL ?>/pages/reviews.php"><?= t('shop_reviews') ?></a></li>
                            <li><a href="<?= APP_URL ?>/pages/contact.php"><?= t('contact') ?></a></li>
                            <li><a href="<?= APP_URL ?>/pages/faq.php"><?= t('faq') ?></a></li>
                        </ul>
                    </div>
                    <div class="offcanvas_footer">
                        <span><a href="mailto:<?= sanitize(getSetting('site_email','info@avtozapchast.ru')) ?>"><i class="fa fa-envelope-o"></i> <?= sanitize(getSetting('site_email','info@avtozapchast.ru')) ?></a></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /offcanvas -->

<header>
    <div class="main_header">
        <!-- header top -->
        <div class="header_top">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-4 col-md-5">
                        <div class="header_account">
                            <ul>
                                <li class="language">
                                    <a href="#"><?= $langs[$lang]['flag'] ?> <?= $langs[$lang]['name'] ?> <i class="ion-chevron-down"></i></a>
                                    <ul class="dropdown_language">
                                        <?php foreach ($langs as $lCode => $lData):
                                            $qHdr = array_merge($queryParams, ['lang'=>$lCode]);
                                        ?>
                                        <li><a href="<?= $currentUrl ?>?<?= http_build_query($qHdr) ?>"><?= $lData['flag'] ?> <?= $lData['name'] ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-8 col-md-7">
                        <div class="header_top_links text-right">
                            <ul>
                                <?php if ($currentUser): ?>
                                <li><a href="<?= APP_URL ?>/<?= $currentUser['role'] ?>/index.php"><?php if (!empty($currentUser['avatar_path'])): ?><img src="<?= sanitize($currentUser['avatar_path']) ?>" alt="" style="width:22px;height:22px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:5px;"><?php else: ?><i class="fa fa-user-o"></i> <?php endif; ?><?= sanitize($currentUser['username']) ?></a></li>
                                <li><a href="<?= APP_URL ?>/auth/logout.php"><?= t('logout') ?></a></li>
                                <?php else: ?>
                                <li><a href="<?= APP_URL ?>/auth/register.php"><?= t('register') ?></a></li>
                                <li><a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a></li>
                                <?php endif; ?>
                                <li><a href="<?= APP_URL ?>/buyer/cart.php"><?= t('shopping_cart') ?></a></li>
                                <li><a href="<?= APP_URL ?>/buyer/checkout.php"><?= t('checkout') ?></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /header top -->

        <!-- header middle -->
        <div class="header_middle">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-2 col-md-4 col-sm-4 col-4">
                        <div class="logo">
                            <a href="<?= APP_URL ?>/index.php" aria-label="<?= sanitize($siteName) ?>">
                                <img src="<?= APP_URL ?>/assets/img/logo/avtodoc-logo.png?v=<?= @filemtime(APP_ROOT.'/assets/img/logo/avtodoc-logo.png') ?>" alt="AutoDoc" class="logo-img">
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-10 col-md-6 col-sm-6 col-6">
                        <div class="header_right_box">
                            <div class="search_container">
                                <form action="<?= APP_URL ?>/search/index.php" method="GET">
                                    <div class="hover_category">
                                        <select class="select_option" name="cat">
                                            <option value=""><?= t('all_categories') ?></option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int)$cat['id'] ?>" <?= (isset($_GET['cat']) && $_GET['cat']==$cat['id'])?'selected':'' ?>><?= sanitize(tField($cat,'name')) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="search_box">
                                        <input name="q" placeholder="<?= t('search_placeholder') ?>" type="text" value="<?= sanitize($_GET['q'] ?? '') ?>">
                                        <button type="submit"><?= t('search') ?></button>
                                    </div>
                                </form>
                            </div>
                            <div class="header_configure_area">
                                <div class="canvas_open mobile_menu_trigger">
                                    <a href="javascript:void(0)" aria-label="Меню"><i class="fa fa-bars"></i></a>
                                </div>
                                <div class="header_auth_mobile">
                                    <?php if ($currentUser): ?>
                                    <a href="<?= APP_URL ?>/<?= $currentUser['role'] ?>/index.php" class="auth-link-mobile" aria-label="<?= t('my_account') ?>">
                                        <i class="fa fa-user-circle-o"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="<?= APP_URL ?>/auth/login.php" class="auth-link-mobile" aria-label="<?= t('login') ?>">
                                        <i class="fa fa-sign-in"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <div class="header_wishlist">
                                    <a href="<?= APP_URL ?>/buyer/wishlist.php">
                                        <i class="icon-heart"></i>
                                        <span class="wishlist_count"><?= $wishCount ?></span>
                                    </a>
                                </div>
                                <div class="mini_cart_wrapper">
                                    <a href="javascript:void(0)">
                                        <i class="icon-shopping-bag2"></i>
                                        <span class="cart_price"><span class="cart_amount"><?= formatPrice($miniTotal) ?></span> <i class="ion-ios-arrow-down"></i></span>
                                        <span class="cart_count"><?= $cartCount ?></span>
                                    </a>
                                    <div class="mini_cart">
                                        <div class="mini_cart_inner">
                                            <div class="cart_close">
                                                <div class="cart_text"><h3><?= t('shopping_cart') ?></h3></div>
                                                <div class="mini_cart_close"><a href="javascript:void(0)"><i class="icon-x"></i></a></div>
                                            </div>
                                            <div class="mini_cart_items">
                                            <?php if (empty($miniCart)): ?>
                                            <p style="padding:16px;color:#888;text-align:center"><?= t('cart_empty') ?></p>
                                            <?php else: foreach ($miniCart as $item): ?>
                                            <div class="cart_item">
                                                <div class="cart_img">
                                                    <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$item['id'] ?>">
                                                        <img src="<?= productImageUrl($item['images']) ?>" alt="<?= sanitize($item['name']) ?>" style="width:60px;height:60px;object-fit:cover">
                                                    </a>
                                                </div>
                                                <div class="cart_info">
                                                    <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$item['id'] ?>"><?= sanitize(truncate($item['name'],40)) ?></a>
                                                    <p><?= t('quantity') ?>: <?= (int)$item['quantity'] ?> &times; <span><?= formatPrice($item['price']) ?></span></p>
                                                </div>
                                                <div class="cart_remove">
                                                    <a href="<?= APP_URL ?>/api/cart.php?action=remove&part_id=<?= (int)$item['id'] ?>&_csrf=<?= generateCsrfToken() ?>"><i class="ion-android-close"></i></a>
                                                </div>
                                            </div>
                                            <?php endforeach; endif; ?>
                                            </div>
                                            <div class="mini_cart_table">
                                                <div class="cart_total">
                                                    <span><?= t('subtotal') ?>:</span>
                                                    <span class="price cart_subtotal"><?= formatPrice($miniTotal) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mini_cart_footer">
                                            <div class="cart_button"><a href="<?= APP_URL ?>/buyer/cart.php"><?= t('view_cart') ?></a></div>
                                            <div class="cart_button"><a class="active" href="<?= APP_URL ?>/buyer/checkout.php"><?= t('checkout') ?></a></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /header middle -->

        <!-- nav -->
        <div class="header_bottom sticky-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-3">
                        <div class="categories_menu">
                            <div class="categories_title">
                                <h2 class="categori_toggle"><?= t('all_categories') ?></h2>
                            </div>
                            <div class="categories_menu_toggle">
                                <ul>
                                    <?php foreach ($catTree as $cat): ?>
                                    <li class="<?= !empty($cat['children'])?'menu_item_children':'' ?>">
                                        <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>">
                                            <?= sanitize(tField($cat,'name')) ?>
                                            <?php if (!empty($cat['children'])): ?><i class="fa fa-angle-right"></i><?php endif; ?>
                                        </a>
                                        <?php if (!empty($cat['children'])): ?>
                                        <ul class="categories_mega_menu">
                                            <?php foreach ($cat['children'] as $child): ?>
                                            <li><a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($child['slug']) ?>"><?= sanitize(tField($child,'name')) ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="main_menu">
                            <nav>
                                <ul>
                                    <!-- ДОМ -->
                                    <li class="menu-item-has-children az-has-megamenu">
                                        <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a>
                                        <div class="az-megamenu az-megamenu--sm">
                                            <ul class="az-megamenu__list">
                                                <li><a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> <?= t('home') ?></a></li>
                                                <li><a href="<?= APP_URL ?>/catalog/index.php?sort=new"><i class="fa fa-star-o"></i> Новинки</a></li>
                                                <li><a href="<?= APP_URL ?>/catalog/index.php?sale=1"><i class="fa fa-tag"></i> Акции и скидки</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/vin.php"><i class="fa fa-search"></i> VIN-поиск</a></li>
                                            </ul>
                                        </div>
                                    </li>

                                    <!-- МАГАЗИН — мега-меню -->
                                    <li class="menu-item-has-children az-has-megamenu az-has-megamenu--wide">
                                        <a href="<?= APP_URL ?>/catalog/index.php"><?= t('shop') ?></a>
                                        <div class="az-megamenu az-megamenu--wide">
                                            <?php
                                            $catChunks = array_chunk($catTree, (int)ceil(count($catTree)/3));
                                            ?>
                                            <div class="az-megamenu__inner">
                                                <?php foreach ($catChunks as $chunk): ?>
                                                <div class="az-megamenu__col">
                                                    <ul class="az-megamenu__list">
                                                        <?php foreach ($chunk as $cat): ?>
                                                        <li class="az-megamenu__group">
                                                            <a class="az-megamenu__head" href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>">
                                                                <?= sanitize(tField($cat,'name')) ?>
                                                                <?php if (!empty($cat['children'])): ?>
                                                                <span class="az-mega-count"><?= count($cat['children']) ?></span>
                                                                <?php endif; ?>
                                                            </a>
                                                            <?php if (!empty($cat['children'])): ?>
                                                            <ul class="az-megamenu__sub">
                                                                <?php foreach ($cat['children'] as $child): ?>
                                                                <li><a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($child['slug']) ?>"><?= sanitize(tField($child,'name')) ?></a></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                            <?php endif; ?>
                                                        </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                                <?php endforeach; ?>
                                                <div class="az-megamenu__col az-megamenu__col--promo">
                                                    <div class="az-mega-promo">
                                                        <span class="az-mega-promo__tag">Популярно</span>
                                                        <p class="az-mega-promo__text">Запчасти для Toyota, BMW, Lada и других марок</p>
                                                        <a href="<?= APP_URL ?>/catalog/index.php" class="az-mega-promo__btn">Весь каталог</a>
                                                    </div>
                                                    <div class="az-mega-links">
                                                        <a href="<?= APP_URL ?>/catalog/index.php?sort=popular"><i class="fa fa-fire"></i> Хиты продаж</a>
                                                        <a href="<?= APP_URL ?>/catalog/index.php?sort=new"><i class="fa fa-star-o"></i> Новинки</a>
                                                        <a href="<?= APP_URL ?>/catalog/index.php?sale=1"><i class="fa fa-percent"></i> Скидки</a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="az-megamenu__footer">
                                                <a href="<?= APP_URL ?>/catalog/index.php">Посмотреть все категории <i class="fa fa-arrow-right"></i></a>
                                                <a href="<?= APP_URL ?>/pages/vin.php"><i class="fa fa-search"></i> Найти по VIN</a>
                                            </div>
                                        </div>
                                    </li>

                                    <!-- VIN -->
                                    <li><a href="<?= APP_URL ?>/pages/vin.php" class="az-nav-vin"><i class="fa fa-search"></i> VIN</a></li>

                                    <!-- БЛОГ -->
                                    <li class="menu-item-has-children az-has-megamenu">
                                        <a href="<?= APP_URL ?>/pages/blog.php"><?= t('blog') ?></a>
                                        <div class="az-megamenu az-megamenu--sm">
                                            <ul class="az-megamenu__list">
                                                <li><a href="<?= APP_URL ?>/pages/blog.php"><i class="fa fa-newspaper-o"></i> Все статьи</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/blog.php?cat=news"><i class="fa fa-rss"></i> Новости</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/blog.php?cat=tips"><i class="fa fa-wrench"></i> Советы по ТО</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/blog.php?cat=review"><i class="fa fa-star-half-o"></i> Обзоры запчастей</a></li>
                                            </ul>
                                        </div>
                                    </li>

                                    <!-- СТРАНИЦЫ -->
                                    <li class="menu-item-has-children az-has-megamenu">
                                        <a href="#">Страницы</a>
                                        <div class="az-megamenu az-megamenu--sm">
                                            <ul class="az-megamenu__list">
                                                <li><a href="<?= APP_URL ?>/pages/about.php"><i class="fa fa-building-o"></i> <?= t('about') ?></a></li>
                                                <li><a href="<?= APP_URL ?>/pages/reviews.php"><i class="fa fa-star"></i> <?= t('shop_reviews') ?></a></li>
                                                <li><a href="<?= APP_URL ?>/pages/contact.php"><i class="fa fa-envelope-o"></i> <?= t('contact') ?></a></li>
                                                <li><a href="<?= APP_URL ?>/pages/faq.php"><i class="fa fa-question-circle-o"></i> <?= t('faq') ?></a></li>
                                                <li><a href="<?= APP_URL ?>/pages/vin.php"><i class="fa fa-search"></i> VIN-поиск</a></li>
                                                <li><a href="<?= APP_URL ?>/buyer/orders.php"><i class="fa fa-truck"></i> Мои заказы</a></li>
                                            </ul>
                                        </div>
                                    </li>

                                    <!-- О НАС -->
                                    <li class="menu-item-has-children az-has-megamenu">
                                        <a href="<?= APP_URL ?>/pages/about.php"><?= t('about') ?></a>
                                        <div class="az-megamenu az-megamenu--sm">
                                            <ul class="az-megamenu__list">
                                                <li><a href="<?= APP_URL ?>/pages/about.php"><i class="fa fa-info-circle"></i> О компании</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/about.php#team"><i class="fa fa-users"></i> Наша команда</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/about.php#reviews"><i class="fa fa-comments-o"></i> Отзывы клиентов</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/contact.php"><i class="fa fa-map-marker"></i> Наши магазины</a></li>
                                            </ul>
                                        </div>
                                    </li>

                                    <!-- СВЯЗАТЬСЯ -->
                                    <li class="menu-item-has-children az-has-megamenu">
                                        <a href="<?= APP_URL ?>/pages/contact.php"><?= t('contact') ?></a>
                                        <div class="az-megamenu az-megamenu--sm">
                                            <ul class="az-megamenu__list">
                                                <li><a href="<?= APP_URL ?>/pages/contact.php"><i class="fa fa-envelope-o"></i> Форма обратной связи</a></li>
                                                <li><a href="<?= APP_URL ?>/pages/contact.php#map"><i class="fa fa-map-o"></i> Схема проезда</a></li>
                                                <li><a href="tel:<?= sanitize($sitePhone) ?>"><i class="fa fa-phone"></i> <?= sanitize($sitePhone) ?></a></li>
                                                <li><a href="<?= APP_URL ?>/pages/faq.php"><i class="fa fa-question-circle-o"></i> FAQ</a></li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="header_phone">
                            <div class="phone_inner">
                                <p><?= t('call_us') ?>: <a href="tel:<?= sanitize($sitePhone) ?>"><?= sanitize($sitePhone) ?></a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /nav -->
    </div>
</header>
