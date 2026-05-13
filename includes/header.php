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
$categories  = getCategories();
$catTree     = getCategoryTree($categories);
$siteName    = getSetting('site_name', t('site_name'));
$sitePhone   = getSetting('site_phone', '+7 (800) 555-35-35');
$pageTitle   = isset($pageTitle) ? $pageTitle : $siteName;

$langs = [
    'ru' => ['name'=>'Русский', 'flag'=>'RU'],
    'tg' => ['name'=>'Тоҷикӣ', 'flag'=>'TJ'],
    'en' => ['name'=>'English', 'flag'=>'EN'],
];

$currentUrl  = strtok($_SERVER['REQUEST_URI'], '?');
$queryParams = $_GET;
unset($queryParams['lang'], $queryParams['currency']);
?>
<!doctype html>
<html class="no-js" lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title><?= sanitize($pageTitle) ?></title>
    <meta name="description" content="<?= sanitize(getSetting('meta_description', t('tagline'))) ?>">
    <meta name="keywords" content="<?= sanitize(getSetting('meta_keywords', '')) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="<?= APP_URL ?>/assets/img/favicon.ico">
    <link rel="stylesheet" href="<?= MAZLAY_CSS ?>/plugins.css">
    <link rel="stylesheet" href="<?= MAZLAY_CSS ?>/style.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
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
                            <li class="currency">
                                <a href="#"><?= $currency ?> <i class="ion-chevron-down"></i></a>
                                <ul class="dropdown_currency">
                                    <?php foreach ($currencies as $cur):
                                        $qHdr = array_merge($queryParams, ['currency'=>$cur['code']]);
                                        $cName  = 'name_' . $lang;
                                        $cLabel = $cur[$cName] ?? $cur['name_ru'];
                                    ?>
                                    <li><a href="<?= $currentUrl ?>?<?= http_build_query($qHdr) ?>"><?= sanitize($cur['symbol'] . ' ' . $cLabel) ?></a></li>
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
                                <li class="currency">
                                    <a href="#"><?= $currency ?> <?= getCurrencySymbol() ?> <i class="ion-chevron-down"></i></a>
                                    <ul class="dropdown_currency">
                                        <?php foreach ($currencies as $cur):
                                            $qHdr = array_merge($queryParams, ['currency'=>$cur['code']]);
                                            $cName  = 'name_' . $lang;
                                            $cLabel = $cur[$cName] ?? $cur['name_ru'];
                                        ?>
                                        <li><a href="<?= $currentUrl ?>?<?= http_build_query($qHdr) ?>"><?= sanitize($cur['symbol'] . ' — ' . $cLabel) ?></a></li>
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
                                <li><a href="<?= APP_URL ?>/<?= $currentUser['role'] ?>/index.php"><i class="fa fa-user-o"></i> <?= sanitize($currentUser['username']) ?></a></li>
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
                                <span class="logo-text">АВТО<span>ЗАПЧАСТЬ</span></span>
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
                                        <span class="cart_price"><?= formatPrice($miniTotal) ?> <i class="ion-ios-arrow-down"></i></span>
                                        <span class="cart_count"><?= $cartCount ?></span>
                                    </a>
                                    <div class="mini_cart">
                                        <div class="mini_cart_inner">
                                            <div class="cart_close">
                                                <div class="cart_text"><h3><?= t('shopping_cart') ?></h3></div>
                                                <div class="mini_cart_close"><a href="javascript:void(0)"><i class="icon-x"></i></a></div>
                                            </div>
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
                                            <div class="mini_cart_table">
                                                <div class="cart_total">
                                                    <span><?= t('subtotal') ?>:</span>
                                                    <span class="price"><?= formatPrice($miniTotal) ?></span>
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
                                    <li><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a></li>
                                    <li class="menu-item-has-children">
                                        <a href="<?= APP_URL ?>/catalog/index.php"><?= t('shop') ?></a>
                                        <ul class="sub_menu">
                                            <?php foreach ($catTree as $cat): ?>
                                            <li><a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($cat['slug']) ?>"><?= sanitize(tField($cat,'name')) ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                    <li><a href="<?= APP_URL ?>/pages/vin.php" style="color:#d32f2f;font-weight:700;"><i class="fa fa-search"></i> VIN</a></li>
                                    <li><a href="<?= APP_URL ?>/pages/blog.php"><?= t('blog') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/about.php"><?= t('about') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/contact.php"><?= t('contact') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/faq.php"><?= t('faq') ?></a></li>
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
