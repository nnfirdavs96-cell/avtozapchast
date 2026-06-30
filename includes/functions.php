<?php
/**
 * АвтоЗапчасть - Helper Functions
 */

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data from DB (cached in session)
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    if (isset($_SESSION['user_data'])) return $_SESSION['user_data'];

    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT id, username, email, role, phone, avatar_path, is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        // avatar_path column may not exist yet (migration not run) — fall back
        $stmt = $db->prepare("SELECT id, username, email, role, phone, is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    if ($user) {
        $_SESSION['user_data'] = $user;
        return $user;
    }
    // User deactivated
    session_destroy();
    return null;
}

/**
 * Check if current user has specific role(s)
 */
function hasRole($role): bool {
    if (!isLoggedIn()) return false;
    $roles = is_array($role) ? $role : [$role];
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

/**
 * Require specific role(s) - redirect if missing
 */
/**
 * Панель управления доступна только на ADMIN_PORT.
 * На любом другом порту разделы admin/manager/superadmin отдают 403,
 * чтобы случайный посетитель не попал в админку с основного адреса.
 */
function requireAdminPort(): void {
    if (!defined('ADMIN_PORT') || ADMIN_PORT === '') return;
    $port = $_SERVER['SERVER_PORT'] ?? '';
    if ($port === '' || (string)$port === (string)ADMIN_PORT) return;
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><html lang="ru"><meta charset="utf-8">'
        . '<title>403 — Доступ запрещён</title>'
        . '<div style="font-family:Arial,sans-serif;text-align:center;padding:90px 20px;color:#222">'
        . '<div style="font-size:72px;font-weight:900;color:#d32f2f;line-height:1">403</div>'
        . '<p style="font-size:18px;margin-top:14px">Доступ к панели управления с этого адреса запрещён.</p>'
        . '<p style="font-size:14px;color:#888">Панель управления открывается по отдельному адресу.</p>'
        . '</div></html>');
}

/**
 * Render the Mazlay-styled 403 page and stop. Used when an authenticated
 * user lacks rights (role or per-section permission). Must be called before
 * any page output — all admin gates run at the top of the page, so this is
 * safe. Replaces the old «flash + redirect to storefront slider».
 */
function denyAccess(): void {
    http_response_code(403);
    require dirname(__DIR__) . '/pages/403.php';
    exit;
}

function requireRole($role): void {
    $roles = is_array($role) ? $role : [$role];
    if (array_intersect($roles, ['admin', 'manager', 'superadmin'])) {
        requireAdminPort();
    }
    if (!isLoggedIn()) {
        redirect(APP_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    // superadmin has access everywhere
    if (in_array('superadmin', $roles, true) || $_SESSION['role'] === 'superadmin') {
        if ($_SESSION['role'] === 'superadmin') return;
    }
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        denyAccess();
    }
}

/**
 * Catalog of permission-controlled sections.
 * Any of these can be delegated by the superadmin to ANY staff
 * user (admin or manager). key => label.
 */
function permissionSections(): array {
    return [
        'products'   => 'Товары / Запчасти',
        'markup'     => 'Себестоимость и наценка',
        'categories' => 'Категории',
        'brands'     => 'Бренды',
        'warehouse'  => 'Склад API',
        'vin'        => 'VIN-поиск',
        'orders'     => 'Заказы',
        'delivery'   => 'Доставка',
        'sliders'    => 'Слайдер / Баннеры',
        'blog'       => 'Блог',
        'pages'      => 'Страницы (CMS)',
        'reviews'    => 'Отзывы',
        'users'      => 'Пользователи',
        'settings'   => 'Настройки сайта',
        'currencies' => 'Валюты',
        'languages'  => 'Языки',
    ];
    // NOTE: 'permissions' (Права доступа) and 'backup' (Бэкапы) are intentionally
    // NOT delegatable — they stay superadmin-only for security.
}

/**
 * Sidebar keys that map onto a permission section
 * (admin uses "products", manager uses "parts" for the same area).
 */
function permissionAlias(string $key): string {
    if ($key === 'parts')   return 'products';
    if ($key === 'banners') return 'sliders';
    return $key;
}

/**
 * Sections a role can reach by DEFAULT, when the superadmin has NOT
 * configured the user. Mirrors the historical behaviour exactly so
 * deploying changes nothing until permissions are explicitly set.
 */
function roleDefaultSections(string $role): array {
    if ($role === 'admin') {
        return ['products','markup','sliders','orders','users',
                'categories','brands','blog','pages','reviews','vin'];
    }
    if ($role === 'manager') {
        return ['products','markup','categories','brands','blog','pages','reviews'];
    }
    return [];
}

/**
 * Raw configured section list for a user, or NULL when the
 * superadmin has never set it (→ role defaults apply).
 */
function getUserConfiguredSections(int $userId): ?array {
    static $cache = [];
    if (array_key_exists($userId, $cache)) return $cache[$userId];
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT sections FROM user_permissions WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) return $cache[$userId] = null;
        $list = json_decode((string)$row['sections'], true);
        return $cache[$userId] = (is_array($list) ? $list : []);
    } catch (PDOException $e) {
        // Table not migrated yet → defaults apply
        return $cache[$userId] = null;
    }
}

/**
 * Effective allowed sections = explicit config, else role defaults.
 */
function effectiveAllowedSections(int $userId, string $role): array {
    $cfg = getUserConfiguredSections($userId);
    return $cfg === null ? roleDefaultSections($role) : $cfg;
}

/**
 * Can the CURRENT user access a permission section?
 * superadmin: always. Otherwise: explicit config, else role default.
 */
function userCan(string $section): bool {
    if (!isLoggedIn()) return false;
    $role = $_SESSION['role'] ?? '';
    if ($role === 'superadmin') return true;
    $section = permissionAlias($section);
    return in_array(
        $section,
        effectiveAllowedSections((int)($_SESSION['user_id'] ?? 0), $role),
        true
    );
}

/**
 * Gate a page by permission section. Call AFTER requireRole(...)
 * so role/auth is already enforced; this narrows access by the
 * superadmin's per-user grant (default = role's historical access).
 */
function requirePermission(string $section): void {
    if (($_SESSION['role'] ?? '') === 'superadmin') return;
    if (userCan($section)) return;
    denyAccess();
}

/**
 * Redirect helper
 *
 * Relative targets ("/path") are anchored to APP_URL when it is set, so the
 * Location header is absolute and the reverse proxy cannot rewrite it to the
 * internal server IP.
 */
