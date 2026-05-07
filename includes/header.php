<?php
$cartCount     = isLoggedIn() ? getCartCount() : 0;
$wishlistCount = isLoggedIn() ? getWishlistCount() : 0;
$compareCount  = getCompareCount();
$currentUser   = getCurrentUser();
$flash         = getFlashMessage();
$csrfToken     = generateCsrfToken();
$activeLang    = currentLanguage();
$activeCur     = currentCurrency();
$languages     = availableLanguages();
$currencies    = availableCurrencies();
$pageTitleStr  = isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '';
$siteSuffix    = sanitize(getSetting('seo_title_suffix', 'АвтоЗапчасть | AutoDoc'));
$metaDesc      = isset($pageDescription) ? sanitize($pageDescription) : sanitize(getSetting('meta_description', ''));
$canonical     = canonicalUrl();
$ogImage       = $ogImage ?? (APP_URL . '/assets/img/site/placeholder.svg');
$bodyClass     = $bodyClass ?? '';
$adminArea     = $adminArea ?? false;
?>
<!DOCTYPE html>
<html lang="<?= sanitize($activeLang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $metaDesc ?>">
  <link rel="canonical" href="<?= sanitize($canonical) ?>">
  <meta property="og:type"        content="<?= isset($ogType) ? sanitize($ogType) : 'website' ?>">
  <meta property="og:title"       content="<?= $pageTitleStr ?: $siteSuffix ?>">
  <meta property="og:description" content="<?= $metaDesc ?>">
  <meta property="og:image"       content="<?= sanitize($ogImage) ?>">
  <meta property="og:url"         content="<?= sanitize($canonical) ?>">
  <meta name="twitter:card"       content="summary_large_image">
  <title><?= $pageTitleStr . $siteSuffix ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=2">
  <?php if ($adminArea): ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css?v=2">
  <?php endif; ?>
  <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/site/placeholder.svg">
  <?php if (!empty($jsonLd)): ?>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
</head>
<body class="<?= sanitize($bodyClass) ?><?= $adminArea ? ' admin-body' : '' ?>">

