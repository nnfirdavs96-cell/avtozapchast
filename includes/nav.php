<?php
/**
 * Role-based navigation items
 */

function renderNav(): void {
    $role = $_SESSION['role'] ?? 'guest';
    $url  = APP_URL;

    $groups = [];

    // Buyer links
    if (in_array($role, ['buyer','admin','manager','superadmin'])) {
        $groups['buyer'] = [
            'label' => 'Покупатель',
            'items' => [
                ['href' => $url . '/buyer/index.php',   'label' => 'Мой кабинет'],
                ['href' => $url . '/buyer/orders.php',  'label' => 'Мои заказы'],
                ['href' => $url . '/buyer/cart.php',    'label' => 'Корзина'],
                ['href' => $url . '/buyer/profile.php', 'label' => 'Профиль'],
            ],
        ];
    }

    // Manager links
    if (in_array($role, ['manager','superadmin'])) {
        $groups['manager'] = [
            'label' => 'Менеджер',
            'items' => [
                ['href' => $url . '/manager/index.php',      'label' => 'Обзор'],
                ['href' => $url . '/manager/parts.php',      'label' => 'Товары'],
                ['href' => $url . '/manager/categories.php', 'label' => 'Категории'],
                ['href' => $url . '/manager/brands.php',     'label' => 'Бренды'],
            ],
        ];
    }

    // Admin links
    if (in_array($role, ['admin','superadmin'])) {
        $groups['admin'] = [
            'label' => 'Администратор',
            'items' => [
                ['href' => $url . '/admin/index.php',  'label' => 'Обзор'],
                ['href' => $url . '/admin/orders.php', 'label' => 'Заказы'],
                ['href' => $url . '/admin/users.php',  'label' => 'Пользователи'],
            ],
        ];
    }

    // Superadmin links
    if ($role === 'superadmin') {
        $groups['superadmin'] = [
            'label' => 'Супер-Администратор',
            'items' => [
                ['href' => $url . '/superadmin/index.php',    'label' => 'Обзор'],
                ['href' => $url . '/superadmin/users.php',    'label' => 'Все пользователи'],
                ['href' => $url . '/superadmin/settings.php', 'label' => 'Настройки сайта'],
            ],
        ];
    }

    if (empty($groups)) return;

    $current = $_SERVER['REQUEST_URI'] ?? '';

    echo '<nav class="side-nav">';
    foreach ($groups as $key => $group) {
        echo '<div class="side-nav-group">';
        echo '<div class="side-nav-label">' . htmlspecialchars($group['label']) . '</div>';
        echo '<ul>';
        foreach ($group['items'] as $item) {
            $active = (strpos($current, parse_url($item['href'], PHP_URL_PATH)) !== false) ? ' active' : '';
            echo '<li><a href="' . $item['href'] . '" class="' . trim($active) . '">'
                . htmlspecialchars($item['label']) . '</a></li>';
        }
        echo '</ul></div>';
    }
    echo '</nav>';
}
?>
<style>
.side-nav { font-family: var(--font-body); }
.side-nav-group { margin-bottom: 24px; }
.side-nav-label {
  font-family: var(--font-mono);
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--text-muted);
  padding: 0 12px 8px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 6px;
}
.side-nav ul { list-style: none; margin: 0; padding: 0; }
.side-nav a {
  display: block;
  padding: 9px 12px;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 0.825rem;
  border-radius: 4px;
  transition: color 0.2s, background 0.2s;
  margin-bottom: 2px;
}
.side-nav a:hover { color: var(--text-primary); background: var(--bg-hover); }
.side-nav a.active { color: var(--accent); background: var(--accent-glow); }
</style>