function redirect(string $url): void {
    if (defined('APP_URL') && APP_URL !== '' && $url !== '' && $url[0] === '/'
        && !preg_match('#^https?://#i', $url)) {
        $url = rtrim(APP_URL, '/') . $url;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Sanitize output
 */
function sanitize($input): string {
    if (is_array($input) || is_object($input)) {
        return '';
    }
    return htmlspecialchars((string)($input ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Render admin/superadmin/manager sidebar based on current user role.
 * $active is the key of the active link (e.g. 'sliders', 'settings', 'users').
 */
function renderRoleSidebar(string $active = ''): void {
    $user = getCurrentUser();
    $role = $user['role'] ?? '';
    $url  = APP_URL;

    if (!in_array($role, ['admin', 'manager', 'superadmin'], true)) return;

    // Role-dependent destinations (the rest are shared pages that allow all admin roles).
    $dashHref     = $role === 'superadmin' ? "$url/superadmin/index.php"
                  : ($role === 'admin' ? "$url/admin/index.php" : "$url/manager/index.php");
    $productsHref = $role === 'manager' ? "$url/manager/parts.php" : "$url/admin/products.php";
    $usersHref    = $role === 'superadmin' ? "$url/superadmin/users.php" : "$url/admin/users.php";

    // Single grouped catalog, rendered for every admin role and filtered by
    // userCan() — superadmin sees all; admin/manager see their granted sections.
    $groups = [
        ['label' => '', 'items' => [
            ['key' => 'dashboard',  'href' => $dashHref,                       'icon' => 'fa-tachometer',  'label' => 'Панель'],
        ]],
        ['label' => 'Каталог', 'items' => [
            ['key' => 'products',   'href' => $productsHref,                    'icon' => 'fa-cogs',        'label' => 'Товары'],
            ['key' => 'categories', 'href' => "$url/manager/categories.php",   'icon' => 'fa-sitemap',     'label' => 'Категории'],
            ['key' => 'brands',     'href' => "$url/manager/brands.php",       'icon' => 'fa-tag',         'label' => 'Бренды'],
            ['key' => 'warehouse',  'href' => "$url/superadmin/warehouse.php", 'icon' => 'fa-database',    'label' => 'Склад API'],
            ['key' => 'vin',        'href' => "$url/superadmin/vin.php",       'icon' => 'fa-search',      'label' => 'VIN-поиск'],
        ]],
        ['label' => 'Продажи', 'items' => [
            ['key' => 'orders',     'href' => "$url/admin/orders.php",         'icon' => 'fa-shopping-bag','label' => 'Заказы'],
            ['key' => 'delivery',   'href' => "$url/superadmin/delivery.php",  'icon' => 'fa-truck',       'label' => 'Доставка'],
        ]],
        ['label' => 'Контент', 'items' => [
            ['key' => 'sliders',    'href' => "$url/admin/sliders.php",        'icon' => 'fa-picture-o',   'label' => 'Слайдер'],
            ['key' => 'banners',    'href' => "$url/admin/banners.php",        'icon' => 'fa-clone',       'label' => 'Баннеры'],
            ['key' => 'blog',       'href' => "$url/manager/blog.php",         'icon' => 'fa-newspaper-o', 'label' => 'Блог'],
            ['key' => 'pages',      'href' => "$url/manager/pages.php",        'icon' => 'fa-file-text-o', 'label' => 'Страницы'],
            ['key' => 'reviews',    'href' => "$url/manager/reviews.php",      'icon' => 'fa-comments-o',  'label' => 'Отзывы'],
        ]],
        ['label' => 'Доступ', 'items' => [
            ['key' => 'users',      'href' => $usersHref,                      'icon' => 'fa-users',       'label' => 'Пользователи'],
            ['key' => 'permissions','href' => "$url/superadmin/permissions.php",'icon' => 'fa-shield',     'label' => 'Права доступа'],
        ]],
        ['label' => 'Система', 'items' => [
            ['key' => 'settings',   'href' => "$url/superadmin/settings.php",  'icon' => 'fa-cog',         'label' => 'Настройки'],
            ['key' => 'currencies', 'href' => "$url/superadmin/currencies.php",'icon' => 'fa-money',       'label' => 'Валюты'],
            ['key' => 'languages',  'href' => "$url/superadmin/languages.php", 'icon' => 'fa-language',    'label' => 'Языки'],
            ['key' => 'backup',     'href' => "$url/superadmin/backup.php",    'icon' => 'fa-archive',     'label' => 'Бэкапы'],
            ['key' => 'manual',     'href' => "$url/superadmin/manual.php",    'icon' => 'fa-book',        'label' => 'Руководство'],
        ]],
    ];

    // Unified staff branding: one panel identity for every back-office role
    // (superadmin / admin / manager). The role is shown as a small sub-label so
    // the layout стays identical while still telling staff who they are.
    $siteBrand = getSetting('site_name', 'AutoDoc');
    $roleLabel = $role === 'superadmin' ? 'Суперадмин'
               : ($role === 'admin' ? 'Администратор' : 'Менеджер');
    $logoHtml = '<div class="az-sidebar-logo">' . sanitize($siteBrand) . '<span>&nbsp;Панель</span></div>'
              . '<div class="az-sidebar-role"><i class="fa fa-id-badge"></i> ' . sanitize($roleLabel) . '</div>';

    // 'parts'/'partners' are legacy active-keys; normalize to the canonical item key.
    $activeKey = $active === 'parts' ? 'products' : ($active === 'partners' ? 'brands' : $active);

    // Visibility: dashboard always; permissions/backup/manual are superadmin-only;
    // everything else by userCan() (superadmin always passes).
    $canSee = function (string $key) use ($role): bool {
        if ($key === 'dashboard') return true;
        if ($role === 'superadmin') return true;
        if (in_array($key, ['permissions', 'backup', 'manual'], true)) return false;
        return userCan(permissionAlias($key));
    };

    echo '<aside class="az-sidebar">';
    echo $logoHtml;
    echo '<nav><ul>';
    foreach ($groups as $g) {
        $visible = array_values(array_filter($g['items'], fn($it) => $canSee($it['key'])));
        if (!$visible) continue;
        if ($g['label'] !== '') {
            echo '<li class="az-sidebar-group">' . sanitize($g['label']) . '</li>';
        }
        foreach ($visible as $it) {
            $cls = $it['key'] === $activeKey ? ' class="active"' : '';
            echo '<li><a href="' . sanitize($it['href']) . '"' . $cls . '><i class="fa ' . sanitize($it['icon']) . '"></i> ' . sanitize($it['label']) . '</a></li>';
        }
    }
    echo '<li class="az-sidebar-group">Аккаунт</li>';
    echo '<li><a href="' . $url . '/index.php"><i class="fa fa-home"></i> На сайт</a></li>';
    echo '<li><a href="' . $url . '/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;"><i class="fa fa-sign-out"></i> Выйти</a></li>';
    echo '</ul></nav></aside>';
}

/**
 * Generate CSRF token (store in session)
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 */
function flashMessage(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get flash message and clear it
 */
function getFlashMessage(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get cart item count for logged-in user
 */
function getCartCount(): int {
    // Гость и авторизованный — через единое хранилище корзины (cart_lib).
    try { return cartCountAny(getDB()); }
    catch (Exception $e) { return 0; }
}

/**
 * Get all active categories (flat list)
 */
function getCategories(): array {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get categories as nested tree
 */
function getCategoryTree(array $categories, ?int $parentId = null): array {
    $tree = [];
    foreach ($categories as $cat) {
        $catParent = $cat['parent_id'] === null ? null : (int)$cat['parent_id'];
        if ($catParent === $parentId) {
            $cat['children'] = getCategoryTree($categories, (int)$cat['id']);
            $tree[] = $cat;
        }
    }
    return $tree;
}

/**
 * Get all active brands
 */
function getBrands(): array {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get order status label in Russian
 */
function getOrderStatusLabel(string $status): string {
    $labels = [
        'pending'    => 'Новый',
        'processing' => 'В обработке',
        'shipped'    => 'Отправлен',
        'delivered'  => 'Доставлен',
        'cancelled'  => 'Отменён',
    ];
    return $labels[$status] ?? $status;
}

/**
 * Get order status CSS class
 */
function getOrderStatusClass(string $status): string {
    $classes = [
        'pending'    => 'warning',
        'processing' => 'info',
        'shipped'    => 'primary',
        'delivered'  => 'success',
        'cancelled'  => 'danger',
    ];
    return $classes[$status] ?? 'secondary';
}

/**
 * Format a shipping address for display.
 * Orders store the address as JSON (see buyer/checkout.php); decode it into
 * readable HTML. Falls back to the raw value if it isn't valid JSON.
 */
function formatShippingAddress(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') return '—';

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return nl2br(sanitize($raw));
    }

    $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
    $lines = [];
    if ($name !== '')           $lines[] = $name;
    if (!empty($data['phone'])) $lines[] = $data['phone'];
    if (!empty($data['email'])) $lines[] = $data['email'];

    $cityLine = trim(implode(', ', array_filter([
        $data['zip_code'] ?? '',
        $data['city']     ?? '',
        $data['country']  ?? '',
    ])));
    $addr = trim(implode(', ', array_filter([
        $data['address'] ?? '',
        $cityLine,
    ])));
    if ($addr !== '') $lines[] = $addr;

    if (empty($lines)) return '—';
    return implode('<br>', array_map('sanitize', $lines));
}

/**
 * Truncate string
 */
function truncate(string $str, int $len = 100, string $suffix = '...'): string {
    if (mb_strlen($str) <= $len) return $str;
    return mb_substr($str, 0, $len) . $suffix;
}

/**
 * Render a 0–5 star rating as Font Awesome icons
 */
function starsHtml(float $rating): string {
    $full = (int)floor($rating);
    $half = ($rating - $full) >= 0.5;
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full) {
            $html .= '<i class="fa fa-star" style="color:#f5a623;"></i>';
        } elseif ($i === $full + 1 && $half) {
            $html .= '<i class="fa fa-star-half-o" style="color:#f5a623;"></i>';
        } else {
            $html .= '<i class="fa fa-star-o" style="color:#ccc;"></i>';
        }
    }
    return $html;
}

/**
 * Aggregate approved-review rating for a set of products.
 * Returns [part_id => ['avg' => float, 'count' => int]]
 */
function getProductRatings(array $partIds): array {
    $partIds = array_values(array_unique(array_map('intval', $partIds)));
    if (empty($partIds)) return [];
    $in  = implode(',', array_fill(0, count($partIds), '?'));
    $db  = getDB();
    try {
        $st = $db->prepare(
            "SELECT part_id, AVG(rating) avg_r, COUNT(*) cnt
             FROM product_reviews
             WHERE status='approved' AND part_id IN ($in)
             GROUP BY part_id"
        );
        $st->execute($partIds);
    } catch (PDOException $e) {
        // Reviews migration not applied yet — degrade gracefully
        return [];
    }
    $out = [];
    foreach ($st as $row) {
        $out[(int)$row['part_id']] = ['avg' => round((float)$row['avg_r'], 1), 'count' => (int)$row['cnt']];
    }
    return $out;
}

/**
 * Compact rating line for product cards. Returns '' if no approved reviews.
 */
function productStarsInline(int $partId, array $ratings): string {
    if (empty($ratings[$partId]) || $ratings[$partId]['count'] < 1) return '';
    $r = $ratings[$partId];
    return '<div class="product_rating" style="margin:4px 0 2px;font-size:0.82rem;white-space:nowrap;">'
        . starsHtml((float)$r['avg'])
        . '<span style="color:#999;margin-left:5px;">(' . $r['count'] . ')</span>'
        . '</div>';
}

/**
 * Has the user actually bought this part in a delivered order?
 */
function userPurchasedPart(int $userId, int $partId): bool {
    if ($userId <= 0 || $partId <= 0) return false;
    $db = getDB();
    $st = $db->prepare(
        "SELECT 1
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = ? AND oi.part_id = ? AND o.status = 'delivered'
         LIMIT 1"
    );
    $st->execute([$userId, $partId]);
    return (bool)$st->fetchColumn();
}

/**
 * Approved shop-review summary: ['avg' => float, 'count' => int]
 */
function getShopRatingSummary(): array {
    $db = getDB();
    try {
        $row = $db->query(
            "SELECT AVG(rating) avg_r, COUNT(*) cnt FROM shop_reviews WHERE status='approved'"
        )->fetch();
    } catch (PDOException $e) {
        return ['avg' => 0.0, 'count' => 0];
    }
    return [
        'avg'   => $row && $row['cnt'] ? round((float)$row['avg_r'], 1) : 0.0,
        'count' => $row ? (int)$row['cnt'] : 0,
    ];
}

/**
 * Get stock status label
 */
function getStockStatus(int $stock): array {
    if ($stock <= 0)  return ['label' => t('out_of_stock'), 'class' => 'danger'];
    if ($stock <= 5)  return ['label' => t('low_stock'),    'class' => 'warning'];
    return ['label' => t('in_stock'), 'class' => 'success'];
}

/**
 * Скидка в процентах, если old_price > price. Иначе 0.
 */
function discountPercent(array $part): int {
    $old   = (float)($part['old_price'] ?? 0);
    $price = (float)($part['price'] ?? 0);
    if ($old > 0 && $old > $price) {
        return (int)round(($old - $price) / $old * 100);
    }
    return 0;
}

/**
 * Товар «новый», если добавлен за последние $days дней.
 */
function isNewProduct(array $part, int $days = 30): bool {
    if (empty($part['created_at'])) return false;
    $ts = strtotime((string)$part['created_at']);
    return $ts && $ts >= strtotime("-{$days} days");
}

/**
 * Единый бейдж для карточки товара внутри .product_thumb.
 * Приоритет: скидка → новинка → нет в наличии → заканчивается.
 */
function productBadges(array $part): string {
    $disc  = discountPercent($part);
    $stock = (int)($part['stock'] ?? 0);
    if ($disc > 0) {
        return '<div class="label_product"><span class="label_sale">-' . $disc . '%</span></div>';
    }
    if (isNewProduct($part)) {
        return '<div class="label_product"><span class="label_new">' . sanitize(t('new_label')) . '</span></div>';
    }
    if ($stock <= 0) {
        return '<div class="label_product"><span class="label_sale">' . sanitize(t('out_of_stock')) . '</span></div>';
    }
    if ($stock <= 5) {
        return '<div class="label_product"><span class="label_new">' . sanitize(t('low_stock')) . '</span></div>';
    }
    return '';
}

/**
 * HTML блока цены: при скидке показывает зачёркнутую старую цену + новую.
 */
function priceBox(array $part): string {
    $disc = discountPercent($part);
    $cur  = '<span class="current_price">' . formatPrice($part['price']) . '</span>';
    if ($disc > 0) {
        return '<div class="price_box"><span class="old_price">' . formatPrice($part['old_price']) . '</span> ' . $cur . '</div>';
    }
    return '<div class="price_box">' . $cur . '</div>';
}

/**
 * Get wishlist count for logged-in user
 */
function getWishlistCount(): int {
    if (!isLoggedIn()) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

/**
 * Returns the effective markup % for a product.
 * Priority: product-level → category-level → global setting.
 */
function getEffectiveMarkup(int $partId, int $categoryId): float {
    try {
        $db = getDB();
        if ($partId > 0) {
            $s = $db->prepare("SELECT markup_percent FROM parts WHERE id = ? AND markup_percent IS NOT NULL LIMIT 1");
            $s->execute([$partId]);
            $v = $s->fetchColumn();
            if ($v !== false) return (float)$v;
        }
        if ($categoryId > 0) {
            $s = $db->prepare("SELECT markup_percent FROM categories WHERE id = ? AND markup_percent IS NOT NULL LIMIT 1");
            $s->execute([$categoryId]);
            $v = $s->fetchColumn();
            if ($v !== false) return (float)$v;
        }
    } catch (Exception $e) {}
    return (float)getSetting('global_markup', '0');
}

/**
 * Get site setting value
 */
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT value FROM site_settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['value'] : $default;
        return $cache[$key];
    } catch (Exception $e) { return $default; }
}

/**
 * Persist a setting in site_settings (upsert).
 */
function setSetting(string $key, string $value): void {
    try {
        $db = getDB();
        $db->prepare(
            "INSERT INTO site_settings (`key`, `value`) VALUES (?,?)
             ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()"
        )->execute([$key, $value, $value]);
    } catch (Exception $e) { /* silent */ }
}

/**
 * Надёжный HTTP GET. Приоритет — cURL (несёт собственный CA-bundle и работает на
 * shared-хостинге, где allow_url_fopen выключен либо у stream-обёртки нет CA),
 * фолбэк — file_get_contents. Именно поэтому интеграции (PartsAPI и др.) должны
 * ходить через этот хелпер, а не напрямую через file_get_contents: на ряде
 * хостингов file_get_contents по HTTPS молча возвращает false на КАЖДОМ запросе.
 *
 * Возвращает ['body'=>string, 'status'=>int, 'error'=>string, 'transport'=>string].
 *   status 0 + непустой error  → транспортный сбой (DNS/TLS/таймаут/блокировка);
 *   status 200 + пустой error   → ответ получен (его уже разбирает вызывающий код).
 */
function httpGet(string $url, int $timeout = 12, array $headers = []): array {
    $timeout = max(2, min(60, $timeout));
    $headers = $headers ?: ['Accept: application/json'];
    $curlError = '';

    if (function_exists('curl_init')) {
        $do = function (bool $verify) use ($url, $timeout, $headers) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'AvtoZapchast/1.0',
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $body   = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            $errno  = curl_errno($ch);
            curl_close($ch);
            return [$body, $status, $err, $errno];
        };

        [$body, $status, $err, $errno] = $do(true);
        // 60/51/35 = проблемы проверки TLS-сертификата на кривом хостинге → ретрай без проверки.
        if ($body === false && in_array($errno, [60, 51, 35], true)) {
            [$body, $status, $err] = $do(false);
        }
        if ($body !== false) {
            return ['body' => (string)$body, 'status' => $status, 'error' => '', 'transport' => 'curl'];
        }
        $curlError = $err !== '' ? "cURL: $err" : 'cURL request failed';
    }

    // Фолбэк: stream-обёртка file_get_contents (если включён allow_url_fopen).
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'header'        => implode("\r\n", $headers) . "\r\nUser-Agent: AvtoZapchast/1.0\r\n",
            ],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body   = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $status = (int)$m[1]; break; }
        }
        if ($body !== false) {
            return ['body' => (string)$body, 'status' => $status, 'error' => '', 'transport' => 'fopen'];
        }
        return ['body' => '', 'status' => 0,
                'error' => $curlError !== '' ? $curlError : 'file_get_contents вернул false (allow_url_fopen включён, но запрос не прошёл)',
                'transport' => 'fopen'];
    }

    return ['body' => '', 'status' => 0,
            'error' => $curlError !== '' ? $curlError : 'Нет HTTP-транспорта: cURL отсутствует и allow_url_fopen=Off',
            'transport' => 'none'];
}