<?php if (!$adminArea): ?>
<!-- ── Top bar ─────────────────────────────────────────────── -->
<div class="topbar">
  <div class="container topbar-inner">
    <div class="topbar-left">
      <span>📞 <a href="tel:+78005553535">+7 (800) 555-35-35</a></span>
      <span class="dot">|</span>
      <span>🇹🇯 <a href="tel:+992926464646">+992 92 646-46-46</a></span>
      <span class="dot">|</span>
      <span>✉ <a href="mailto:info@avtozapchast.ru">info@avtozapchast.ru</a></span>
    </div>
    <div class="topbar-right">
      <div class="topbar-switcher" id="lang-switcher">
        <button onclick="toggleSwitcher('lang-switcher', event)">
          🌐 <?= sanitize(strtoupper($activeLang)) ?>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="dropdown">
          <?php foreach ($languages as $lng): ?>
            <a href="?lang=<?= sanitize($lng['code']) ?>" class="<?= $lng['code']===$activeLang ? 'active' : '' ?>"><?= sanitize($lng['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="topbar-switcher" id="cur-switcher">
        <button onclick="toggleSwitcher('cur-switcher', event)">
          💱 <?= sanitize($activeCur['code']) ?>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="dropdown">
          <?php foreach ($currencies as $c): ?>
            <a href="?cur=<?= sanitize($c['code']) ?>" class="<?= $c['code']===$activeCur['code'] ? 'active' : '' ?>"><?= sanitize($c['symbol']) ?> <?= sanitize($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (isLoggedIn()): ?>
        <a href="<?= APP_URL ?>/buyer/index.php"><?= t('my_account') ?></a>
        <a href="<?= APP_URL ?>/auth/logout.php"><?= t('logout') ?></a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a>
        <a href="<?= APP_URL ?>/auth/register.php"><?= t('register') ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Header main ─────────────────────────────────────────── -->
<header class="header-main">
  <div class="container header-main-inner">
    <a href="<?= APP_URL ?>/index.php" class="logo">
      <span class="logo-mark">A</span>
      АВТО<span>DOC</span>
    </a>

    <form class="header-search" action="<?= APP_URL ?>/search/index.php" method="get">
      <select name="category">
        <option value=""><?= t('all_categories') ?></option>
        <?php foreach (getCategories() as $c): if ($c['parent_id'] !== null) continue; ?>
          <option value="<?= sanitize($c['slug']) ?>" <?= ($_GET['category'] ?? '')===$c['slug']?'selected':'' ?>>
            <?= sanitize(tField('category',(int)$c['id'],'name',$c['name'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" placeholder="<?= t('search_placeholder') ?>" value="<?= sanitize($_GET['q'] ?? '') ?>" autocomplete="off" id="header-search">
      <button type="submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <?= t('search_btn') ?>
      </button>
    </form>

    <div class="header-actions">
      <a href="<?= APP_URL ?>/buyer/wishlist.php" class="header-action" title="<?= t('wishlist') ?>">
        <span class="icon-circle">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
          <?php if ($wishlistCount>0): ?><span class="badge-count"><?= $wishlistCount ?></span><?php endif; ?>
        </span>
        <span class="ha-label"><small><?= t('wishlist') ?></small><strong><?= $wishlistCount ?></strong></span>
      </a>
      <a href="<?= APP_URL ?>/buyer/compare.php" class="header-action" title="<?= t('compare') ?>">
        <span class="icon-circle">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
          <?php if ($compareCount>0): ?><span class="badge-count"><?= $compareCount ?></span><?php endif; ?>
        </span>
        <span class="ha-label"><small><?= t('compare') ?></small><strong><?= $compareCount ?></strong></span>
      </a>
      <a href="<?= APP_URL ?>/buyer/cart.php" class="header-action" title="<?= t('cart') ?>">
        <span class="icon-circle">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
          <?php if ($cartCount>0): ?><span class="badge-count" id="cart-badge"><?= $cartCount ?></span><?php endif; ?>
        </span>
        <span class="ha-label"><small><?= t('cart') ?></small><strong><?= $cartCount ?></strong></span>
      </a>
    </div>
  </div>
</header>

<!-- ── Main red nav ───────────────────────────────────────── -->
<nav class="mainnav">
  <div class="container mainnav-inner">
    <div class="cat-toggle" id="cat-toggle" onclick="toggleCatMenu(event)">
      <span class="lines"><span></span><span></span><span></span></span>
      <?= t('all_categories') ?>
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      <div class="cat-menu" onclick="event.stopPropagation()">
        <?php foreach (array_slice(getCategories(),0,12) as $c): if ($c['parent_id']!==null) continue; ?>
          <a href="<?= APP_URL ?>/catalog/index.php?category=<?= sanitize($c['slug']) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <?= sanitize(tField('category',(int)$c['id'],'name',$c['name'])) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <ul class="menu">
      <li class="<?= basename($_SERVER['PHP_SELF'])==='index.php' && dirname($_SERVER['PHP_SELF'])==='/' ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a>
      </li>
      <li><a href="<?= APP_URL ?>/catalog/index.php"><?= t('catalog') ?></a></li>
      <li><a href="<?= APP_URL ?>/search/vin.php"><?= t('find_by_vin') ?></a></li>
      <li><a href="<?= APP_URL ?>/blog/index.php"><?= t('blog') ?></a></li>
      <li><a href="<?= APP_URL ?>/pages/about.php"><?= t('about') ?></a></li>
      <li><a href="<?= APP_URL ?>/pages/delivery.php"><?= t('delivery') ?></a></li>
      <li><a href="<?= APP_URL ?>/pages/contacts.php"><?= t('contacts') ?></a></li>
      <?php if (hasRole(['manager','admin','superadmin'])): ?>
      <li><a href="<?= APP_URL ?>/<?= $_SESSION['role']==='manager' ? 'manager' : ($_SESSION['role']==='admin' ? 'admin' : 'superadmin') ?>/index.php" style="color:#fcb700">⚙ Панель</a></li>
      <?php endif; ?>
    </ul>

    <div class="mainnav-tail">
      <span>🚚 <?= t('free_shipping') ?></span>
    </div>
  </div>
</nav>
<?php endif; // not admin area ?>

<?php if ($flash): ?>
<div class="flash-wrap">
  <div class="alert alert-<?= sanitize($flash['type']) ?>">
    <span><?= sanitize($flash['message']) ?></span>
    <span class="dismiss" onclick="this.parentElement.remove()">✕</span>
  </div>
</div>
<?php endif; ?>

<main id="main-content">
