<?php
require_once __DIR__ . '/../config/config.php';
requireRole(['buyer','manager','admin','superadmin']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare(
    "SELECT o.*, dm.name AS delivery_name, pm.name AS payment_name
     FROM orders o
     LEFT JOIN delivery_methods dm ON dm.id=o.delivery_method_id
     LEFT JOIN payment_methods pm ON pm.id=o.payment_method_id
     WHERE o.id=? AND o.user_id=?"
);
$stmt->execute([$id, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) { flashMessage('danger','Заказ не найден'); redirect(APP_URL . '/buyer/orders.php'); }

$itStmt = $db->prepare(
    "SELECT oi.*, p.name AS part_name, p.part_number FROM order_items oi
     JOIN parts p ON p.id=oi.part_id WHERE oi.order_id=?"
);
$itStmt->execute([$id]);
$orderItems = $itStmt->fetchAll();

$pageTitle = t('order_placed');
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="container container-sm">
    <div class="checkout-card text-center" style="padding:48px">
      <div style="width:80px;height:80px;border-radius:50%;background:rgba(46,204,113,0.15);color:#2ecc71;display:inline-flex;align-items:center;justify-content:center;margin-bottom:18px">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h1 style="font-size:2rem"><?= t('order_placed') ?></h1>
      <p class="text-muted mt-8 mb-24"><?= t('order_confirmation_msg') ?></p>
      <p><?= t('order_number') ?>: <strong style="color:var(--primary);font-size:1.4rem">#<?= (int)$order['id'] ?></strong></p>

      <table class="spec-table mt-32" style="text-align:left">
        <tr><th>Дата</th><td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td></tr>
        <tr><th>Сумма</th><td><strong><?= money($order['total_amount']) ?></strong> (<?= sanitize($order['currency_code']) ?>)</td></tr>
        <tr><th>Доставка</th><td><?= sanitize($order['delivery_name']) ?> · <?= money($order['delivery_cost']) ?></td></tr>
        <tr><th>Оплата</th><td><?= sanitize($order['payment_name']) ?></td></tr>
        <tr><th>Статус</th><td><span class="badge badge-warning">Новый</span></td></tr>
        <tr><th>Адрес</th><td><?= sanitize($order['shipping_address']) ?></td></tr>
      </table>

      <h3 class="mt-32 mb-16" style="text-align:left">Состав заказа</h3>
      <table class="cart-table">
        <tbody>
          <?php foreach ($orderItems as $oi): ?>
            <tr>
              <td><?= sanitize($oi['part_name']) ?> <small class="text-muted">· <?= sanitize($oi['part_number']) ?></small></td>
              <td><?= (int)$oi['quantity'] ?> × <?= money($oi['unit_price']) ?></td>
              <td><strong><?= money($oi['unit_price'] * $oi['quantity']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="mt-32 flex" style="gap:10px;justify-content:center">
        <a href="<?= APP_URL ?>/buyer/orders.php" class="btn btn-primary"><?= t('go_to_orders') ?></a>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-outline"><?= t('continue_shopping') ?></a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
