<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);

$db   = getDB();
$csrf = generateCsrfToken();

// AJAX status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action  = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $token   = $_POST['csrf_token'] ?? '';
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (!verifyCsrfToken($token)) {
        echo json_encode(['success' => false, 'error' => 'CSRF fail']); exit;
    }
    if ($action === 'update_status' && in_array($status, $allowed)) {
        $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$status, $orderId]);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Bad request']); exit;
}

// View specific order
$viewId      = (int)($_GET['id'] ?? 0);
$orderDetail = null;
$orderItems  = [];
if ($viewId) {
    $stmt = $db->prepare(
        "SELECT o.*, u.username, u.email, u.phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?"
    );
    $stmt->execute([$viewId]);
    $orderDetail = $stmt->fetch();
    if ($orderDetail) {
        $iStmt = $db->prepare(
            "SELECT oi.*, p.name AS part_name, p.part_number, b.name AS brand_name
             FROM order_items oi JOIN parts p ON p.id = oi.part_id LEFT JOIN brands b ON b.id = p.brand_id
             WHERE oi.order_id = ?"
        );
        $iStmt->execute([$viewId]);
        $orderItems = $iStmt->fetchAll();
    }
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterUser   = trim($_GET['user'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$where  = [];
$params = [];
if ($filterStatus) { $where[] = 'o.status = ?'; $params[] = $filterStatus; }
if ($filterUser)   { $where[] = '(u.username LIKE ? OR u.email LIKE ?)'; $params[] = "%$filterUser%"; $params[] = "%$filterUser%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total    = (int)$db->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id $whereSQL")->execute($params) ? 0 : 0;
$cntStmt  = $db->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id $whereSQL");
$cntStmt->execute($params);
$total    = (int)$cntStmt->fetchColumn();
$pages    = max(1, ceil($total / $perPage));
$offset   = ($page - 1) * $perPage;

$ordersStmt = $db->prepare(
    "SELECT o.*, u.username, u.email FROM orders o JOIN users u ON u.id = o.user_id
     $whereSQL ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset"
);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

$pageTitle = 'Управление заказами';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
$statuses = ['pending','processing','shipped','delivered','cancelled'];
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">ЗАКАЗЫ</div>

    <?php if ($orderDetail): ?>
    <!-- Order detail -->
    <div class="card mb-24">
      <div class="card-header">
        <div>
          <h3>ЗАКАЗ #<?= $orderDetail['id'] ?></h3>
          <div class="label-mono"><?= sanitize($orderDetail['username']) ?> · <?= sanitize($orderDetail['email']) ?></div>
        </div>
        <select class="form-select" style="width:auto;"
                data-status-update="<?= $orderDetail['id'] ?>"
                data-csrf="<?= sanitize($csrf) ?>">
          <?php foreach ($statuses as $st): ?>
          <option value="<?= $st ?>" <?= $orderDetail['status'] === $st ? 'selected' : '' ?>>
            <?= getOrderStatusLabel($st) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="card-body">
        <div class="grid-2 mb-16">
          <div>
            <div class="label-mono mb-8">Покупатель</div>
            <p style="font-size:0.875rem;color:var(--text-secondary);">
              <?= sanitize($orderDetail['username']) ?><br>
              <?= sanitize($orderDetail['email']) ?><br>
              <?= sanitize($orderDetail['phone'] ?? '') ?>
            </p>
          </div>
          <div>
            <div class="label-mono mb-8">Адрес доставки</div>
            <p style="font-size:0.875rem;color:var(--text-secondary);"><?= nl2br(sanitize($orderDetail['shipping_address'])) ?></p>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Номер</th><th>Наименование</th><th>Кол-во</th><th style="text-align:right;">Цена</th><th style="text-align:right;">Сумма</th></tr></thead>
            <tbody>
              <?php foreach ($orderItems as $item): ?>
              <tr>
                <td><span class="mono"><?= sanitize($item['part_number']) ?></span></td>
                <td style="font-size:0.875rem;"><?= sanitize($item['part_name']) ?></td>
                <td style="font-family:var(--font-mono);"><?= $item['quantity'] ?></td>
                <td style="text-align:right;font-family:var(--font-mono);color:var(--text-secondary);"><?= formatPrice($item['unit_price']) ?></td>
                <td style="text-align:right;font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($item['unit_price'] * $item['quantity']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="text-align:right;padding-top:12px;font-family:var(--font-display);font-size:1.5rem;color:var(--accent);">
          ИТОГО: <?= formatPrice($orderDetail['total_amount']) ?>
        </div>
      </div>
      <div class="card-footer">
        <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">← Все заказы</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="get" class="flex gap-8 mb-16" style="flex-wrap:wrap;">
      <select name="status" class="form-select" style="width:auto;" onchange="this.form.submit()">
        <option value="">Все статусы</option>
        <?php foreach ($statuses as $st): ?>
        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= getOrderStatusLabel($st) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="user" class="form-input" style="width:200px;" placeholder="Поиск покупателя..." value="<?= sanitize($filterUser) ?>">
      <button type="submit" class="btn btn-outline btn-sm">Фильтр</button>
      <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">Сбросить</a>
      <span style="margin-left:auto;font-family:var(--font-mono);font-size:0.75rem;color:var(--text-muted);align-self:center;">
        Всего: <?= $total ?>
      </span>
    </form>

    <!-- Orders table -->
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Покупатель</th><th>Дата</th><th>Сумма</th><th>Статус</th><th>Изменить статус</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
          <tr>
            <td><span class="mono">#<?= $order['id'] ?></span></td>
            <td>
              <div style="font-size:0.875rem;"><?= sanitize($order['username']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= sanitize($order['email']) ?></div>
            </td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
            <td style="font-family:var(--font-mono);color:var(--accent);"><?= formatPrice($order['total_amount']) ?></td>
            <td><span class="badge badge-<?= getOrderStatusClass($order['status']) ?>"><?= getOrderStatusLabel($order['status']) ?></span></td>
            <td>
              <select class="form-select" style="width:auto;font-size:0.78rem;padding:6px 10px;"
                      data-status-update="<?= $order['id'] ?>"
                      data-csrf="<?= sanitize($csrf) ?>">
                <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>>
                  <?= getOrderStatusLabel($st) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><a href="?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">Детали</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= $pages; $p++): $q = array_merge($_GET, ['page' => $p]); ?>
      <a href="?<?= http_build_query($q) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