/**
 * Full catalog of supported phone countries (single source of truth, shared with JS).
 * mask: 'X' = digit placeholder, остальные символы — литералы разделителей.
 */
function phoneCountriesCatalog(): array {
    return [
        // СНГ и соседи
        ['code'=>'tj','dial'=>'992','flag'=>'🇹🇯','name'=>'Таджикистан','mask'=>'(XX) XXX-XX-XX'],
        ['code'=>'ru','dial'=>'7',  'flag'=>'🇷🇺','name'=>'Россия',     'mask'=>'(XXX) XXX-XX-XX'],
        ['code'=>'uz','dial'=>'998','flag'=>'🇺🇿','name'=>'Узбекистан', 'mask'=>'(XX) XXX-XX-XX'],
        ['code'=>'kz','dial'=>'7',  'flag'=>'🇰🇿','name'=>'Казахстан',  'mask'=>'(XXX) XXX-XX-XX'],
        ['code'=>'kg','dial'=>'996','flag'=>'🇰🇬','name'=>'Киргизия',   'mask'=>'(XXX) XXX-XXX'],
        ['code'=>'tm','dial'=>'993','flag'=>'🇹🇲','name'=>'Туркменистан','mask'=>'(XX) XX-XX-XX'],
        ['code'=>'az','dial'=>'994','flag'=>'🇦🇿','name'=>'Азербайджан','mask'=>'(XX) XXX-XX-XX'],
        ['code'=>'am','dial'=>'374','flag'=>'🇦🇲','name'=>'Армения',    'mask'=>'(XX) XXX-XXX'],
        ['code'=>'ge','dial'=>'995','flag'=>'🇬🇪','name'=>'Грузия',     'mask'=>'(XXX) XXX-XXX'],
        ['code'=>'by','dial'=>'375','flag'=>'🇧🇾','name'=>'Беларусь',   'mask'=>'(XX) XXX-XX-XX'],
        ['code'=>'ua','dial'=>'380','flag'=>'🇺🇦','name'=>'Украина',    'mask'=>'(XX) XXX-XX-XX'],
        ['code'=>'md','dial'=>'373','flag'=>'🇲🇩','name'=>'Молдова',    'mask'=>'(XX) XXX-XXX'],
        // Азия и Ближний Восток
        ['code'=>'cn','dial'=>'86', 'flag'=>'🇨🇳','name'=>'Китай',      'mask'=>'XXX XXXX-XXXX'],
        ['code'=>'in','dial'=>'91', 'flag'=>'🇮🇳','name'=>'Индия',      'mask'=>'XXXXX-XXXXX'],
        ['code'=>'pk','dial'=>'92', 'flag'=>'🇵🇰','name'=>'Пакистан',   'mask'=>'(XXX) XXX-XXXX'],
        ['code'=>'af','dial'=>'93', 'flag'=>'🇦🇫','name'=>'Афганистан', 'mask'=>'(XX) XXX-XXXX'],
        ['code'=>'ir','dial'=>'98', 'flag'=>'🇮🇷','name'=>'Иран',       'mask'=>'(XXX) XXX-XXXX'],
        ['code'=>'tr','dial'=>'90', 'flag'=>'🇹🇷','name'=>'Турция',     'mask'=>'(XXX) XXX-XX-XX'],
        ['code'=>'ae','dial'=>'971','flag'=>'🇦🇪','name'=>'ОАЭ',        'mask'=>'(XX) XXX-XXXX'],
        ['code'=>'sa','dial'=>'966','flag'=>'🇸🇦','name'=>'Саудовская Аравия','mask'=>'(XX) XXX-XXXX'],
        ['code'=>'kr','dial'=>'82', 'flag'=>'🇰🇷','name'=>'Южная Корея','mask'=>'(XX) XXXX-XXXX'],
        ['code'=>'jp','dial'=>'81', 'flag'=>'🇯🇵','name'=>'Япония',     'mask'=>'(XX) XXXX-XXXX'],
        ['code'=>'th','dial'=>'66', 'flag'=>'🇹🇭','name'=>'Таиланд',    'mask'=>'(XX) XXX-XXXX'],
        ['code'=>'vn','dial'=>'84', 'flag'=>'🇻🇳','name'=>'Вьетнам',    'mask'=>'(XXX) XXX-XXXX'],
        ['code'=>'my','dial'=>'60', 'flag'=>'🇲🇾','name'=>'Малайзия',   'mask'=>'(XX) XXX-XXXX'],
        ['code'=>'id','dial'=>'62', 'flag'=>'🇮🇩','name'=>'Индонезия',  'mask'=>'(XXX) XXX-XXXX'],
        // Европа
        ['code'=>'de','dial'=>'49', 'flag'=>'🇩🇪','name'=>'Германия',   'mask'=>'(XXX) XXXX-XXXX'],
        ['code'=>'fr','dial'=>'33', 'flag'=>'🇫🇷','name'=>'Франция',    'mask'=>'(X) XX-XX-XX-XX'],
        ['code'=>'gb','dial'=>'44', 'flag'=>'🇬🇧','name'=>'Великобритания','mask'=>'XXXX XXXXXX'],
        ['code'=>'it','dial'=>'39', 'flag'=>'🇮🇹','name'=>'Италия',     'mask'=>'(XXX) XXX-XXXX'],
        ['code'=>'es','dial'=>'34', 'flag'=>'🇪🇸','name'=>'Испания',    'mask'=>'(XXX) XX-XX-XX'],
        ['code'=>'pl','dial'=>'48', 'flag'=>'🇵🇱','name'=>'Польша',     'mask'=>'(XXX) XXX-XXX'],
        ['code'=>'nl','dial'=>'31', 'flag'=>'🇳🇱','name'=>'Нидерланды', 'mask'=>'(XX) XXX-XXXX'],
        ['code'=>'cz','dial'=>'420','flag'=>'🇨🇿','name'=>'Чехия',      'mask'=>'(XXX) XXX-XXX'],
        // Америка и прочее
        ['code'=>'us','dial'=>'1',  'flag'=>'🇺🇸','name'=>'США',        'mask'=>'(XXX) XXX-XXXX'],
        ['code'=>'ca','dial'=>'1',  'flag'=>'🇨🇦','name'=>'Канада',     'mask'=>'(XXX) XXX-XXXX'],
        ['code'=>'br','dial'=>'55', 'flag'=>'🇧🇷','name'=>'Бразилия',   'mask'=>'(XX) XXXXX-XXXX'],
        ['code'=>'eg','dial'=>'20', 'flag'=>'🇪🇬','name'=>'Египет',     'mask'=>'(XX) XXXX-XXXX'],
    ];
}

