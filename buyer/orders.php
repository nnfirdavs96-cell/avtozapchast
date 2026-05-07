<?php
require_once __DIR__ . '/../config/config.php';
requireRole(['buyer','manager','admin','superadmin']);

$db = getDB();
$stmt = $db->prepare(
    "SELECT o.*, dm.name AS delivery_name, pm.name AS payment_name
     FROM orders o
     LEFT JOIN delivery_methods dm ON dm.id=o.delivery_method_id
     LEFT JOIN payment_methods pm ON pm.id=o.payment_method_id
     WHERE o.user_id=? ORDER BY o.created_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

$pageTitle = t('my_orders');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><div class="container">
  <h1><?= t('my_orders') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><a href="<?= APP_URL ?>/buyer/index.php"><?= t('my_account') ?></a><span class="sep">/</span><span class="current"><?= t('my_orders') ?></span></nav>
</div></div>

<section class="section">
  <div class="container">
    <?php if (empty($orders)): ?>
      <div class="empty-state">
        <h3>У вас пока нет заказов</h3>
        <p>Перейдите в каталог и оформите свой первый заказ.</p>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary"><?= t('catalog') ?></a>
      </div>
    <?php else: ?>
      <table class="cart-table">
        <thead><tr><th>№</th><th>Дата</th><th>Сумма</th><th>Доставка</th><th>Оплата</th><th>Статус</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><strong>#<?= (int)$o['id'] ?></strong></td>
              <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
              <td><strong><?= money($o['total_amount']) ?></strong></td>
              <td><?= sanitize($o['delivery_name'] ?? '—') ?></td>
              <td><?= sanitize($o['payment_name'] ?? '—') ?> <span class="badge badge-<?= $o['payment_status']==='paid'?'success':'warning' ?>"><?= $o['payment_status']==='paid'?'оплачен':'не оплачен' ?></span></td>
              <td><span class="badge badge-<?= getOrderStatusClass($o['status']) ?>"><?= sanitize(getOrderStatusLabel($o['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
