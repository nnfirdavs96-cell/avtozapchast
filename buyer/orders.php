<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('buyer');

$user = getCurrentUser();
$db   = getDB();

// View specific order
$viewId = (int)($_GET['id'] ?? 0);
$orderDetail = null;
$orderItems  = [];

if ($viewId) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$viewId, $user['id']]);
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

// All orders
$ordersStmt = $db->prepare(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC"
);
$ordersStmt->execute([$user['id']]);
$orders = $ordersStmt->fetchAll();

$pageTitle = 'Мои заказы';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">МОИ ЗАКАЗЫ</div>

    <?php if ($orderDetail): ?>
    <!-- Order detail view -->
    <div class="card mb-24">
      <div class="card-header">
        <div>
          <h3>ЗАКАЗ #<?= $orderDetail['id'] ?></h3>
          <span class="label-mono"><?= date('d.m.Y H:i', strtotime($orderDetail['created_at'])) ?></span>
        </div>
        <span class="badge badge-<?= getOrderStatusClass($orderDetail['status']) ?> " style="font-size:0.75rem;">
          <?= getOrderStatusLabel($orderDetail['status']) ?>
        </span>
      </div>
      <div class="card-body">
        <div class="grid-2 mb-16">
          <div>
            <div class="label-mono mb-8">Адрес доставки</div>
            <p style="font-size:0.875rem;color:var(--text-secondary);"><?= nl2br(sanitize($orderDetail['shipping_address'])) ?></p>
          </div>
          <?php if ($orderDetail['notes']): ?>
          <div>
            <div class="label-mono mb-8">Примечания</div>
            <p style="font-size:0.875rem;color:var(--text-secondary);"><?= nl2br(sanitize($orderDetail['notes'])) ?></p>
          </div>
          <?php endif; ?>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>Номер</th><th>Наименование</th><th>Бренд</th><th style="text-align:center;">Кол-во</th><th style="text-align:right;">Цена</th><th style="text-align:right;">Сумма</th></tr>
            </thead>
            <tbody>
              <?php foreach ($orderItems as $item): ?>
              <tr>
                <td><span class="mono"><?= sanitize($item['part_number']) ?></span></td>
                <td>
                  <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $item['part_id'] ?>" style="color:var(--text-primary);text-decoration:none;font-size:0.875rem;">
                    <?= sanitize($item['part_name']) ?>
                  </a>
                </td>
                <td style="color:var(--text-muted);font-size:0.8rem;"><?= sanitize($item['brand_name']) ?></td>
                <td style="text-align:center;font-family:var(--font-mono);"><?= $item['quantity'] ?></td>
                <td style="text-align:right;font-family:var(--font-mono);color:var(--text-secondary);"><?= formatPrice($item['unit_price']) ?></td>
                <td style="text-align:right;font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($item['unit_price'] * $item['quantity']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="text-align:right;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
          <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--text-muted);">ИТОГО: </span>
          <span style="font-family:var(--font-display);font-size:1.8rem;color:var(--accent);letter-spacing:2px;">
            <?= formatPrice($orderDetail['total_amount']) ?>
          </span>
        </div>
      </div>
      <div class="card-footer">
        <a href="<?= APP_URL ?>/buyer/orders.php" class="btn btn-outline btn-sm">← Все заказы</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Orders list -->
    <?php if (empty($orders)): ?>
    <div class="no-data">
      <div class="no-data-icon">📦</div>
      <p>У вас ещё нет заказов.</p>
      <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary btn-sm" style="margin-top:16px;">Перейти в каталог</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Дата</th><th>Сумма</th><th>Статус</th><th>Обновлён</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
          <tr>
            <td><span class="mono">#<?= $order['id'] ?></span></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
            <td style="font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($order['total_amount']) ?></td>
            <td><span class="badge badge-<?= getOrderStatusClass($order['status']) ?>"><?= getOrderStatusLabel($order['status']) ?></span></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y H:i', strtotime($order['updated_at'])) ?></td>
            <td><a href="?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">Детали</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
