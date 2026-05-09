<?php
// header.php - Must be included AFTER config.php
$cartCount = isLoggedIn() ? getCartCount() : 0;
$currentUser = getCurrentUser();
$flash = getFlashMessage();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="АвтоЗапчасть — профессиональный подбор и продажа автозапчастей">
  <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '' ?>АвтоЗапчасть</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <style>
    /* ── Navbar ──────────────────────────────────────────────── */
    .navbar {
      background: var(--bg-secondary);
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .navbar-inner {
      max-width: 1440px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      height: 64px;
      gap: 20px;
    }
    .nav-logo {
      font-family: var(--font-display);
      font-size: 1.8rem;
      color: var(--text-primary);
      text-decoration: none;
      letter-spacing: 2px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .nav-logo span { color: var(--accent); }
    .nav-logo-icon {
      width: 32px;
      height: 32px;
      background: var(--accent);
      clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* Search */
    .nav-search {
      flex: 1;
      max-width: 520px;
      position: relative;
    }
    .nav-search-form {
      display: flex;
      align-items: center;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 4px;
      overflow: hidden;
      transition: border-color 0.2s;
    }
    .nav-search-form:focus-within {
      border-color: var(--accent);
    }
    .nav-search-input {
      flex: 1;
      background: transparent;
      border: none;
      outline: none;
      padding: 10px 14px;
      color: var(--text-primary);
      font-family: var(--font-body);
      font-size: 0.875rem;
    }
    .nav-search-input::placeholder { color: var(--text-muted); }
    .nav-search-btn {
      padding: 10px 16px;
      background: var(--accent);
      border: none;
      cursor: pointer;
      color: #fff;
      font-size: 1rem;
      transition: background 0.2s;
      display: flex;
      align-items: center;
    }
    .nav-search-btn:hover { background: var(--accent-dark); }
    .search-dropdown {
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      right: 0;
      background: var(--bg-card);
      border: 1px solid var(--border-accent);
      border-radius: 4px;
      display: none;
      max-height: 320px;
      overflow-y: auto;
      z-index: 2000;
      box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    }
    .search-dropdown.open { display: block; }
    .search-result-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      text-decoration: none;
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }
    .search-result-item:last-child { border-bottom: none; }
    .search-result-item:hover { background: var(--bg-hover); }
    .search-result-num {
      font-family: var(--font-mono);
      font-size: 0.72rem;
      color: var(--accent);
      background: var(--accent-glow);
      padding: 2px 6px;
      border-radius: 2px;
      white-space: nowrap;
    }
    .search-result-name {
      font-size: 0.825rem;
      color: var(--text-primary);
      flex: 1;
    }
    .search-result-price {
      font-family: var(--font-mono);
      font-size: 0.78rem;
      color: var(--accent);
      white-space: nowrap;
    }
    .search-no-results {
      padding: 16px;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.825rem;
    }

    /* Nav links */
    .nav-links {
      display: flex;
      align-items: center;
      gap: 4px;
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .nav-links a {
      color: var(--text-secondary);
      text-decoration: none;
      font-size: 0.825rem;
      font-weight: 500;
      padding: 6px 12px;
      border-radius: 4px;
      transition: color 0.2s, background 0.2s;
      white-space: nowrap;
      font-family: var(--font-body);
    }
    .nav-links a:hover { color: var(--text-primary); background: var(--bg-card); }
    .nav-links a.active { color: var(--accent); }

    /* Cart & user */
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-left: auto;
    }
    .cart-btn {
      position: relative;
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 4px;
      color: var(--text-primary);
      text-decoration: none;
      font-size: 0.825rem;
      transition: border-color 0.2s, background 0.2s;
    }
    .cart-btn:hover { border-color: var(--accent); background: var(--bg-hover); }
    .cart-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background: var(--accent);
      color: #fff;
      font-family: var(--font-mono);
      font-size: 0.65rem;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }
    .btn-login {
      padding: 8px 16px;
      background: transparent;
      border: 1px solid var(--accent);
      color: var(--accent);
      border-radius: 4px;
      text-decoration: none;
      font-size: 0.825rem;
      font-weight: 500;
      transition: background 0.2s, color 0.2s;
    }
    .btn-login:hover { background: var(--accent); color: #fff; }
    .btn-register {
      padding: 8px 16px;
      background: var(--accent);
      border: 1px solid var(--accent);
      color: #fff;
      border-radius: 4px;
      text-decoration: none;
      font-size: 0.825rem;
      font-weight: 500;
      transition: background 0.2s;
    }
    .btn-register:hover { background: var(--accent-dark); }
    .user-dropdown-wrap { position: relative; }
    .user-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 4px;
      color: var(--text-primary);
      cursor: pointer;
      font-size: 0.825rem;
      transition: border-color 0.2s;
      font-family: var(--font-body);
    }
    .user-btn:hover { border-color: var(--accent); }
    .user-avatar {
      width: 26px;
      height: 26px;
      background: var(--accent);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-mono);
      font-size: 0.7rem;
      color: #fff;
      font-weight: 700;
    }
    .role-badge {
      font-family: var(--font-mono);
      font-size: 0.6rem;
      padding: 2px 5px;
      border-radius: 2px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .role-badge.superadmin { background: #9b59b6; color: #fff; }
    .role-badge.admin      { background: var(--danger); color: #fff; }
    .role-badge.manager    { background: var(--info); color: #fff; }
    .role-badge.buyer      { background: var(--success); color: #fff; }
    .user-dropdown {
      position: absolute;
      top: calc(100% + 6px);
      right: 0;
      min-width: 200px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 4px;
      display: none;
      z-index: 2000;
      box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    }
    .user-dropdown.open { display: block; }
    .user-dropdown a {
      display: block;
      padding: 10px 16px;
      color: var(--text-secondary);
      text-decoration: none;
      font-size: 0.825rem;
      border-bottom: 1px solid var(--border);
      transition: background 0.15s, color 0.15s;
    }
    .user-dropdown a:last-child { border-bottom: none; color: var(--danger); }
    .user-dropdown a:hover { background: var(--bg-hover); color: var(--text-primary); }

    /* Secondary nav bar */
    .subnav {
      background: var(--bg-primary);
      border-bottom: 1px solid var(--border);
    }
    .subnav-inner {
      max-width: 1440px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      height: 40px;
      gap: 0;
    }
    .subnav a {
      color: var(--text-muted);
      text-decoration: none;
      font-size: 0.78rem;
      padding: 0 14px;
      height: 40px;
      display: flex;
      align-items: center;
      border-right: 1px solid var(--border);
      transition: color 0.2s, background 0.2s;
      font-family: var(--font-mono);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .subnav a:first-child { border-left: 1px solid var(--border); }
    .subnav a:hover { color: var(--accent); background: var(--accent-glow); }

    /* Flash messages */
    .flash-wrap {
      max-width: 1440px;
      margin: 12px auto 0;
      padding: 0 24px;
    }
    .flash {
      padding: 12px 16px;
      border-radius: 4px;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 10px;
      border: 1px solid transparent;
    }
    .flash-success { background: rgba(46,204,113,0.12); border-color: var(--success); color: var(--success); }
    .flash-danger  { background: rgba(231,76,60,0.12); border-color: var(--danger); color: var(--danger); }
    .flash-warning { background: rgba(243,156,18,0.12); border-color: var(--warning); color: var(--warning); }
    .flash-info    { background: rgba(52,152,219,0.12); border-color: var(--info); color: var(--info); }
    .flash-dismiss { margin-left: auto; cursor: pointer; opacity: 0.6; font-size: 1.1rem; }

    /* Mobile hamburger */
    .hamburger {
      display: none;
      flex-direction: column;
      gap: 5px;
      cursor: pointer;
      padding: 4px;
      background: none;
      border: none;
    }
    .hamburger span {
      display: block;
      width: 24px;
      height: 2px;
      background: var(--text-secondary);
      transition: all 0.2s;
    }
    @media (max-width: 900px) {
      .hamburger { display: flex; }
      .nav-links  { display: none; }
      .nav-search { max-width: 260px; }
    }
    @media (max-width: 600px) {
      .nav-inner  { flex-wrap: wrap; height: auto; padding: 10px 16px; }
      .nav-search { max-width: 100%; order: 3; width: 100%; }
    }
  </style>
</head>
<body>

<!-- ── Sticky Navbar ─────────────────────────────────────────── -->
<header class="navbar" id="main-header">
  <div class="navbar-inner">
    <!-- Logo -->
    <a href="<?= APP_URL ?>/index.php" class="nav-logo">
      <div class="nav-logo-icon"></div>
      АВТО<span>ЗАПЧАСТЬ</span>
    </a>

    <!-- Search -->
    <div class="nav-search">
      <form class="nav-search-form" action="<?= APP_URL ?>/search/index.php" method="get">
        <input
          type="text"
          name="q"
          id="header-search"
          class="nav-search-input"
          placeholder="Номер детали или название..."
          autocomplete="off"
          value="<?= isset($_GET['q']) ? sanitize($_GET['q']) : '' ?>"
        >
        <button type="submit" class="nav-search-btn" title="Поиск">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
      </form>
      <div class="search-dropdown" id="search-dropdown"></div>
    </div>

    <!-- Nav links -->
    <ul class="nav-links" id="nav-links">
      <li><a href="<?= APP_URL ?>/catalog/index.php">Каталог</a></li>
      <?php if (hasRole(['manager','superadmin'])): ?>
        <li><a href="<?= APP_URL ?>/manager/index.php">Менеджер</a></li>
      <?php endif; ?>
      <?php if (hasRole(['admin','superadmin'])): ?>
        <li><a href="<?= APP_URL ?>/admin/index.php">Админ</a></li>
      <?php endif; ?>
      <?php if (hasRole('superadmin')): ?>
        <li><a href="<?= APP_URL ?>/superadmin/index.php">Супер</a></li>
      <?php endif; ?>
    </ul>

    <!-- Actions -->
    <div class="nav-actions">
      <?php if (isLoggedIn()): ?>
        <!-- Cart -->
        <a href="<?= APP_URL ?>/buyer/cart.php" class="cart-btn" title="Корзина">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
          Корзина
          <?php if ($cartCount > 0): ?>
            <span class="cart-badge" id="cart-badge"><?= $cartCount ?></span>
          <?php endif; ?>
        </a>
        <!-- User dropdown -->
        <div class="user-dropdown-wrap">
          <button class="user-btn" id="user-btn" onclick="toggleUserDropdown()">
            <div class="user-avatar"><?= mb_strtoupper(mb_substr($currentUser['username'] ?? 'U', 0, 1)) ?></div>
            <span><?= sanitize($currentUser['username'] ?? '') ?></span>
            <span class="role-badge <?= $currentUser['role'] ?>"><?= sanitize($currentUser['role']) ?></span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div class="user-dropdown" id="user-dropdown">
            <a href="<?= APP_URL ?>/buyer/index.php">Мой кабинет</a>
            <a href="<?= APP_URL ?>/buyer/orders.php">Мои заказы</a>
            <a href="<?= APP_URL ?>/buyer/profile.php">Профиль</a>
            <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= APP_URL ?>/auth/login.php" class="btn-login">Войти</a>
        <a href="<?= APP_URL ?>/auth/register.php" class="btn-register">Регистрация</a>
      <?php endif; ?>
      <button class="hamburger" id="hamburger" onclick="toggleMobileMenu()">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<!-- Secondary nav -->
<nav class="subnav">
  <div class="subnav-inner">
    <a href="<?= APP_URL ?>/catalog/index.php?category=dvigatel">Двигатель</a>
    <a href="<?= APP_URL ?>/catalog/index.php?category=tormoznaya-sistema">Тормоза</a>
    <a href="<?= APP_URL ?>/catalog/index.php?category=podveska">Подвеска</a>
    <a href="<?= APP_URL ?>/catalog/index.php?category=elektrika">Электрика</a>
    <a href="<?= APP_URL ?>/catalog/index.php?category=kuzov">Кузов</a>
    <a href="<?= APP_URL ?>/catalog/index.php?category=transmissiya">Трансмиссия</a>
  </div>
</nav>

<!-- Flash messages -->
<?php if ($flash): ?>
<div class="flash-wrap" id="flash-container">
  <div class="flash flash-<?= sanitize($flash['type']) ?>">
    <span><?= sanitize($flash['message']) ?></span>
    <span class="flash-dismiss" onclick="this.parentElement.parentElement.remove()">✕</span>
  </div>
</div>
<?php endif; ?>

<!-- Main content wrapper -->
<main id="main-content">
