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
 * Get current user data from session (fetches from DB)
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, username, email, role, phone, is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

/**
 * Check if current user has the given role(s)
 * @param string|array $role
 */
function hasRole($role): bool {
    $user = getCurrentUser();
    if (!$user) return false;
    if (is_array($role)) {
        return in_array($user['role'], $role);
    }
    // superadmin has access to all roles
    if ($user['role'] === 'superadmin') return true;
    // admin has access to admin and below
    if ($user['role'] === 'admin' && in_array($role, ['admin', 'manager', 'buyer'])) return true;
    // manager has access to manager and buyer
    if ($user['role'] === 'manager' && in_array($role, ['manager', 'buyer'])) return true;
    return $user['role'] === $role;
}

/**
 * Require role or redirect to login/403
 * @param string|array $role
 */
function requireRole($role): void {
    if (!isLoggedIn()) {
        flashMessage('warning', 'Для доступа к этой странице необходимо войти в систему.');
        redirect(APP_URL . '/auth/login.php');
    }
    if (!hasRole($role)) {
        http_response_code(403);
        flashMessage('danger', 'У вас нет прав для просмотра этой страницы.');
        redirect(APP_URL . '/index.php');
    }
}

/**
 * Header redirect
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input): string {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Format price as "12 500 ₽"
 */
function formatPrice($price): string {
    return number_format((float)$price, 0, ',', ' ') . ' ₽';
}

/**
 * Generate CSRF token (session-based)
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
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 */
function flashMessage(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 */
function getFlashMessages(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Get cart count for current user
 */
function getCartCount(): int {
    if (!isLoggedIn()) return 0;
    static $count = null;
    if ($count === null) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $count = (int)$stmt->fetchColumn();
    }
    return $count;
}

/**
 * Get categories for navigation
 */
function getCategories(bool $rootOnly = false): array {
    $pdo = getDB();
    if ($rootOnly) {
        $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order ASC");
    } else {
        $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY parent_id ASC, sort_order ASC");
    }
    return $stmt->fetchAll();
}

/**
 * Get site setting
 */
function getSetting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT `key`, `value` FROM site_settings");
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Truncate text
 */
function truncate(string $text, int $length = 100): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '…';
}

/**
 * Get status label in Russian
 */
function getStatusLabel(string $status): string {
    $labels = [
        'pending'    => 'Ожидает',
        'processing' => 'В обработке',
        'shipped'    => 'Отправлен',
        'delivered'  => 'Доставлен',
        'cancelled'  => 'Отменён',
    ];
    return $labels[$status] ?? $status;
}

/**
 * Get status CSS class
 */
function getStatusClass(string $status): string {
    $classes = [
        'pending'    => 'status-pending',
        'processing' => 'status-processing',
        'shipped'    => 'status-shipped',
        'delivered'  => 'status-delivered',
        'cancelled'  => 'status-cancelled',
    ];
    return $classes[$status] ?? '';
}

/**
 * Stock status label
 */
function getStockLabel(int $stock): string {
    if ($stock <= 0)  return '<span class="stock-badge out">Нет в наличии</span>';
    if ($stock <= 5)  return '<span class="stock-badge low">Заканчивается (' . $stock . ' шт.)</span>';
    return '<span class="stock-badge in">В наличии (' . $stock . ' шт.)</span>';
}

/**
 * Pagination helper
 */
function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = (int)ceil($total / $perPage);
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}