/**
 * Countries enabled for the phone selector (configured in superadmin settings,
 * stored as a comma-separated list of codes; defaults to Tajikistan only).
 */
function enabledPhoneCountries(): array {
    $enabled = array_filter(array_map('trim', explode(',', getSetting('phone_countries', 'tj'))));
    $catalog = phoneCountriesCatalog();
    $out = [];
    foreach ($enabled as $code) {
        foreach ($catalog as $c) { if ($c['code'] === $code) { $out[] = $c; break; } }
    }
    return $out ?: [$catalog[0]];
}

/**
 * Get mini cart items for logged-in user
 */
function getMiniCart(): array {
    // Гость и авторизованный — через единое хранилище корзины (cart_lib).
    try {
        $items = cartDetailedItems(getDB());
        return array_slice($items, 0, 5);
    } catch (Exception $e) { return []; }
}

/**
 * Get mini cart total in RUB
 */
function getMiniCartTotal(): float {
    // Гость и авторизованный — через единое хранилище корзины (cart_lib).
    try { return cartTotalAny(getDB()); }
    catch (Exception $e) { return 0; }
}

/**
 * Build breadcrumb array
 */
function breadcrumb(array $items): string {
    $html  = '<!--breadcrumbs area start-->';
    $html .= '<div class="breadcrumbs_area"><div class="container"><div class="row"><div class="col-12"><div class="breadcrumb_content"><ul>';
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        if ($i === $last || empty($item['url'])) {
            $html .= '<li>' . sanitize($item['label']) . '</li>';
        } else {
            $html .= '<li><a href="' . sanitize($item['url']) . '">' . sanitize($item['label']) . '</a></li>';
        }
    }
    $html .= '</ul></div></div></div></div></div>';
    $html .= '<!--breadcrumbs area end-->';
    return $html;
}

/**
 * Horizontal account navigation for buyer storefront pages
 * (dashboard / orders / profile / cart / wishlist).
 * $active = 'dashboard' | 'orders' | 'profile' | 'cart' | 'wishlist'
 */
function renderBuyerAccountNav(string $active = ''): string {
    $url   = APP_URL;
    $items = [
        ['key' => 'dashboard', 'href' => "$url/buyer/index.php",    'icon' => 'fa-th-large',      'label' => t('dashboard')],
        ['key' => 'orders',    'href' => "$url/buyer/orders.php",   'icon' => 'fa-list-alt',      'label' => 'Мои заказы'],
        ['key' => 'profile',   'href' => "$url/buyer/profile.php",  'icon' => 'fa-user-o',        'label' => 'Профиль'],
        ['key' => 'cart',      'href' => "$url/buyer/cart.php",     'icon' => 'fa-shopping-cart', 'label' => t('shopping_cart')],
        ['key' => 'wishlist',  'href' => "$url/buyer/wishlist.php", 'icon' => 'fa-heart-o',       'label' => t('wishlist')],
    ];
    $h = '<nav class="az-account-nav">';
    foreach ($items as $it) {
        $cls = $it['key'] === $active ? ' class="active"' : '';
        $h  .= '<a href="' . sanitize($it['href']) . '"' . $cls . '>'
             . '<i class="fa ' . sanitize($it['icon']) . '"></i> '
             . sanitize($it['label']) . '</a>';
    }
    $h .= '<a class="az-account-nav-out" href="' . $url . '/auth/logout.php">'
        . '<i class="fa fa-sign-out"></i> ' . sanitize(t('logout')) . '</a>';
    $h .= '</nav>';
    return $h;
}

/**
 * Paginate query results
 */
function paginate(string $countSql, string $dataSql, array $params, int $page, int $perPage = 12): array {
    $db    = getDB();
    $total = (int)$db->prepare($countSql)->execute($params) ? $db->prepare($countSql)->execute($params) : 0;
    // Count
    $cStmt = $db->prepare($countSql);
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();
    $pages = max(1, ceil($total / $perPage));
    $page  = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    // Data
    $dStmt = $db->prepare($dataSql . " LIMIT $perPage OFFSET $offset");
    $dStmt->execute($params);
    return [
        'items'   => $dStmt->fetchAll(),
        'total'   => $total,
        'pages'   => $pages,
        'current' => $page,
        'perPage' => $perPage,
    ];
}

/**
 * Generate pagination HTML
 */
