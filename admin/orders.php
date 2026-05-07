<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $id = (int)$_POST['id'];
    $status         = $_POST['status'] ?? null;
    $paymentStatus  = $_POST['payment_status'] ?? null;
    $tracking       = trim($_POST['tracking_number'] ?? '');
    if ($status) {
        $db->prepare("UPDATE orders SET status=?, payment_status=?, tracking_number=? WHERE id=?")
            ->execute([$status, $paymentStatus, $tracking ?: null, $id]);

        // notify buyer
        $st = $db->prepare("SELECT email,total_amount FROM orders WHERE id=?");
        $st->execute([$id]); $row = $st->fetch();
        if ($row && $row['email']) {
            sendEmail($row['email'], "Статус заказа №{$id} обновлён",
                emailLayout("Заказ №{$id} — " . getOrderStatusLabel($status),
                    "<p>Статус вашего заказа изменён на: <strong>" . getOrderStatusLabel($status) . "</strong>.</p>" .
                    ($tracking ? "<p>Трек-номер: <strong>{$tracking}</strong></p>" : '')));
        }
    }
    redirect(APP_URL . '/admin/orders.php?id=' . $id);
}

$orderId = (int)($_GET['id'] ?? 0);
$current = null;
if ($orderId) {
    $st = $db->prepare("SELECT o.*, u.username, u.email AS user_email, dm.name AS delivery_name, pm.name AS payment_name
                        FROM orders o JOIN users u ON u.id=o.user_id
                        LEFT JOIN delivery_methods dm ON dm.id=o.delivery_method_id
                        LEFT JOIN payment_methods pm ON pm.id=o.payment_method_id
                        WHERE o.id=?");
    $st->execute([$orderId]); $current = $st->fetch();
    $itStmt = $db->prepare("SELECT oi.*, p.name, p.part_number FROM order_items oi JOIN parts p ON p.id=oi.part_id WHERE oi.order_id=?");
    $itStmt->execute([$orderId]); $orderItems = $itStmt->fetchAll();
}

$status = $_GET['status'] ?? '';
$where = $status ? "WHERE o.status=" . $db->quote($status) : '';
$orders = $db->query("SELECT o.*, u.username, u.email FROM orders o JOIN users u ON u.id=o.user_id {$where} ORDER BY o.created_at DESC LIMIT 100")->fetchAll();

$pageTitle = 'Заказы';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Заказы</h1>

    <?php if ($current): ?>
      <div class="admin-card mb-32">
        <div class="flex-between mb-16">
          <h3>Заказ #<?= (int)$current['id'] ?> · <?= money($current['total_amount']) ?></h3>
          <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">← Все заказы</a>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
          <div>
            <table class="admin-table">
              <tr><th>Покупатель</th><td><?= sanitize($current['username']) ?></td></tr>
              <tr><th>E-mail</th><td><?= sanitize($current['email'] ?: $current['user_email']) ?></td></tr>
              <tr><th>Адрес</th><td><?= sanitize($current['shipping_address']) ?></td></tr>
              <tr><th>Доставка</th><td><?= sanitize($current['delivery_name']) ?> (<?= money($current['delivery_cost']) ?>)</td></tr>
              <tr><th>Оплата</th><td><?= sanitize($current['payment_name']) ?></td></tr>
              <tr><th>Создан</th><td><?= date('d.m.Y H:i', strtotime($current['created_at'])) ?></td></tr>
              <tr><th>Комментарий</th><td><?= sanitize($current['notes'] ?? '—') ?></td></tr>
            </table>
          </div>
          <div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
              <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
              <div class="form-group">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                  <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $current['status']===$s?'selected':'' ?>><?= sanitize(getOrderStatusLabel($s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Статус оплаты</label>
                <select name="payment_status" class="form-select">
                  <?php foreach (['unpaid','paid','refunded'] as $ps): ?>
                    <option value="<?= $ps ?>" <?= $current['payment_status']===$ps?'selected':'' ?>><?= $ps ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Трек-номер</label>
                <input type="text" name="tracking_number" class="form-input" value="<?= sanitize($current['tracking_number'] ?? '') ?>">
              </div>
              <button class="btn btn-primary">Сохранить</button>
            </form>
          </div>
        </div>
        <h3 class="mt-32 mb-16">Состав заказа</h3>
        <div class="admin-table-wrap"><table class="admin-table">
          <thead><tr><th>Артикул</th><th>Название</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr></thead>
          <tbody>
          <?php foreach ($orderItems as $oi): ?>
            <tr>
              <td><span class="mono"><?= sanitize($oi['part_number']) ?></span></td>
              <td><?= sanitize($oi['name']) ?></td>
              <td><?= (int)$oi['quantity'] ?></td>
              <td><?= money($oi['unit_price']) ?></td>
              <td><strong><?= money($oi['unit_price'] * $oi['quantity']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
    <?php endif; ?>

    <div class="mb-16">
      <a href="?"          class="btn btn-<?= !$status?'primary':'outline' ?> btn-sm">Все</a>
      <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-<?= $status===$s?'primary':'outline' ?> btn-sm"><?= sanitize(getOrderStatusLabel($s)) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="admin-card">
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>№</th><th>Покупатель</th><th>Сумма</th><th>Статус</th><th>Оплата</th><th>Дата</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><strong>#<?= (int)$o['id'] ?></strong></td>
              <td><?= sanitize($o['username']) ?></td>
              <td><strong><?= money($o['total_amount']) ?></strong></td>
              <td><span class="badge badge-<?= getOrderStatusClass($o['status']) ?>"><?= sanitize(getOrderStatusLabel($o['status'])) ?></span></td>
              <td><span class="badge badge-<?= $o['payment_status']==='paid'?'success':'warning' ?>"><?= $o['payment_status'] ?></span></td>
              <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
              <td><a href="?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">→</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
