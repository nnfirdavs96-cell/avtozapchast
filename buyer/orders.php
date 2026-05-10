<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('buyer');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$db     = getDB();

// ── Single order detail ───────────────────────────────────────────────
$viewId      = (int)($_GET['id'] ?? 0);
$orderDetail = null;
$orderItems  = [];

if ($viewId) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$viewId, $userId]);
    $orderDetail = $stmt->fetch();

    if ($orderDetail) {
        $iStmt = $db->prepare(
            "SELECT oi.*, p.name AS part_name, p.part_number, b.name AS brand_name
             FROM order_items oi
             JOIN parts p ON p.id = oi.part_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE oi.order_id = ?"
        );
        $iStmt->execute([$viewId]);
        $orderItems = $iStmt->fetchAll();
    }
}

// ── All orders with pagination ────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM orders WHERE user_id = ?"
);
$countStmt->execute([$userId]);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages  = max(1, (int)ceil($totalOrders / $perPage));
$page        = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$ordersStmt = $db->prepare(
    "SELECT o.*, COUNT(oi.id) AS item_count, SUM(oi.quantity * oi.unit_price) AS total
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$ordersStmt->execute([$userId]);
$orders = $ordersStmt->fetchAll();

$pageTitle = 'Мои заказы';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav>
            <ul>
                <li><a href="<?= APP_URL ?>/buyer/index.php"><i class="fa fa-dashboard"></i> <?= t('dashboard') ?></a></li>
                <li><a href="<?= APP_URL ?>/buyer/orders.php" class="active"><i class="fa fa-list-alt"></i> Мои заказы</a></li>
                <li><a href="<?= APP_URL ?>/buyer/profile.php"><i class="fa fa-user-o"></i> Профиль</a></li>
                <li><a href="<?= APP_URL ?>/buyer/cart.php"><i class="fa fa-shopping-cart"></i> <?= t('shopping_cart') ?></a></li>
                <li><a href="<?= APP_URL ?>/buyer/wishlist.php"><i class="fa fa-heart-o"></i> <?= t('wishlist') ?></a></li>
                <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:20px;">
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
            <h1>Мои заказы</h1>
            <a href="<?= APP_URL ?>/index.php" style="font-size:0.85rem;color:#d32f2f;text-decoration:none;">
                <i class="fa fa-arrow-left"></i> В магазин
            </a>
        </div>

        <div class="az-content">

            <?php if ($orderDetail): ?>
            <!-- ── Order detail view ──────────────────────────────── -->
            <div class="az-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
                    <div>
                        <h3 style="margin:0;font-size:1.1rem;">Заказ #<?= (int)$orderDetail['id'] ?></h3>
                        <div style="font-size:0.8rem;color:#888;margin-top:4px;">
                            <?= sanitize(date('d.m.Y H:i', strtotime($orderDetail['created_at']))) ?>
                        </div>
                    </div>
                    <span class="badge badge-<?= sanitize(getOrderStatusClass($orderDetail['status'])) ?>"
                          style="font-size:0.85rem;padding:6px 14px;">
                        <?= sanitize(getOrderStatusLabel($orderDetail['status'])) ?>
                    </span>
                </div>

                <?php if (!empty($orderDetail['shipping_address'])): ?>
                <div style="margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:6px;font-size:0.875rem;">
                    <strong>Адрес доставки:</strong><br>
                    <?= nl2br(sanitize($orderDetail['shipping_address'])) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($orderDetail['notes'])): ?>
                <div style="margin-bottom:16px;padding:12px;background:#fff3cd;border-radius:6px;font-size:0.875rem;">
                    <strong>Примечание:</strong> <?= sanitize($orderDetail['notes']) ?>
                </div>
                <?php endif; ?>

                <div style="overflow-x:auto;">
                    <table class="az-table">
                        <thead>
                            <tr>
                                <th>Артикул</th>
                                <th>Наименование</th>
                                <th>Бренд</th>
                                <th style="text-align:center;">Кол-во</th>
                                <th style="text-align:right;">Цена</th>
                                <th style="text-align:right;">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td><code style="font-size:0.8rem;"><?= sanitize($item['part_number']) ?></code></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$item['part_id'] ?>"
                                           style="color:#333;text-decoration:none;font-size:0.875rem;">
                                            <?= sanitize($item['part_name']) ?>
                                        </a>
                                    </td>
                                    <td style="color:#888;font-size:0.8rem;"><?= sanitize($item['brand_name'] ?? '—') ?></td>
                                    <td style="text-align:center;"><?= (int)$item['quantity'] ?></td>
                                    <td style="text-align:right;"><?= formatPrice($item['unit_price']) ?></td>
                                    <td style="text-align:right;color:#d32f2f;font-weight:700;">
                                        <?= formatPrice($item['unit_price'] * $item['quantity']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align:right;font-weight:700;padding-top:12px;">ИТОГО:</td>
                                <td style="text-align:right;font-weight:900;color:#d32f2f;font-size:1.1rem;padding-top:12px;">
                                    <?= formatPrice($orderDetail['total_amount']) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top:20px;">
                    <a href="<?= APP_URL ?>/buyer/orders.php" class="az-btn az-btn-secondary az-btn-sm">
                        <i class="fa fa-arrow-left"></i> Все заказы
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Orders list ───────────────────────────────────── -->
            <div class="az-card">
                <h3 style="margin:0 0 16px;">
                    <?= $orderDetail ? 'Все заказы' : 'История заказов' ?>
                    <span style="font-size:0.8rem;font-weight:400;color:#888;">(<?= $totalOrders ?>)</span>
                </h3>

                <?php if (empty($orders)): ?>
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
                                    <th>№</th>
                                    <th>Дата</th>
                                    <th style="text-align:center;">Позиций</th>
                                    <th style="text-align:right;">Сумма</th>
                                    <th><?= t('status') ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?= (int)$order['id'] ?></strong></td>
                                        <td style="color:#888;font-size:0.85rem;">
                                            <?= sanitize(date('d.m.Y H:i', strtotime($order['created_at']))) ?>
                                        </td>
                                        <td style="text-align:center;"><?= (int)$order['item_count'] ?></td>
                                        <td style="text-align:right;font-weight:700;color:#d32f2f;">
                                            <?= formatPrice($order['total'] ?? $order['total_amount'] ?? 0) ?>
                                        </td>
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

                    <?php if ($totalPages > 1): ?>
                    <div class="paginatoin-area" style="margin-top:20px;">
                        <div class="row"><div class="col-12">
                            <div class="pagination-box">
                                <ul class="pagination">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="<?= $i === $page ? 'active' : '' ?>">
                                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </div>
                        </div></div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