function paginationHtml(array $page, string $baseUrl): string {
    if ($page['pages'] <= 1) return '';
    $html = '<div class="paginatoin-area"><div class="row"><div class="col-12"><div class="pagination-box"><ul class="pagination">';
    for ($i = 1; $i <= $page['pages']; $i++) {
        $active = $i === $page['current'] ? ' active' : '';
        $sep    = strpos($baseUrl, '?') !== false ? '&' : '?';
        $html  .= '<li class="' . $active . '"><a href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '</ul></div></div></div></div>';
    return $html;
}

/**
 * Product image URL
 */
function productImageUrl($images, int $index = 0): string {
    if (is_string($images)) $images = json_decode($images, true);
    if (!empty($images[$index])) {
        $img = $images[$index];
        // Уже абсолютный URL или корневой путь — возвращаем как есть
        if (preg_match('#^(https?:)?//#i', $img) || str_starts_with($img, '/')) {
            return $img;
        }
        // Относительный путь вида "products/foo.jpg" — добавляем UPLOAD_URL
        return UPLOAD_URL . ltrim($img, '/');
    }
    return APP_URL . '/assets/img/product/placeholder.jpg';
}

/**
 * Transliterate a (Cyrillic) name into a URL slug.
 */
function categorySlugify(string $name): string {
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
        'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
        'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $s = mb_strtolower(trim($name), 'UTF-8');
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'cat';
}

/**
 * Build an SEO-friendly product URL: /product/{id}-{slug}.
 * Accepts a part row (array with id/name) or a plain numeric id. The slug part is
 * purely cosmetic — routing matches on the numeric id (see .htaccess), so the old
 * /catalog/part.php?id=N links keep working forever. Falls back to the catalog
 * listing when the id is missing.
 */
function partUrl($part, string $name = ''): string {
    if (is_array($part)) {
        $id   = (int)($part['id'] ?? 0);
        $name = $name !== '' ? $name : (string)($part['name'] ?? '');
    } else {
        $id = (int)$part;
    }
    if ($id <= 0) return APP_URL . '/catalog/index.php';
    $slug = $name !== '' ? categorySlugify(mb_substr($name, 0, 80)) : '';
    if (strlen($slug) > 60) $slug = trim(substr($slug, 0, 60), '-');
    return APP_URL . '/product/' . $id . ($slug !== '' ? '-' . $slug : '');
}

/**
 * Seed subcategories under the existing top-level categories so the
 * "ВСЕ КАТЕГОРИИ" mega-menu and catalog look populated. Idempotent: matches
 * parents by name, skips subcategories that already exist. Returns count added.
 */
function seedCategorySubcategories(): int {
    $plan = [
        'Двигатель'         => ['Поршни и кольца','Клапаны','Прокладки ГБЦ','Масляный насос','Ремни ГРМ','Масляные фильтры'],
        'Тормозная система' => ['Тормозные колодки','Тормозные диски','Суппорты','Тормозные шланги','Тормозная жидкость'],
        'Подвеска'          => ['Амортизаторы','Пружины','Рычаги','Шаровые опоры','Сайлентблоки','Стойки стабилизатора'],
        'Электрика'         => ['Аккумуляторы','Стартеры','Генераторы','Свечи зажигания','Датчики','Реле и предохранители'],
        'Кузов'             => ['Бамперы','Капоты','Крылья','Зеркала','Фары','Решётки радиатора'],
        'Трансмиссия'       => ['Сцепление','Маховики','ШРУСы','Карданные валы','Подшипники ступицы'],
    ];
    try {
        $db = getDB();
        $n  = 0;
        foreach ($plan as $parentName => $subs) {
            $st = $db->prepare("SELECT id FROM categories WHERE name = ? AND parent_id IS NULL LIMIT 1");
            $st->execute([$parentName]);
            $parentId = $st->fetchColumn();
            if (!$parentId) continue;
            $sort = 0;
            foreach ($subs as $subName) {
                $sort++;
                $chk = $db->prepare("SELECT id FROM categories WHERE name = ? AND parent_id = ? LIMIT 1");
                $chk->execute([$subName, $parentId]);
                if ($chk->fetchColumn()) continue;
                $slug = categorySlugify($subName);
                $base = $slug; $i = 1;
                while (true) {
                    $s = $db->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
                    $s->execute([$slug]);
                    if (!$s->fetchColumn()) break;
                    $slug = $base . '-' . (++$i);
                }
                try {
                    $db->prepare(
                        "INSERT INTO categories (name, slug, parent_id, description, image_path, image_path_mobile, sort_order, is_active, markup_percent)
                         VALUES (?,?,?,?,?,?,?,1,NULL)"
                    )->execute([$subName, $slug, (int)$parentId, null, null, '', $sort]);
                    $n++;
                } catch (Exception $e) { /* skip this one, keep going */ }
            }
        }
        return $n;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Assign a real-looking catalog photo to any product that has no image yet.
 * Idempotent: only touches rows with empty images, cycles through the theme
 * photos product1..product13.jpg (stored as root-relative paths). Returns the
 * number of products updated.
 */
function fillMissingProductImages(): int {
    try {
        $db   = getDB();
        $ids  = $db->query(
            "SELECT id FROM parts
             WHERE images IS NULL OR images = '' OR images = '[]' OR images = 'null'"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return 0;
        $upd = $db->prepare("UPDATE parts SET images = ? WHERE id = ?");
        $n = 0;
        foreach ($ids as $id) {
            $imgN = ((int)$id % 13) + 1;   // product1.jpg .. product13.jpg
            $json = json_encode(['/assets/img/product/product' . $imgN . '.jpg']);
            $upd->execute([$json, (int)$id]);
            $n++;
        }
        return $n;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Seed a catalog of popular auto-parts manufacturers (idempotent).
 * Skips brands that already exist by name. Returns count added.
 */
function seedBrands(): int {
    $brands = [
        ['Bosch','Германия'], ['Brembo','Италия'], ['Denso','Япония'],
        ['Febi','Германия'], ['Gates','США'], ['Monroe','Бельгия'],
        ['NGK','Япония'], ['SKF','Швеция'], ['Mann-Filter','Германия'],
        ['Mahle','Германия'], ['Sachs','Германия'], ['TRW','Германия'],
        ['Valeo','Франция'], ['Continental','Германия'], ['LuK','Германия'],
        ['Hella','Германия'], ['Lemförder','Германия'], ['ATE','Германия'],
        ['Mobil','США'], ['Castrol','Великобритания'], ['Liqui Moly','Германия'],
        ['Aisin','Япония'], ['KYB','Япония'], ['Exedy','Япония'],
        ['Delphi','Великобритания'], ['Optimal','Германия'], ['Ruville','Германия'],
        ['Zimmermann','Германия'], ['Nipparts','Нидерланды'], ['Blue Print','Великобритания'],
    ];
    try {
        $db = getDB();
        $n  = 0;
        $sort = 0;
        foreach ($brands as [$name, $country]) {
            $sort++;
            $chk = $db->prepare("SELECT id FROM brands WHERE name = ? LIMIT 1");
            $chk->execute([$name]);
            if ($chk->fetchColumn()) continue;
            $slug = categorySlugify($name);
            $base = $slug; $i = 1;
            while (true) {
                $s = $db->prepare("SELECT id FROM brands WHERE slug = ? LIMIT 1");
                $s->execute([$slug]);
                if (!$s->fetchColumn()) break;
                $slug = $base . '-' . (++$i);
            }
            try {
                $db->prepare(
                    "INSERT INTO brands (name, slug, country, description, logo_path, is_active, sort_order)
                     VALUES (?,?,?,?,?,1,?)"
                )->execute([$name, $slug, $country, null, null, $sort]);
                $n++;
            } catch (Exception $e) {
                try {
                    $db->prepare(
                        "INSERT INTO brands (name, slug, country, description, logo_path, is_active)
                         VALUES (?,?,?,?,?,1)"
                    )->execute([$name, $slug, $country, null, null]);
                    $n++;
                } catch (Exception $e2) { /* skip */ }
            }
        }
        return $n;
    } catch (Exception $e) {
        return 0;
    }
}

/* ───────────────────────── Phone / SMS authentication ───────────────────────── */

/**
 * Ensure the DB schema supports phone-based (SMS) registration.
 * Idempotent, runs once per deploy (guarded by a settings flag):
 *  - email / password_hash become NULLable (phone-only accounts have neither)
 *  - phone_e164 column added (normalized digits, the canonical login key)
 *  - phone_otp table created (one-time SMS codes)
 */
function ensurePhoneAuthSchema(): void {
    if (getSetting('phone_auth_schema_v1', '') === '1') return;
    try {
        $db = getDB();
        // email: drop NOT NULL (keep the unique index — MySQL allows multiple NULLs)
        $col = $db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email'")
                  ->fetchColumn();
        if ($col === 'NO') {
            $db->exec("ALTER TABLE `users` MODIFY `email` VARCHAR(180) NULL");
        }
        // password_hash: drop NOT NULL
        $col = $db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash'")
                  ->fetchColumn();
        if ($col === 'NO') {
            $db->exec("ALTER TABLE `users` MODIFY `password_hash` VARCHAR(255) NULL");
        }
        // phone_e164: canonical normalized phone for reliable lookup
        dbAddColumnIfMissing($db, 'users', 'phone_e164',
            "`phone_e164` VARCHAR(20) DEFAULT NULL AFTER `phone`");
        try { $db->exec("CREATE INDEX `idx_phone_e164` ON `users` (`phone_e164`)"); } catch (Exception $e) {}
        // OTP table
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `phone_otp` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `phone`       VARCHAR(20)  NOT NULL,
                `code_hash`   VARCHAR(255) NOT NULL,
                `purpose`     VARCHAR(20)  NOT NULL DEFAULT 'login',
                `attempts`    TINYINT      NOT NULL DEFAULT 0,
                `expires_at`  DATETIME     NOT NULL,
                `consumed_at` DATETIME     DEFAULT NULL,
                `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        setSetting('phone_auth_schema_v1', '1');
    } catch (Exception $e) { /* leave flag unset → retried next load */ }
}

/**
 * Add the `pin_hash` column used for staff phone+PIN login.
 * Separate from ensurePhoneAuthSchema() because that one is guarded by its own
 * flag that may already be set on existing installs.
 */
function ensureStaffPinSchema(): void {
    if (getSetting('staff_pin_schema_v1', '') === '1') return;
    try {
        $db = getDB();
        dbAddColumnIfMissing($db, 'users', 'pin_hash',
            "`pin_hash` VARCHAR(255) DEFAULT NULL AFTER `password_hash`");
        setSetting('staff_pin_schema_v1', '1');
    } catch (Exception $e) { /* retried next load */ }
}

/** Is email + password login/registration enabled by the admin? (default: yes) */
function emailAuthEnabled(): bool {
    return getSetting('auth_email_enabled', '1') === '1';
}

/** Roles that count as staff (back-office), as opposed to a buyer. */
function isStaffRole(?string $role): bool {
    return in_array($role, ['manager', 'admin', 'superadmin'], true);
}

/**
 * Normalize a phone to canonical digits with country code (no '+', no separators).
 * Tajik local numbers (9 digits, no country code) are prefixed with 992.
 * Returns '' if there aren't enough digits to be a real number.
 */
function normalizePhone(string $raw): string {
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === '') return '';
    // 8XXXXXXXXXX (RU-style trunk) → 7XXXXXXXXXX
    if (strlen($d) === 11 && $d[0] === '8') $d = '7' . substr($d, 1);
    // Local Tajik number without country code (e.g. 92 123 45 67) → prefix 992
    if (strlen($d) === 9) $d = '992' . $d;
    return strlen($d) >= 9 ? $d : '';
}

/** Is a real SMS provider configured? When false, codes are shown on screen (test mode). */
function smsConfigured(): bool {
    return getSetting('sms_provider', '') !== '';
}

/**
 * Send an SMS. In test mode (no provider configured) the message is written to
 * storage/sms.log and the function returns true so the flow keeps working.
 * Real providers can be wired in here later (config in superadmin settings).
 */
function sendSms(string $phone, string $message): bool {
    $provider = getSetting('sms_provider', '');
    if ($provider === '') {
        $line = '[' . date('Y-m-d H:i:s') . '] +' . $phone . ' :: ' . $message . "\n";
        @file_put_contents(APP_ROOT . '/storage/sms.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }
    // Extension point: implement provider HTTP calls here (OSON SMS / SMSC / Twilio …)
    return false;
}

/**
 * Create and send a one-time code for a phone.
 * Rate-limited: one code per 60s, max 5 per hour.
 * Returns ['ok'=>bool, 'error'=>?string, 'dev_code'=>?string] (dev_code only in test mode).
 */
function createPhoneOtp(string $phone, string $purpose = 'login'): array {
    $phone = normalizePhone($phone);
    if ($phone === '') return ['ok' => false, 'error' => 'Введите корректный номер телефона.'];
    try {
        $db = getDB();
        // Rate limit
        $last = $db->prepare("SELECT created_at FROM phone_otp WHERE phone = ? ORDER BY id DESC LIMIT 1");
        $last->execute([$phone]);
        $lastAt = $last->fetchColumn();
        if ($lastAt && (time() - strtotime($lastAt)) < 60) {
            return ['ok' => false, 'error' => 'Код уже отправлен. Подождите минуту перед повторной отправкой.'];
        }
        $hr = $db->prepare("SELECT COUNT(*) FROM phone_otp WHERE phone = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $hr->execute([$phone]);
        if ((int)$hr->fetchColumn() >= 5) {
            return ['ok' => false, 'error' => 'Слишком много запросов. Попробуйте через час.'];
        }
        $code = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $db->prepare(
            "INSERT INTO phone_otp (phone, code_hash, purpose, expires_at)
             VALUES (?, ?, ?, (NOW() + INTERVAL 5 MINUTE))"
        )->execute([$phone, password_hash($code, PASSWORD_DEFAULT), $purpose]);
        sendSms($phone, 'Ваш код для входа на ' . getSetting('site_name', 'сайт') . ': ' . $code);
        return ['ok' => true, 'error' => null, 'dev_code' => smsConfigured() ? null : $code];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Не удалось отправить код. Попробуйте позже.'];
    }
}

/**
 * Verify a one-time code. On success the code is consumed (single-use).
 * Returns true only for a valid, unexpired, unconsumed code.
 */
function verifyPhoneOtp(string $phone, string $code, string $purpose = 'login'): bool {
    $phone = normalizePhone($phone);
    $code  = preg_replace('/\D+/', '', $code);
    if ($phone === '' || $code === '') return false;
    try {
        $db  = getDB();
        $row = $db->prepare(
            "SELECT * FROM phone_otp
             WHERE phone = ? AND purpose = ? AND consumed_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $row->execute([$phone, $purpose]);
        $otp = $row->fetch();
        if (!$otp) return false;
        if ((int)$otp['attempts'] >= 5) return false;
        if (!password_verify($code, $otp['code_hash'])) {
            $db->prepare("UPDATE phone_otp SET attempts = attempts + 1 WHERE id = ?")->execute([$otp['id']]);
            return false;
        }
        $db->prepare("UPDATE phone_otp SET consumed_at = NOW() WHERE id = ?")->execute([$otp['id']]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/** Find an active user by phone (matches the normalized phone_e164 key). */
function findUserByPhone(string $phone): ?array {
    $norm = normalizePhone($phone);
    if ($norm === '') return null;
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE phone_e164 = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$norm]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/** Establish a login session for a user row (shared by all auth flows). */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['username'] = $user['username'];
    unset($_SESSION['user_data']);
    // Слить гостевую корзину (если была) в БД-корзину пользователя.
    if (function_exists('cartMergeGuestIntoUser')) {
        try { cartMergeGuestIntoUser(getDB(), (int)$user['id']); } catch (Exception $e) {}
    }
}

/**
 * Slider text-block fonts available to admins. Key = font family stored in DB
 * ('' = the site default Rubik); value = human label for the dropdown.
 * All chosen families support Cyrillic.
 */
function sliderFonts(): array {
    return [
        ''                 => 'По умолчанию (Rubik)',
        'Oswald'           => 'Oswald — узкий, для заголовков',
        'Montserrat'       => 'Montserrat — геометрический',
        'Roboto Condensed' => 'Roboto Condensed — узкий',
        'Russo One'        => 'Russo One — жирный, техно',
        'Play'             => 'Play — техно',
        'Playfair Display' => 'Playfair Display — серифный',
        'Pacifico'         => 'Pacifico — рукописный',
    ];
}

/** CSS font-family value for a slider block font name ('' → site default). */
function sliderFontStack(string $name): string {
    $map = [
        'Oswald'           => "'Oswald', sans-serif",
        'Montserrat'       => "'Montserrat', sans-serif",
        'Roboto Condensed' => "'Roboto Condensed', sans-serif",
        'Russo One'        => "'Russo One', sans-serif",
        'Play'             => "'Play', sans-serif",
        'Playfair Display' => "'Playfair Display', serif",
        'Pacifico'         => "'Pacifico', cursive",
    ];
    return $map[$name] ?? '';
}

/** Google Fonts <link> URL loading every slider block font in one request. */
function sliderFontsGoogleUrl(): string {
    return 'https://fonts.googleapis.com/css2'
        . '?family=Oswald:wght@300;400;500;600;700'
        . '&family=Montserrat:wght@300;400;500;600;700;800;900'
        . '&family=Roboto+Condensed:wght@300;400;700'
        . '&family=Russo+One'
        . '&family=Play:wght@400;700'
        . '&family=Playfair+Display:wght@400;500;600;700;800;900'
        . '&family=Pacifico'
        . '&display=swap';
}

/** Allowed font-weight values for slider blocks (string => label). */
function sliderWeights(): array {
    return [
        '300' => 'Тонкий (300)',
        '400' => 'Обычный (400)',
        '500' => 'Средний (500)',
        '600' => 'Полужирный (600)',
        '700' => 'Жирный (700)',
        '800' => 'Очень жирный (800)',
        '900' => 'Чёрный (900)',
    ];
}

/**
 * Normalise raw slider text blocks (from POST or DB JSON) into a clean,
 * validated array. Drops blocks with empty text.
 */
function normalizeSliderBlocks(array $raw): array {
    // Cast to strings: numeric array keys (weights) become ints in array_keys(),
    // which would break the strict in_array() comparison below.
    $fonts   = array_map('strval', array_keys(sliderFonts()));
    $weights = array_map('strval', array_keys(sliderWeights()));
    $out = [];
    foreach ($raw as $b) {
        if (!is_array($b)) continue;
        $text = trim((string)($b['text'] ?? ''));
        if ($text === '') continue;
        $color = (string)($b['color'] ?? '#ffffff');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#ffffff';
        $out[] = [
            'text'        => mb_substr($text, 0, 255),
            'size'        => max(8, min(200, (int)($b['size'] ?? 24))),
            'size_mobile' => max(0, min(120, (int)($b['size_mobile'] ?? 0))), // 0 = auto-scale
            'weight'      => in_array((string)($b['weight'] ?? '400'), $weights, true) ? (string)$b['weight'] : '400',
            'color'       => strtolower($color),
            'font'        => in_array((string)($b['font'] ?? ''), $fonts, true) ? (string)$b['font'] : '',
            'mb'          => max(0, min(160, (int)($b['mb'] ?? 10))),
        ];
    }
    return $out;
}

/**
 * Swap ONLY the slider photos to the real Mazlay template hero images (one-time).
 *
 * The user's slides keep ALL their text — we touch only the image columns. The old
 * demo had a wrong photo (a blue Lamborghini placeholder); we replace it with the
 * genuine 1920×500 template images:
 *   hero1.jpg — салон/интерьер · hero2.jpg — тягач · hero3.jpg — Fidanza спорткар
 *
 * Each existing slide is matched to the most fitting photo by keywords in its text
 * (Fidanza / тягач / салон); anything else falls back to position order.
 *
 * Self-guarded by the 'slider_photos_template_v1' setting so it runs once.
 */
function seedSliderTemplate(): void {
    if (getSetting('slider_photos_template_v1', '')) return;
    $db = getDB();

    $fidanza  = '/assets/img/slider/hero3.jpg'; // спорткар Fidanza
    $truck    = '/assets/img/slider/hero2.jpg'; // тягач с прицепом
    $interior = '/assets/img/slider/hero1.jpg'; // салон автомобиля
    $byOrder  = [$interior, $truck, $fidanza];  // дефолтный порядок шаблона

    try {
        $slides = $db->query("SELECT id, title, text_blocks, text_blocks_mobile FROM sliders ORDER BY sort_order ASC, id ASC")->fetchAll();
        if (!$slides) { setSetting('slider_photos_template_v1', '1'); return; } // нет слайдов — менять нечего

        // Pick the best photo for a slide from the words in its text.
        $pick = function (array $row) use ($fidanza, $truck, $interior): ?string {
            $hay = mb_strtolower(($row['title'] ?? '') . ' ' . ($row['text_blocks'] ?? '') . ' ' . ($row['text_blocks_mobile'] ?? ''));
            if (preg_match('/fidanza|фиданза/u', $hay))               return $fidanza;
            if (preg_match('/тягач|прицеп|грузов|truck/u', $hay))     return $truck;
            if (preg_match('/салон|интерьер|interior|cabin/u', $hay)) return $interior;
            return null;
        };

        // Update only the image columns; text is left untouched.
        $upd = $db->prepare("UPDATE sliders SET image_url = ?, image_url_mobile = '' WHERE id = ?");
        foreach ($slides as $i => $row) {
            $img = $pick($row) ?? $byOrder[$i % 3];
            $upd->execute([$img, $row['id']]);
        }
        setSetting('slider_photos_template_v1', '1');
    } catch (Exception $e) { /* leave flag unset → retried next load */ }
}

/**
 * Seed the banners table with the 3 Mazlay template slider images (one-time).
 * Only runs when the banners table is empty; call is guarded by 'banners_seed_done' setting.
 */
function seedBanners(): void {
    $db = getDB();
    // Ensure table & columns exist before seeding
    $db->exec("CREATE TABLE IF NOT EXISTS `banners` (
      `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
      `title`            VARCHAR(255)  NOT NULL DEFAULT '',
      `image_url`        VARCHAR(500)  NOT NULL DEFAULT '',
      `image_url_mobile` VARCHAR(500)  NOT NULL DEFAULT '',
      `link_url`         VARCHAR(500)  NOT NULL DEFAULT '',
      `sort_order`       SMALLINT      NOT NULL DEFAULT 0,
      `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
      `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    dbAddColumnIfMissing($db, 'banners', 'image_url_mobile',
        "`image_url_mobile` VARCHAR(500) NOT NULL DEFAULT '' AFTER `image_url`");
    dbAddColumnIfMissing($db, 'banners', 'placement',
        "`placement` VARCHAR(20) NOT NULL DEFAULT 'home' AFTER `link_url`");

    // Skip if already has banners
    $count = (int)$db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if ($count > 0) return;

    $catalogUrl = defined('APP_URL') ? APP_URL . '/catalog/index.php' : '/catalog/index.php';
    $seeds = [
        ['Авто запчасти — выгодные цены',  '/assets/img/slider/slider1.jpg', 1],
        ['Надёжные детали для грузовиков', '/assets/img/slider/slider2.jpg', 2],
        ['Спортивные и тюнинг запчасти',   '/assets/img/slider/slider3.jpg', 3],
    ];
    $stmt = $db->prepare(
        "INSERT INTO banners (title, image_url, image_url_mobile, link_url, placement, sort_order, is_active)
         VALUES (?, ?, '', ?, 'home', ?, 1)"
    );
    foreach ($seeds as [$title, $img, $sort]) {
        $stmt->execute([$title, $img, $catalogUrl, $sort]);
    }
}

/* ───────────────────────── Login brute-force throttle (C2) ───────────────────────── */

const LOGIN_MAX_ATTEMPTS   = 5;    // consecutive failures before a lockout
const LOGIN_LOCK_SECONDS   = 900;  // lockout length (15 min)
const LOGIN_WINDOW_SECONDS = 900;  // window for counting consecutive failures

/**
 * Ensure the login_attempts table exists (one-time, guarded by a settings flag).
 * Failures are counted per (client IP + login identifier) so a brute-force run
 * against one account/IP gets locked out without affecting other users.
 */
function ensureLoginThrottleSchema(): void {
    if (getSetting('login_throttle_schema_v1', '') === '1') return;
    try {
        getDB()->exec(
            "CREATE TABLE IF NOT EXISTS `login_attempts` (
                `attempt_key`  VARCHAR(190) NOT NULL,
                `attempts`     INT          NOT NULL DEFAULT 0,
                `last_attempt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `locked_until` DATETIME     DEFAULT NULL,
                PRIMARY KEY (`attempt_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        setSetting('login_throttle_schema_v1', '1');
    } catch (Exception $e) { /* retried next load */ }
}

/** Throttle key for the current client + a login identifier (email/phone). */
function loginThrottleKey(string $identifier): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    return substr(hash('sha256', $ip . '|' . mb_strtolower(trim($identifier))), 0, 180);
}

/**
 * Current lockout status for an identifier.
 * @return array{locked:bool, seconds:int} seconds = time left on the lock.
 */
function loginThrottleStatus(string $identifier): array {
    ensureLoginThrottleSchema();
    try {
        $st = getDB()->prepare("SELECT locked_until FROM login_attempts WHERE attempt_key = ? LIMIT 1");
        $st->execute([loginThrottleKey($identifier)]);
        $until = $st->fetchColumn();
        if ($until) {
            $left = strtotime($until) - time();
            if ($left > 0) return ['locked' => true, 'seconds' => $left];
        }
    } catch (Exception $e) { /* fail open */ }
    return ['locked' => false, 'seconds' => 0];
}

/**
 * Record a failed login. After LOGIN_MAX_ATTEMPTS consecutive failures within the
 * window the (IP+identifier) is locked for LOGIN_LOCK_SECONDS.
 */
function registerFailedLogin(string $identifier): void {
    ensureLoginThrottleSchema();
    try {
        $db  = getDB();
        $key = loginThrottleKey($identifier);
        $st  = $db->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE attempt_key = ? LIMIT 1");
        $st->execute([$key]);
        $row = $st->fetch();
        if (!$row) {
            $db->prepare("INSERT INTO login_attempts (attempt_key, attempts, last_attempt) VALUES (?, 1, NOW())")
               ->execute([$key]);
            return;
        }
        $attempts = (int)$row['attempts'];
        if ($row['last_attempt'] && (time() - strtotime($row['last_attempt'])) > LOGIN_WINDOW_SECONDS) {
            $attempts = 0; // stale — start a fresh streak
        }
        $attempts++;
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $db->prepare("UPDATE login_attempts SET attempts = 0, last_attempt = NOW(), locked_until = (NOW() + INTERVAL " . LOGIN_LOCK_SECONDS . " SECOND) WHERE attempt_key = ?")
               ->execute([$key]);
        } else {
            $db->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = NOW() WHERE attempt_key = ?")
               ->execute([$attempts, $key]);
        }
    } catch (Exception $e) { /* ignore */ }
}

/** Clear throttle state after a successful login. */
function clearLoginAttempts(string $identifier): void {
    try {
        getDB()->prepare("DELETE FROM login_attempts WHERE attempt_key = ?")
               ->execute([loginThrottleKey($identifier)]);
    } catch (Exception $e) { /* ignore */ }
}

/** User-facing lockout message. */
function loginLockMessage(int $seconds): string {
    return 'Слишком много неудачных попыток входа. Повторите через ' . max(1, (int)ceil($seconds / 60)) . ' мин.';
}

/* ───────────────────────── Online-payment discount (checkout) ───────────────────────── */

/** Admin-configured online-payment incentive (read from site_settings). */
function onlinePaymentSettings(): array {
    return [
        'enabled'   => getSetting('online_payment_enabled', '0') === '1',
        'type'      => getSetting('online_discount_type', 'percent'), // 'percent' | 'fixed'
        'value'     => (float)getSetting('online_discount_value', '0'),
        'free_ship' => getSetting('online_free_shipping', '0') === '1',
    ];
}

/** Is paying online enabled by the admin? */
function onlinePaymentEnabled(): bool {
    return onlinePaymentSettings()['enabled'];
}

/**
 * Money discount (in base currency) for paying online, from the order subtotal.
 * Returns 0 when online payment is disabled or the discount is not configured.
 */
function onlinePaymentDiscount(float $subtotal): float {
    $s = onlinePaymentSettings();
    if (!$s['enabled'] || $subtotal <= 0) return 0.0;
    if ($s['type'] === 'fixed') {
        return round(min($subtotal, max(0.0, $s['value'])), 2);
    }
    $pct = max(0.0, min(100.0, $s['value']));
    return round($subtotal * $pct / 100, 2);
}

/** Short label of the active online-payment incentive (for the checkout UI). */
function onlinePaymentIncentiveLabel(): string {
    $s = onlinePaymentSettings();
    if (!$s['enabled']) return '';
    $parts = [];
    if ($s['value'] > 0) {
        $parts[] = $s['type'] === 'fixed'
            ? ('−' . formatPrice($s['value']))
            : ('−' . rtrim(rtrim(number_format($s['value'], 2, '.', ''), '0'), '.') . '%');
    }
    if ($s['free_ship']) $parts[] = t('online_free_shipping_short');
    return $parts ? implode(' · ', $parts) : '';
}

/**
 * Give the template slider slides presentable marketing copy (one-time).
 * Runs after seedSliderTemplate() (which sets the hero photos); here we match each
 * slide to copy by its image and write Russian headline/subtitle blocks + a button
 * — but ONLY for slides still showing demo/empty text, so admin-customised slides
 * are never overwritten. Self-guarded by 'slider_text_v1'.
 */
function seedSliderText(): void {
    if (getSetting('slider_text_v1', '') === '1') return;
    try {
        $db = getDB();
        $slides = $db->query("SELECT id, image_url, text_blocks FROM sliders ORDER BY sort_order ASC, id ASC")->fetchAll();
        if (!$slides) { setSetting('slider_text_v1', '1'); return; }

        $blk = fn(string $text, int $size, string $weight, int $mb): array =>
            ['text' => $text, 'size' => $size, 'size_mobile' => 0, 'weight' => $weight, 'color' => '#ffffff', 'font' => '', 'mb' => $mb];

        $copy = [
            'hero2' => ['button' => 'Подобрать по VIN', 'link' => '/pages/vin.php', 'blocks' => [
                $blk('Для легковых и грузовых авто', 28, '400', 8),
                $blk('Надёжность в каждой детали', 50, '800', 16),
                $blk('Подбор по VIN-коду за минуту', 20, '400', 26),
            ]],
            'hero3' => ['button' => 'Смотреть товары', 'link' => '/catalog/index.php', 'blocks' => [
                $blk('Тюнинг и спортивные компоненты', 28, '400', 8),
                $blk('Качество, проверенное дорогой', 50, '800', 16),
                $blk('Гарантия на все запчасти', 20, '400', 26),
            ]],
            'default' => ['button' => 'Перейти в каталог', 'link' => '/catalog/index.php', 'blocks' => [
                $blk('Автозапчасти для вашего авто', 28, '400', 8),
                $blk('Оригинальные детали по честной цене', 46, '800', 16),
                $blk('Доставка по всему Таджикистану', 20, '400', 26),
            ]],
        ];

        // A slide is "demo/empty" when it has no blocks or still uses template words.
        $isDemo = function (?string $json): bool {
            $s = trim((string)$json);
            if ($s === '' || $s === '[]' || $s === 'null') return true;
            return (bool)preg_match('/fidanza|фиданза|lorem|demo|пример|слайд\s*\d/iu', $s);
        };
        // Scale a desktop block set down for phones.
        $toMobile = function (array $blocks): array {
            foreach ($blocks as &$b) {
                $b['size'] = max(14, (int)round($b['size'] * 0.62));
                $b['mb']   = max(4,  (int)round($b['mb']   * 0.7));
            }
            return $blocks;
        };

        $upd = $db->prepare(
            "UPDATE sliders SET text_blocks = ?, text_blocks_mobile = ?, text_pos = 'left-center', text_pos_mobile = 'left-center', button_text = ?, link_url = ? WHERE id = ?"
        );
        foreach ($slides as $row) {
            if (!$isDemo($row['text_blocks'] ?? '')) continue; // keep customised slides
            $img = (string)($row['image_url'] ?? '');
            $key = strpos($img, 'hero2') !== false ? 'hero2'
                 : (strpos($img, 'hero3') !== false ? 'hero3' : 'default');
            $c       = $copy[$key];
            $jsonD   = json_encode(normalizeSliderBlocks($c['blocks']), JSON_UNESCAPED_UNICODE);
            $jsonM   = json_encode(normalizeSliderBlocks($toMobile($c['blocks'])), JSON_UNESCAPED_UNICODE);
            $upd->execute([$jsonD, $jsonM, $c['button'], $c['link'], $row['id']]);
        }
        setSetting('slider_text_v1', '1');
    } catch (Exception $e) { /* leave flag unset → retried next load */ }
}

/**
 * Seed a demonstration catalogue of real-looking auto parts (one-time, idempotent).
 * Each item is placed in an existing sub-category (matched by name) with a real
 * brand and a theme photo, so the shop looks stocked and the owner can edit/delete
 * every product from the admin panel. Skips an item when its part number already
 * exists or the sub-category/brand is missing. Guarded by 'demo_products_v1'.
 */
function seedDemoProducts(): int {
    if (getSetting('demo_products_v1', '') === '1') return 0;
    // [part_number, name, description, brand, sub-category, price, old_price|null, stock, weight(kg), dimensions]
    $items = [
        // — Двигатель —
        ['PR-92112','Поршень STD 81.0 мм Mahle','Поршень стандартного размера с пальцем и стопорными кольцами. Точная геометрия, термостойкий сплав.','Mahle','Поршни и кольца',420,null,60,0.400,'81x81x70'],
        ['RK-30418','Кольца поршневые, комплект Mahle','Комплект поршневых колец на 4 цилиндра. Хром-молибденовое покрытие, низкий расход масла.','Mahle','Поршни и кольца',1850,2300,40,0.350,'90x90x40'],
        ['VL-1042','Клапан впускной Febi','Впускной клапан из жаропрочной стали. Точная посадка, увеличенный ресурс.','Febi','Клапаны',180,null,120,0.080,'110x33'],
        ['VL-1043','Клапан выпускной Febi','Выпускной клапан, биметаллический, для высоких температур.','Febi','Клапаны',220,null,110,0.085,'112x33'],
        ['GK-5521','Прокладка ГБЦ Febi','Многослойная металлическая прокладка головки блока. Надёжная герметизация.','Febi','Прокладки ГБЦ',1450,null,25,0.300,'450x150x2'],
        ['OP-7781','Масляный насос Febi','Масляный насос в сборе. Стабильное давление в системе смазки.','Febi','Масляный насос',3200,null,18,1.600,'140x120x90'],
        ['K015561','Ремень ГРМ Gates PowerGrip','Ремень ГРМ из арамидного волокна для двигателей 1.6–2.0 TDI.','Gates','Ремни ГРМ',2350,null,45,0.320,'870x25'],
        ['T1019','Ремень ГРМ Continental CT','Зубчатый ремень ГРМ, износостойкий профиль зуба.','Continental','Ремни ГРМ',1750,2100,55,0.300,'1100x25'],
        ['OF-7707','Масляный фильтр Bosch','Масляный фильтр с клапаном обратного тока. Эффективная фильтрация.','Bosch','Масляные фильтры',380,null,150,0.120,'76x66'],
        ['W71225','Масляный фильтр Mann-Filter','Оригинальный масляный фильтр Mann. Надёжная защита двигателя.','Mann-Filter','Масляные фильтры',420,null,140,0.130,'76x79'],
        // — Тормозная система —
        ['P85020','Тормозные колодки перед. Brembo','Передние тормозные колодки. Низкий уровень пыли и шума, стабильное торможение.','Brembo','Тормозные колодки',3200,3900,35,0.580,'155x65x18'],
        ['GDB1330','Тормозные колодки TRW','Комплект тормозных колодок с износостойким составом.','TRW','Тормозные колодки',1650,null,60,0.550,'150x60x18'],
        ['09A73111','Тормозной диск Brembo','Вентилируемый тормозной диск, точная балансировка.','Brembo','Тормозные диски',2800,null,30,6.500,'300x28'],
        ['DF4318','Тормозной диск TRW','Тормозной диск с антикоррозийным покрытием.','TRW','Тормозные диски',2100,null,28,6.200,'280x25'],
        ['24-3654','Суппорт тормозной ATE','Тормозной суппорт в сборе, восстановлен по стандарту OE.','ATE','Суппорты',5400,null,12,3.200,'180x120x90'],
        ['BH-2201','Тормозной шланг Febi','Армированный тормозной шланг, устойчив к давлению и температуре.','Febi','Тормозные шланги',320,null,90,0.150,'350x15'],
        ['LM-DOT4','Тормозная жидкость DOT4 Liqui Moly 1 л','Синтетическая тормозная жидкость DOT4, высокая температура кипения.','Liqui Moly','Тормозная жидкость',95,null,200,1.050,'90x60x180'],
        // — Подвеска —
        ['G8-3401','Амортизатор перед. KYB Excel-G','Газомасляный амортизатор. Восстанавливает заводские характеристики.','KYB','Амортизаторы',2400,2900,40,1.900,'520x60'],
        ['E1100','Амортизатор Monroe OESpectrum','Технология Reflex для оптимального контроля кузова.','Monroe','Амортизаторы',2900,null,30,1.950,'540x60'],
        ['SP-7745','Пружина подвески Febi','Пружина подвески с антикоррозийным покрытием.','Febi','Пружины',1250,null,50,2.100,'380x140'],
        ['CTR-1188','Рычаг подвески Lemförder','Рычаг передней подвески в сборе с шарниром.','Lemförder','Рычаги',3600,null,20,3.400,'420x180x90'],
        ['BJ-2031','Шаровая опора TRW','Шаровая опора с пыльником, точная посадка.','TRW','Шаровые опоры',680,null,80,0.450,'90x90x80'],
        ['SB-5510','Сайлентблок рычага Febi','Сайлентблок из износостойкой резины с усиленным корпусом.','Febi','Сайлентблоки',280,null,150,0.100,'55x42x38'],
        ['SL-9921','Стойка стабилизатора Optimal','Стойка стабилизатора с шарнирами, снижает крены.','Optimal','Стойки стабилизатора',340,null,120,0.300,'280x15'],
        // — Электрика —
        ['BAT-60R','Аккумулятор 60 Ач Bosch S4','Аккумулятор 60 Ач, 540 A. Технология Power Frame.','Bosch','Аккумуляторы',4200,null,25,14.500,'242x175x190'],
        ['BAT-74R','Аккумулятор 74 Ач Bosch S5','Аккумулятор 74 Ач, 680 A. Долгий срок службы.','Bosch','Аккумуляторы',5100,5800,18,17.200,'278x175x190'],
        ['ST-0986','Стартер Bosch 12В','Стартер в сборе, высокий пусковой момент.','Bosch','Стартеры',6800,null,10,4.200,'200x150x150'],
        ['DN-100A','Генератор Denso 100A','Генератор 100 A со встроенным регулятором напряжения.','Denso','Генераторы',12500,null,8,4.200,'170x135x85'],
        ['BKR6E-K','Свеча зажигания NGK','Никелевая свеча зажигания с увеличенным ресурсом.','NGK','Свечи зажигания',180,null,300,0.045,'19x19x55'],
        ['NGK-IK20','Свеча NGK Iridium IX','Иридиевая свеча, улучшенное воспламенение и экономия топлива.','NGK','Свечи зажигания',260,null,250,0.042,'19x19x55'],
        ['MAF-0280','Датчик расхода воздуха Bosch','Датчик массового расхода воздуха (MAF), точное измерение потока.','Bosch','Датчики',4850,null,15,0.180,'90x45x38'],
        ['RL-4410','Реле Hella 12В 40A','Реле коммутации 12 В 40 A, надёжный контакт.','Hella','Реле и предохранители',140,null,200,0.050,'30x30x30'],
        // — Кузов —
        ['BMP-F01','Бампер передний (под покраску)','Передний бампер под покраску, точная геометрия.','Blue Print','Бамперы',3800,null,12,4.500,'1800x500x200'],
        ['HD-2201','Капот стальной','Капот в сборе, оцинкованная сталь, под покраску.','Blue Print','Капоты',7200,null,6,12.000,'1500x1200x100'],
        ['FN-3301','Крыло переднее левое','Переднее крыло, оцинковка, под покраску.','Blue Print','Крылья',2600,null,14,3.800,'1000x600x200'],
        ['MR-1102','Зеркало боковое правое','Боковое зеркало с электроприводом и обогревом.','Hella','Зеркала',2100,null,20,0.900,'250x180x120'],
        ['HL-7788','Фара передняя правая Hella','Передняя фара с регулировкой, прозрачный рассеиватель.','Hella','Фары',4600,5400,16,1.800,'600x350x250'],
        ['GR-5502','Решётка радиатора','Решётка радиатора, чёрный глянец.','Blue Print','Решётки радиатора',1400,null,30,0.700,'1100x200x60'],
        // — Трансмиссия —
        ['620301400','Комплект сцепления LuK RepSet','Комплект сцепления: диск, корзина, выжимной подшипник.','LuK','Сцепление',5600,6500,18,7.500,'240x240x120'],
        ['3000951','Комплект сцепления Sachs','Сцепление в сборе, плавное включение.','Sachs','Сцепление',5200,null,16,7.200,'228x228x110'],
        ['FW-4410','Маховик демпферный LuK','Двухмассовый маховик, снижает вибрации.','LuK','Маховики',11800,null,6,9.500,'260x260x60'],
        ['CV-3320','ШРУС наружный Febi','ШРУС наружный в комплекте с пыльником и смазкой.','Febi','ШРУСы',1900,null,40,1.500,'90x90x180'],
        ['HB-6101','Подшипник ступицы SKF','Комплект подшипника ступицы, полный узел.','SKF','Подшипники ступицы',2300,null,30,1.450,'85x42'],
        ['VKBA-3648','Подшипник ступицы перед. SKF','Двухрядный подшипник ступицы передней оси.','SKF','Подшипники ступицы',2600,null,22,1.500,'85x42'],
    ];
    try {
        $db = getDB();
        $catId = function (string $name) use ($db): int {
            $st = $db->prepare("SELECT id FROM categories WHERE name = ? ORDER BY (parent_id IS NULL) ASC, id ASC LIMIT 1");
            $st->execute([$name]);
            return (int)($st->fetchColumn() ?: 0);
        };
        $brandId = function (string $name) use ($db): int {
            $st = $db->prepare("SELECT id FROM brands WHERE name = ? LIMIT 1");
            $st->execute([$name]);
            return (int)($st->fetchColumn() ?: 0);
        };
        $has = $db->prepare("SELECT 1 FROM parts WHERE part_number = ? LIMIT 1");
        $ins = $db->prepare(
            "INSERT INTO parts (part_number, name, description, brand_id, category_id, price, old_price, stock, weight, dimensions, images, is_active, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NULL)"
        );
        $n = 0; $i = 0;
        foreach ($items as $it) {
            [$pn, $name, $desc, $brand, $subcat, $price, $old, $stock, $weight, $dims] = $it;
            $has->execute([$pn]);
            if ($has->fetchColumn()) continue;        // already present — never duplicate
            $cid = $catId($subcat);
            $bid = $brandId($brand);
            if (!$cid || !$bid) continue;             // structure missing — skip safely
            $imgN = ($i % 13) + 1; $i++;
            $img  = json_encode(['/assets/img/product/product' . $imgN . '.jpg']);
            try {
                $ins->execute([$pn, $name, $desc, $bid, $cid, $price, $old, $stock, $weight, $dims, $img]);
                $n++;
            } catch (Exception $e) { /* skip on any constraint, keep going */ }
        }
        setSetting('demo_products_v1', '1');
        return $n;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Add a column to an existing table only if it is missing.
 *
 * `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` is MariaDB-only (works on the
 * Debian dev box) but is a syntax error on MySQL 8.0 (Timeweb prod). We check
 * information_schema first, then run a plain ALTER — portable across both.
 *
 * @param string $colDdl Column definition, e.g. "`foo` VARCHAR(255) NOT NULL DEFAULT '' AFTER `bar`"
 */
function dbAddColumnIfMissing(PDO $db, string $table, string $column, string $colDdl): void {
    $st = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $column]);
    if (!(int)$st->fetchColumn()) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN $colDdl");
    }
}
