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
        flashMessage('danger', 'Доступ запрещён. Недостаточно прав.');
        redirect(APP_URL . '/index.php');
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
        'sliders'    => 'Слайдер / Баннеры',
        'orders'     => 'Заказы',
        'users'      => 'Пользователи',
        'categories' => 'Категории',
        'brands'     => 'Бренды',
        'blog'       => 'Блог',
        'pages'      => 'Страницы (CMS)',
        'reviews'    => 'Отзывы',
        'warehouse'  => 'Склад API',
        'vin'        => 'VIN-поиск',
    ];
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
    flashMessage('danger', 'Доступ к этому разделу ограничён администратором.');
    redirect(APP_URL . '/index.php');
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

    if ($role === 'superadmin') {
        $items = [
            ['key' => 'dashboard',  'href' => "$url/superadmin/index.php",   'icon' => 'fa-tachometer',   'label' => 'Панель'],
            ['key' => 'users',      'href' => "$url/superadmin/users.php",   'icon' => 'fa-users',        'label' => 'Пользователи'],
            ['key' => 'permissions','href' => "$url/superadmin/permissions.php",'icon' => 'fa-shield',     'label' => 'Права доступа'],
            ['key' => 'orders',     'href' => "$url/admin/orders.php",       'icon' => 'fa-shopping-bag', 'label' => 'Заказы'],
            ['key' => 'products',   'href' => "$url/admin/products.php",     'icon' => 'fa-cogs',         'label' => 'Товары'],
            ['key' => 'sliders',    'href' => "$url/admin/sliders.php",      'icon' => 'fa-picture-o',    'label' => 'Слайдер'],
            ['key' => 'banners',    'href' => "$url/admin/banners.php",      'icon' => 'fa-clone',        'label' => 'Баннеры'],
            ['key' => 'settings',   'href' => "$url/superadmin/settings.php",'icon' => 'fa-cog',          'label' => 'Настройки'],
            ['key' => 'currencies', 'href' => "$url/superadmin/currencies.php", 'icon' => 'fa-money',     'label' => 'Валюты'],
            ['key' => 'languages',  'href' => "$url/superadmin/languages.php",  'icon' => 'fa-language',  'label' => 'Языки'],
            ['key' => 'warehouse',  'href' => "$url/superadmin/warehouse.php",  'icon' => 'fa-database',  'label' => 'Склад API'],
            ['key' => 'vin',        'href' => "$url/superadmin/vin.php",        'icon' => 'fa-search',    'label' => 'VIN-поиск'],
            ['key' => 'blog',       'href' => "$url/superadmin/blog.php",       'icon' => 'fa-newspaper-o', 'label' => 'Блог'],
            ['key' => 'partners',   'href' => "$url/manager/brands.php",        'icon' => 'fa-handshake-o', 'label' => 'Партнёры'],
            ['key' => 'pages',      'href' => "$url/manager/pages.php",         'icon' => 'fa-file-text-o', 'label' => 'Страницы'],
            ['key' => 'reviews',    'href' => "$url/manager/reviews.php",       'icon' => 'fa-comments-o',  'label' => 'Отзывы'],
            ['key' => 'categories', 'href' => "$url/manager/categories.php",    'icon' => 'fa-sitemap',     'label' => 'Категории'],
            ['key' => 'backup',     'href' => "$url/superadmin/backup.php",     'icon' => 'fa-archive',   'label' => 'Бэкапы'],
            ['key' => 'manual',     'href' => "$url/superadmin/manual.php",     'icon' => 'fa-book',      'label' => 'Руководство'],
        ];
        $logoHtml = '<div class="az-sidebar-logo"><span style="color:#fcb700;">★</span> СУПЕР<span>АДМИН</span></div>';
        $asideStyle = '';
    } elseif ($role === 'admin') {
        $items = [
            ['key' => 'dashboard', 'href' => "$url/admin/index.php",     'icon' => 'fa-tachometer',   'label' => 'Панель'],
            ['key' => 'products',  'href' => "$url/admin/products.php",  'icon' => 'fa-cogs',         'label' => 'Товары'],
            ['key' => 'sliders',   'href' => "$url/admin/sliders.php",   'icon' => 'fa-picture-o',    'label' => 'Слайдер'],
            ['key' => 'banners',   'href' => "$url/admin/banners.php",   'icon' => 'fa-clone',        'label' => 'Баннеры'],
            ['key' => 'orders',    'href' => "$url/admin/orders.php",    'icon' => 'fa-shopping-bag', 'label' => 'Заказы'],
            ['key' => 'users',     'href' => "$url/admin/users.php",     'icon' => 'fa-users',        'label' => 'Пользователи'],
            ['key' => 'vin',       'href' => "$url/superadmin/vin.php",  'icon' => 'fa-search',       'label' => 'VIN-поиск'],
        ];
        $logoHtml = '<div class="az-sidebar-logo">ADMIN<span>PANEL</span></div>';
        $asideStyle = '';
    } elseif ($role === 'manager') {
        $items = [
            ['key' => 'dashboard',  'href' => "$url/manager/index.php",      'icon' => 'fa-dashboard',    'label' => 'Панель'],
            ['key' => 'parts',      'href' => "$url/manager/parts.php",      'icon' => 'fa-cogs',         'label' => 'Запчасти'],
            ['key' => 'categories', 'href' => "$url/manager/categories.php", 'icon' => 'fa-sitemap',      'label' => 'Категории'],
            ['key' => 'brands',     'href' => "$url/manager/brands.php",     'icon' => 'fa-tag',          'label' => 'Бренды'],
            ['key' => 'blog',       'href' => "$url/manager/blog.php",       'icon' => 'fa-newspaper-o',  'label' => 'Блог'],
        ];
        $logoHtml = '<div class="az-sidebar-logo">AUTO<span>PARTS</span></div>';
        $asideStyle = '';
    } else {
        return;
    }

    echo '<aside class="az-sidebar"' . ($asideStyle ? ' style="' . $asideStyle . '"' : '') . '>';
    echo $logoHtml;
    echo '<nav><ul>';
    foreach ($items as $it) {
        // Hide links the superadmin has restricted for this user.
        // 'dashboard' is always visible (entry point).
        if ($it['key'] !== 'dashboard' && !userCan(permissionAlias($it['key']))) {
            continue;
        }
        $cls = $it['key'] === $active ? ' class="active"' : '';
        echo '<li><a href="' . sanitize($it['href']) . '"' . $cls . '><i class="fa ' . sanitize($it['icon']) . '"></i> ' . sanitize($it['label']) . '</a></li>';
    }
    echo '<li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:12px;">';
    echo '<a href="' . $url . '/index.php"><i class="fa fa-home"></i> На сайт</a></li>';
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
    if (!isLoggedIn()) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
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
    if (!isLoggedIn()) return [];
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT c.quantity, p.id, p.name, p.price, p.images, b.name AS brand_name
             FROM cart c
             JOIN parts p ON p.id = c.part_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE c.user_id = ?
             ORDER BY c.added_at DESC LIMIT 5"
        );
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

/**
 * Get mini cart total in RUB
 */
function getMiniCartTotal(): float {
    if (!isLoggedIn()) return 0;
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(c.quantity * p.price), 0)
             FROM cart c JOIN parts p ON p.id = c.part_id WHERE c.user_id = ?"
        );
        $stmt->execute([$_SESSION['user_id']]);
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
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
                $db->prepare(
                    "INSERT INTO categories (name, slug, parent_id, description, image_path, image_path_mobile, sort_order, is_active, markup_percent)
                     VALUES (?,?,?,?,?,?,?,1,NULL)"
                )->execute([$subName, $slug, (int)$parentId, null, null, null, $sort]);
                $n++;
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
