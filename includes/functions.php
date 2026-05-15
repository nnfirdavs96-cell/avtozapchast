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
    $stmt = $db->prepare("SELECT id, username, email, role, phone, is_active FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
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
function requireRole($role): void {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    $roles = is_array($role) ? $role : [$role];
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
 * Redirect helper
 */
function redirect(string $url): void {
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
            ['key' => 'orders',     'href' => "$url/admin/orders.php",       'icon' => 'fa-shopping-bag', 'label' => 'Заказы'],
            ['key' => 'products',   'href' => "$url/admin/products.php",     'icon' => 'fa-cogs',         'label' => 'Товары'],
            ['key' => 'sliders',    'href' => "$url/admin/sliders.php",      'icon' => 'fa-picture-o',    'label' => 'Слайдер'],
            ['key' => 'settings',   'href' => "$url/superadmin/settings.php",'icon' => 'fa-cog',          'label' => 'Настройки'],
            ['key' => 'currencies', 'href' => "$url/superadmin/currencies.php", 'icon' => 'fa-money',     'label' => 'Валюты'],
            ['key' => 'languages',  'href' => "$url/superadmin/languages.php",  'icon' => 'fa-language',  'label' => 'Языки'],
            ['key' => 'warehouse',  'href' => "$url/superadmin/warehouse.php",  'icon' => 'fa-database',  'label' => 'Склад API'],
            ['key' => 'vin',        'href' => "$url/superadmin/vin.php",        'icon' => 'fa-search',    'label' => 'VIN-поиск'],
            ['key' => 'blog',       'href' => "$url/superadmin/blog.php",       'icon' => 'fa-newspaper-o', 'label' => 'Блог'],
            ['key' => 'backup',     'href' => "$url/superadmin/backup.php",     'icon' => 'fa-archive',   'label' => 'Бэкапы'],
        ];
        $logoHtml = '<div class="az-sidebar-logo" style="color:#fff;">★ Суперадмин</div>';
        $asideStyle = 'background:linear-gradient(180deg,#2c1338 0%,#1a0a26 100%);';
    } elseif ($role === 'admin') {
        $items = [
            ['key' => 'dashboard', 'href' => "$url/admin/index.php",     'icon' => 'fa-tachometer',   'label' => 'Панель'],
            ['key' => 'products',  'href' => "$url/admin/products.php",  'icon' => 'fa-cogs',         'label' => 'Товары'],
            ['key' => 'sliders',   'href' => "$url/admin/sliders.php",   'icon' => 'fa-picture-o',    'label' => 'Слайдер'],
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
 * Truncate string
 */
function truncate(string $str, int $len = 100, string $suffix = '...'): string {
    if (mb_strlen($str) <= $len) return $str;
    return mb_substr($str, 0, $len) . $suffix;
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
    if (isset($cache[$key])) return $cache[$key];
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
