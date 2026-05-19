<?php
require_once dirname(__DIR__) . '/config/config.php';

// buyers + admins + superadmin may access
requireRole(['buyer', 'admin', 'superadmin']);

$user   = getCurrentUser();
$userId = (int)$user['id'];
$db     = getDB();

// ── Stats ─────────────────────────────────────────────────────────────
try {
    $s = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $s->execute([$userId]);
    $orderCount = (int)$s->fetchColumn();
} catch (Exception $e) { $orderCount = 0; }

try {
    $s = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $s->execute([$userId]);
    $cartItemCount = (int)$s->fetchColumn();
} catch (Exception $e) { $cartItemCount = 0; }

try {
    $s = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $s->execute([$userId]);
    $wishlistCount = (int)$s->fetchColumn();
} catch (Exception $e) { $wishlistCount = 0; }

// ── Recent orders ─────────────────────────────────────────────────────
try {
    $stmt = $db->prepare(
        "SELECT o.*, COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ?
         GROUP BY o.id
         ORDER BY o.created_at DESC
         LIMIT 5"
    );
    $stmt->execute([$userId]);
    $recentOrders = $stmt->fetchAll();
} catch (Exception $e) { $recentOrders = []; }

$pageTitle = t('dashboard');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="az-sidebar">
        <div class="az-sidebar-logo">
            AUTO<span>PARTS</span>
        </div>
        <nav>
            <ul>
                <li>
                    <a href="<?= APP_URL ?>/buyer/index.php" class="active">
                        <i class="fa fa-dashboard"></i> <?= t('dashboard') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/buyer/orders.php">
                        <i class="fa fa-list-alt"></i> Мои заказы
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/buyer/profile.php">
                        <i class="fa fa-user-o"></i> Профиль
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/buyer/cart.php">
                        <i class="fa fa-shopping-cart"></i> <?= t('shopping_cart') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/buyer/wishlist.php">
                        <i class="fa fa-heart-o"></i> <?= t('wishlist') ?>
                    </a>
                </li>
                <li style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);margin-top:20px;">
                    <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;">
                        <i class="fa fa-sign-out"></i> <?= t('logout') ?>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- ── Main ───────────────────────────────────────────────────── -->
    <main class="az-main">
        <div class="az-topbar">
            <h1><?= t('dashboard') ?></h1>
            <div style="display:flex;align-items:center;gap:16px;">
                <span style="font-size:0.875rem;color:#666;display:inline-flex;align-items:center;gap:7px;">
                    <?php if (!empty($user['avatar_path'])): ?>
                        <img src="<?= sanitize($user['avatar_path']) ?>" alt="" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <i class="fa fa-user-o"></i>
                    <?php endif; ?>
                    <?= sanitize($user['username']) ?>
                    <span style="background:#d32f2f;color:#fff;border-radius:4px;padding:2px 8px;font-size:0.75rem;"><?= sanitize($user['role']) ?></span>
                </span>
                <a href="<?= APP_URL ?>/index.php" style="font-size:0.85rem;color:#d32f2f;text-decoration:none;">
                    <i class="fa fa-arrow-left"></i> В магазин
                </a>
            </div>
        </div>

        <div class="az-content">

            <!-- Stat cards -->
            <div class="az-stats">
                <div class="az-stat-card">
                    <div class="stat-val"><?= $orderCount ?></div>
                    <div class="stat-lbl"><i class="fa fa-list-alt"></i> Заказов всего</div>
                </div>
                <div class="az-stat-card" style="border-left-color:#28a745;">
                    <div class="stat-val" style="color:#28a745;"><?= $cartItemCount ?></div>
                    <div class="stat-lbl"><i class="fa fa-shopping-cart"></i> Товаров в корзине</div>
                </div>
                <div class="az-stat-card" style="border-left-color:#e91e63;">
                    <div class="stat-val" style="color:#e91e63;"><?= $wishlistCount ?></div>
                    <div class="stat-lbl"><i class="fa fa-heart-o"></i> В избранном</div>
                </div>
            </div>

            <!-- Recent orders -->
            <div class="az-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="margin:0;">Последние заказы</h3>
                    <a href="<?= APP_URL ?>/buyer/orders.php" class="az-btn az-btn-secondary az-btn-sm">Все заказы</a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <div style="text-align:center;padding:40px;color:#aaa;">
                        <i class="fa fa-inbox" style="font-size:2.5rem;display:block;margin-bottom:12px;"></i>
                        У вас ещё нет заказов.
                        <br><br>
                        <a href="<?= APP_URL ?>/catalog/index.php" class="az-btn az-btn-primary az-btn-sm">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="az-table">
                            <thead>
                                <tr>
                                    <th>№ заказа</th>
                                    <th>Дата</th>
                                    <th>Позиций</th>
                                    <th><?= t('status') ?></th>
                                    <th><?= t('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><strong>#<?= (int)$order['id'] ?></strong></td>
                                        <td><?= sanitize(date('d.m.Y', strtotime($order['created_at']))) ?></td>
                                        <td><?= (int)$order['item_count'] ?> шт.</td>
                                        <td>
                                            <span class="badge badge-<?= sanitize(getOrderStatusClass($order['status'])) ?>">
                                                <?= sanitize(getOrderStatusLabel($order['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?= APP_URL ?>/buyer/orders.php?id=<?= (int)$order['id'] ?>"
                                               class="az-btn az-btn-secondary az-btn-sm">
                                                Детали
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick links -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;">
                <a href="<?= APP_URL ?>/catalog/index.php"
                   style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:16px;text-decoration:none;color:#333;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-left:4px solid #1976d2;">
                    <i class="fa fa-th-large" style="font-size:1.4rem;color:#1976d2;"></i>
                    <span style="font-weight:600;font-size:0.875rem;">Каталог</span>
                </a>
                <a href="<?= APP_URL ?>/buyer/cart.php"
                   style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:16px;text-decoration:none;color:#333;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-left:4px solid #28a745;">
                    <i class="fa fa-shopping-cart" style="font-size:1.4rem;color:#28a745;"></i>
                    <span style="font-weight:600;font-size:0.875rem;">Корзина (<?= $cartItemCount ?>)</span>
                </a>
                <a href="<?= APP_URL ?>/buyer/wishlist.php"
                   style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:16px;text-decoration:none;color:#333;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-left:4px solid #e91e63;">
                    <i class="fa fa-heart-o" style="font-size:1.4rem;color:#e91e63;"></i>
                    <span style="font-weight:600;font-size:0.875rem;">Избранное (<?= $wishlistCount ?>)</span>
                </a>
                <a href="<?= APP_URL ?>/buyer/profile.php"
                   style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:16px;text-decoration:none;color:#333;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-left:4px solid #ff9800;">
                    <i class="fa fa-user-o" style="font-size:1.4rem;color:#ff9800;"></i>
                    <span style="font-weight:600;font-size:0.875rem;">Мой профиль</span>
                </a>
            </div>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
