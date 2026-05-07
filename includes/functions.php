<?php
/**
 * АвтоЗапчасть — Helper Functions
 */

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    if (isset($_SESSION['user_data'])) return $_SESSION['user_data'];
    try {
        $stmt = getDB()->prepare("SELECT id, username, email, role, phone, is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) { $_SESSION['user_data'] = $user; return $user; }
        session_destroy();
    } catch (Throwable $e) { /* ignore */ }
    return null;
}

function hasRole($role): bool {
    if (!isLoggedIn()) return false;
    $roles = is_array($role) ? $role : [$role];
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

function requireRole($role): void {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    $roles = is_array($role) ? $role : [$role];
    if (($_SESSION['role'] ?? '') === 'superadmin') return;
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        flashMessage('danger', 'Доступ запрещён. Недостаточно прав.');
        redirect(APP_URL . '/index.php');
    }
}

function redirect(string $url): void { header('Location: ' . $url); exit; }
function sanitize($input): string  { return htmlspecialchars((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

function formatPrice($price): string {
    return money($price);
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function flashMessage(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function getFlashMessage(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function getCartCount(): int {
    if (!isLoggedIn()) return 0;
    try {
        $stmt = getDB()->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function getWishlistCount(): int {
    if (!isLoggedIn()) return 0;
    try {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function getCompareCount(): int {
    try {
        $db = getDB();
        if (isLoggedIn()) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM compare_list WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM compare_list WHERE session_id = ?");
            $stmt->execute([session_id()]);
        }
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function isInWishlist(int $partId): bool {
    if (!isLoggedIn()) return false;
    try {
        $stmt = getDB()->prepare("SELECT 1 FROM wishlist WHERE user_id=? AND part_id=? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'], $partId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function isInCompare(int $partId): bool {
    try {
        $db = getDB();
        if (isLoggedIn()) {
            $stmt = $db->prepare("SELECT 1 FROM compare_list WHERE user_id=? AND part_id=? LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $partId]);
        } else {
            $stmt = $db->prepare("SELECT 1 FROM compare_list WHERE session_id=? AND part_id=? LIMIT 1");
            $stmt->execute([session_id(), $partId]);
        }
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function getCategories(): array {
    try {
        return getDB()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order, name")->fetchAll();
    } catch (Throwable $e) { return []; }
}
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
function getBrands(): array {
    try {
        return getDB()->query("SELECT * FROM brands WHERE is_active=1 ORDER BY name")->fetchAll();
    } catch (Throwable $e) { return []; }
}
function getCarMakes(): array {
    try {
        return getDB()->query("SELECT * FROM car_makes WHERE is_active=1 ORDER BY name")->fetchAll();
    } catch (Throwable $e) { return []; }
}
function getCarModels(?int $makeId = null): array {
    try {
        if ($makeId) {
            $stmt = getDB()->prepare("SELECT * FROM car_models WHERE is_active=1 AND make_id=? ORDER BY name");
            $stmt->execute([$makeId]);
            return $stmt->fetchAll();
        }
        return getDB()->query("SELECT * FROM car_models WHERE is_active=1 ORDER BY name")->fetchAll();
    } catch (Throwable $e) { return []; }
}

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
function getOrderStatusClass(string $status): string {
    return [
        'pending'=>'warning','processing'=>'info','shipped'=>'primary',
        'delivered'=>'success','cancelled'=>'danger',
    ][$status] ?? 'secondary';
}

function truncate(string $str, int $len = 100, string $suffix = '...'): string {
    return mb_strlen($str) <= $len ? $str : mb_substr($str, 0, $len) . $suffix;
}

function getStockStatus(int $stock): array {
    if ($stock <= 0) return ['label' => t('out_of_stock'), 'class' => 'danger'];
    if ($stock <= 5) return ['label' => t('low_stock'),    'class' => 'warning'];
    return ['label' => t('in_stock'), 'class' => 'success'];
}

/**
 * Average rating + count of approved reviews for a part.
 */
function getPartRating(int $partId): array {
    try {
        $stmt = getDB()->prepare(
            "SELECT COALESCE(ROUND(AVG(rating),1),0) avg_r, COUNT(*) c
             FROM reviews WHERE part_id=? AND status='approved'");
        $stmt->execute([$partId]);
        $r = $stmt->fetch();
        return ['avg' => (float)$r['avg_r'], 'count' => (int)$r['c']];
    } catch (Throwable $e) { return ['avg'=>0, 'count'=>0]; }
}

function ratingStars(float $avg, int $size = 14): string {
    $full  = (int)floor($avg);
    $half  = ($avg - $full) >= 0.5;
    $empty = 5 - $full - ($half ? 1 : 0);
    $out = '';
    for ($i = 0; $i < $full;  $i++)  $out .= '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="#fcb700" stroke="none"><polygon points="12,2 15,9 22,10 17,15 18,22 12,18 6,22 7,15 2,10 9,9"/></svg>';
    if ($half) $out .=                     '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"><defs><linearGradient id="hg"><stop offset="50%" stop-color="#fcb700"/><stop offset="50%" stop-color="#ddd"/></linearGradient></defs><polygon points="12,2 15,9 22,10 17,15 18,22 12,18 6,22 7,15 2,10 9,9" fill="url(#hg)"/></svg>';
    for ($i = 0; $i < $empty; $i++)  $out .= '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="#ddd"><polygon points="12,2 15,9 22,10 17,15 18,22 12,18 6,22 7,15 2,10 9,9"/></svg>';
    return $out;
}

/**
 * Returns first image URL for a part (or placeholder).
 */
function getPartImage(int $partId): string {
    try {
        $stmt = getDB()->prepare("SELECT path FROM part_images WHERE part_id=? ORDER BY is_primary DESC, sort_order LIMIT 1");
        $stmt->execute([$partId]);
        $p = $stmt->fetchColumn();
        if ($p) return UPLOAD_URL . 'parts/' . ltrim($p, '/');
    } catch (Throwable $e) { /* ignore */ }
    return APP_URL . '/assets/img/site/placeholder.svg';
}

function getPartImages(int $partId): array {
    try {
        $stmt = getDB()->prepare("SELECT * FROM part_images WHERE part_id=? ORDER BY is_primary DESC, sort_order");
        $stmt->execute([$partId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) { return []; }
}

/**
 * Build canonical URL for current page.
 */
function canonicalUrl(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri   = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $proto . '://' . $host . $uri;
}
