<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('buyer');

$user   = getCurrentUser();
$userId = (int)$user['id'];
$db     = getDB();

// ── Cancel order (buyer can cancel only while the order is still pending) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности. Попробуйте снова.');
        redirect(APP_URL . '/buyer/orders.php');
    }
    $cancelId = (int)($_POST['order_id'] ?? 0);
    $chk = $db->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $chk->execute([$cancelId, $userId]);
    $st = $chk->fetchColumn();

    if ($st === false) {
        flashMessage('danger', 'Заказ не найден.');
    } elseif ($st !== 'pending') {
        flashMessage('warning', 'Этот заказ уже подтверждён — отменить его можно только через поддержку.');
    } else {
        $db->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'")
           ->execute([$cancelId, $userId]);
        flashMessage('success', 'Заказ #' . $cancelId . ' отменён.');
    }
    redirect(APP_URL . '/buyer/orders.php?id=' . $cancelId);
}

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
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb([
    ['label' => t('home'), 'url' => APP_URL . '/index.php'],
    ['label' => 'Мои заказы'],
]) ?>

<div class="az-account">
    <div class="container">
        <?= renderBuyerAccountNav('orders') ?>
        <div class="az-account-body">

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
                    <?= formatShippingAddress($orderDetail['shipping_address']) ?>
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
                            <?php $shipCost = (float)($orderDetail['shipping_cost'] ?? 0); ?>
                            <?php if ($shipCost > 0): ?>
                            <tr>
                                <td colspan="5" style="text-align:right;color:#666;padding-top:12px;">Доставка:</td>
                                <td style="text-align:right;color:#666;padding-top:12px;"><?= formatPrice($shipCost) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="5" style="text-align:right;font-weight:700;<?= $shipCost > 0 ? '' : 'padding-top:12px;' ?>">ИТОГО:</td>
                                <td style="text-align:right;font-weight:900;color:#d32f2f;font-size:1.1rem;<?= $shipCost > 0 ? '' : 'padding-top:12px;' ?>">
                                    <?= formatPrice($orderDetail['total_amount']) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <a href="<?= APP_URL ?>/buyer/orders.php" class="az-btn az-btn-secondary az-btn-sm">
                        <i class="fa fa-arrow-left"></i> Все заказы
                    </a>

                    <?php if ($orderDetail['status'] === 'pending'): ?>
                        <form method="post" action="<?= APP_URL ?>/buyer/orders.php"
                              onsubmit="return confirm('Отменить заказ #<?= (int)$orderDetail['id'] ?>? Это действие нельзя отменить.');"
                              style="display:inline;margin:0;">
                            <input type="hidden" name="_csrf" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="order_id" value="<?= (int)$orderDetail['id'] ?>">
                            <button type="submit" class="az-btn az-btn-sm" style="background:#d32f2f;color:#fff;border:none;">
                                <i class="fa fa-times"></i> Отменить заказ
                            </button>
                        </form>
                    <?php elseif (in_array($orderDetail['status'], ['processing', 'shipped'], true)):
                        $supPhone = getSetting('site_phone', '');
                        $supWa    = getSetting('site_whatsapp', '');
                    ?>
                        <div style="flex:1 1 100%;margin-top:8px;padding:12px;background:#fff3cd;border-radius:6px;font-size:0.85rem;color:#664d03;">
                            <i class="fa fa-info-circle"></i>
                            Заказ уже подтверждён. Чтобы отменить его, свяжитесь с поддержкой:
                            <?php if ($supPhone !== ''): ?>
                                <a href="tel:<?= sanitize(preg_replace('/[^\d+]/', '', $supPhone)) ?>" style="font-weight:700;color:#d32f2f;"><?= sanitize($supPhone) ?></a>
                            <?php endif; ?>
                            <?php if ($supWa !== ''): ?>
                                &middot; <a href="https://wa.me/<?= sanitize(preg_replace('/\D/', '', $supWa)) ?>" target="_blank" rel="noopener" style="font-weight:700;color:#25D366;">WhatsApp</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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

        </div><!-- /.az-account-body -->
    </div><!-- /.container -->
</div><!-- /.az-account -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
