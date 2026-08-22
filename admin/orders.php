<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/messaging.php';
requireRole(['admin', 'manager', 'superadmin']);
requirePermission('orders');

$db   = getDB();
$csrf = generateCsrfToken();
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// Текст автосообщения покупателю при смене статуса (pending не анонсируем).
$statusMessages = [
    'processing' => 'Ваш заказ подтверждён и принят в работу. Мы уже занимаемся им.',
    'shipped'    => 'Ваш заказ отправлен и едет к вам.',
    'delivered'  => 'Ваш заказ доставлен. Спасибо за покупку!',
    'cancelled'  => 'Ваш заказ отменён. Если это ошибка — напишите нам прямо здесь.',
];

// ── POST: change order status ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/admin/orders.php');
    }
    $action  = $_POST['action'] ?? '';
    $orderId = (int)($_POST['id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    if ($action === 'status' && $orderId && in_array($status, $statuses)) {
        // Текущий статус и владелец заказа — чтобы не слать сообщение при «пустой» смене.
        $cur = $db->prepare("SELECT status, user_id FROM orders WHERE id = ? LIMIT 1");
        $cur->execute([$orderId]);
        $ord = $cur->fetch();
        $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $orderId]);
        // Автосообщение в переписку заказа, только если статус реально изменился.
        if ($ord && $ord['status'] !== $status && isset($statusMessages[$status])) {
            postSystemMessage((int)$ord['user_id'], $orderId, $statusMessages[$status]);
        }
        flashMessage('success', 'Статус заказа обновлён.');
    }
    redirect(APP_URL . '/admin/orders.php' . ($orderId ? "?id=$orderId" : ''));
}

// ── View order detail ────────────────────────────────────────────────────────
$viewId      = (int)($_GET['id'] ?? 0);
$orderDetail  = null;
$orderSellers = [];   // подзаказы по продавцам (маркетплейс, Фаза 2)
$orderItems  = [];
if ($viewId) {
    $stmt = $db->prepare(
        "SELECT o.*, u.username, u.email, u.phone FROM orders o
         JOIN users u ON u.id = o.user_id WHERE o.id = ?"
    );
    $stmt->execute([$viewId]);
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

        // Подзаказы по продавцам (маркетплейс, Фаза 2): админу нужно видеть, кто
        // из продавцов что везёт, в каком статусе и сколько с этого комиссии —
        // особенно при разборе споров. Нет миграции → блок просто не показывается.
        try {
            $osStmt = $db->prepare(
                "SELECT os.*, s.shop_name, s.slug, u.phone AS seller_phone, u.email AS seller_email
                   FROM order_sellers os
                   LEFT JOIN sellers s ON s.id = os.seller_id
                   LEFT JOIN users u   ON u.id = s.user_id
                  WHERE os.order_id = ?
               ORDER BY (os.seller_id IS NULL) DESC, s.shop_name"
            );
            $osStmt->execute([$viewId]);
            $orderSellers = $osStmt->fetchAll();
        } catch (Throwable $e) {
            $orderSellers = [];
        }
    }
}

// ── Filters & list ────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterUser   = trim($_GET['user'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$where        = [];
$params       = [];

if ($filterStatus && in_array($filterStatus, $statuses)) {
    $where[]  = 'o.status = ?';
    $params[] = $filterStatus;
}
if ($filterUser) {
    $where[]  = '(u.username LIKE ? OR u.email LIKE ?)';
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cntStmt = $db->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id $whereSQL");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$ordersStmt = $db->prepare(
    "SELECT o.*, u.username, u.email FROM orders o JOIN users u ON u.id = o.user_id
     $whereSQL ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset"
);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

$pageTitle = 'Заказы — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('orders'); ?>

  <!-- Main -->
  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Управление заказами</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Order detail view -->
      <?php if ($orderDetail): ?>
      <div class="az-card mb-24">
        <div class="az-card-header">
          <h4 class="az-card-title">Заказ #<?= (int)$orderDetail['id'] ?>
            <span class="badge badge-<?= getOrderStatusClass($orderDetail['status']) ?> ml-8">
              <?= getOrderStatusLabel($orderDetail['status']) ?>
            </span>
          </h4>
          <a href="<?= APP_URL ?>/admin/orders.php" class="az-btn az-btn-outline az-btn-sm">← Все заказы</a>
        </div>
        <div class="az-card-body">
          <?php if (!empty($orderSellers)): ?>
          <?php
            $osLabels = ['pending'=>'В обработке','processing'=>'Собирается',
                         'shipped'=>'Отправлен','delivered'=>'Доставлен','cancelled'=>'Отменён'];
            $commTotal = 0.0;
            foreach ($orderSellers as $os) $commTotal += (float)$os['commission_amount'];
          ?>
          <div class="mb-16" style="border:1px solid #e7e7ee;border-radius:10px;padding:12px 14px;background:#fafafc;">
            <div style="font-weight:700;margin-bottom:8px;font-size:.9rem;">
              <i class="fa fa-briefcase" style="color:#C70909;"></i> Разбивка по продавцам
              <?php if ($commTotal > 0): ?>
              <span style="font-weight:400;color:#666;font-size:.85rem;">· комиссия площадки: <b><?= formatPrice($commTotal) ?></b></span>
              <?php endif; ?>
              <!-- Здесь только «сколько причитается по этому заказу». Накопленный
                   долг и сами выплаты живут в отдельном реестре. -->
              <a href="<?= APP_URL ?>/admin/payouts.php" style="font-weight:400;font-size:.8rem;margin-left:8px;">
                Реестр выплат →
              </a>
            </div>
            <div class="table-responsive">
              <table class="az-table" style="font-size:.82rem;margin:0;">
                <thead>
                  <tr>
                    <th>Продавец</th>
                    <th>Контакт</th>
                    <th style="text-align:right;">Сумма</th>
                    <th style="text-align:right;">Комиссия</th>
                    <th style="text-align:right;">К выплате</th>
                    <th>Статус</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orderSellers as $os): ?>
                  <tr>
                    <td>
                      <?php if (!empty($os['seller_id'])): ?>
                        <strong><?= sanitize($os['shop_name'] ?: 'Магазин #' . (int)$os['seller_id']) ?></strong>
                      <?php else: ?>
                        <span style="color:#666;">Наш каталог</span>
                      <?php endif; ?>
                    </td>
                    <td style="color:#888;">
                      <?= sanitize($os['seller_phone'] ?? '') ?>
                      <?php if (!empty($os['seller_email'])): ?><br><small><?= sanitize($os['seller_email']) ?></small><?php endif; ?>
                    </td>
                    <td style="text-align:right;"><?= formatPrice((float)$os['subtotal']) ?></td>
                    <td style="text-align:right;color:#666;">
                      <?= (float)$os['commission_percent'] > 0
                            ? rtrim(rtrim(number_format((float)$os['commission_percent'], 2, '.', ''), '0'), '.') . '% · ' . formatPrice((float)$os['commission_amount'])
                            : '—' ?>
                    </td>
                    <td style="text-align:right;font-weight:700;"><?= formatPrice((float)$os['payout_amount']) ?></td>
                    <td><span class="sl-badge sl-badge-<?= sanitize($os['status']) ?>"><?= $osLabels[$os['status']] ?? sanitize($os['status']) ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div style="font-size:.76rem;color:#999;margin-top:8px;">
              Статусы частей меняют сами продавцы в своём кабинете. Выплаты пока не автоматизированы (Фаза 3).
            </div>
          </div>
          <?php endif; ?>

          <div class="row mb-16">
            <div class="col-md-4">
              <strong>Покупатель:</strong><br>
              <?= sanitize($orderDetail['username']) ?><br>
              <small class="text-muted"><?= sanitize($orderDetail['email']) ?></small><br>
              <?php if ($orderDetail['phone']): ?>
              <small><?= sanitize($orderDetail['phone']) ?></small>
              <?php endif; ?>
            </div>
            <div class="col-md-4">
              <strong>Адрес доставки:</strong><br>
              <small><?= formatShippingAddress($orderDetail['shipping_address'] ?? '') ?></small>
            </div>
            <div class="col-md-4">
              <strong>Дата заказа:</strong><br>
              <?= date('d.m.Y H:i', strtotime($orderDetail['created_at'])) ?>
              <br><br>
              <!-- Change status -->
              <form method="post" action="" class="d-flex align-items-center gap-8">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="id" value="<?= (int)$orderDetail['id'] ?>">
                <select name="status" class="form-control form-control-sm" style="width:auto;">
                  <?php foreach ($statuses as $st): ?>
                  <option value="<?= $st ?>" <?= $orderDetail['status'] === $st ? 'selected' : '' ?>>
                    <?= getOrderStatusLabel($st) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="az-btn az-btn-primary az-btn-sm">Сохранить</button>
              </form>
            </div>
          </div>

          <div class="table-responsive">
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
                  <td><code><?= sanitize($item['part_number']) ?></code></td>
                  <td><?= sanitize($item['part_name']) ?></td>
                  <td style="font-size:0.8rem;color:#888;"><?= sanitize($item['brand_name'] ?? '—') ?></td>
                  <td style="text-align:center;"><?= (int)$item['quantity'] ?></td>
                  <td style="text-align:right;"><?= formatPrice($item['unit_price']) ?></td>
                  <td style="text-align:right;"><strong><?= formatPrice($item['unit_price'] * $item['quantity']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <?php $admShipCost = (float)($orderDetail['shipping_cost'] ?? 0); ?>
                <?php if ($admShipCost > 0): ?>
                <tr>
                  <td colspan="5" style="text-align:right;color:#666;">Доставка:</td>
                  <td style="text-align:right;color:#666;"><?= formatPrice($admShipCost) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                  <td colspan="5" style="text-align:right;font-weight:700;">ИТОГО:</td>
                  <td style="text-align:right;font-weight:700;font-size:1.1rem;"><?= formatPrice($orderDetail['total_amount']) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <?php
          // ── Заявка поставщику (ветка «человек в Москве») ──
          // Готовый текст со списком деталей — менеджер копирует и отправляет
          // своему человеку в Москве / поставщику в мессенджере. Денег не двигает.
          $shipAddr = trim(preg_replace('/\s+/', ' ', strip_tags(formatShippingAddress($orderDetail['shipping_address'] ?? ''))));
          $custName = trim(($orderDetail['username'] ?? '') . '');
          $zayavka  = "ЗАЯВКА по заказу #" . (int)$orderDetail['id'] . "\n";
          $zayavka .= "Дата: " . date('d.m.Y H:i', strtotime($orderDetail['created_at'])) . "\n";
          if ($orderDetail['phone']) $zayavka .= "Покупатель: " . $orderDetail['phone'] . "\n";
          if ($shipAddr !== '')      $zayavka .= "Доставка: " . $shipAddr . "\n";
          $zayavka .= "\nДетали:\n";
          $ln = 1;
          foreach ($orderItems as $item) {
              $zayavka .= $ln++ . ". "
                        . trim(($item['brand_name'] ?? '') . ' ' . ($item['part_number'] ?? ''))
                        . " — " . ($item['part_name'] ?? '')
                        . " ×" . (int)$item['quantity'] . "\n";
          }
          ?>
          <div class="az-card" style="margin-top:18px;background:#faf7f2;border:1px solid #ece4d6;">
            <div class="az-card-body">
              <h4 style="margin:0 0 4px;font-size:1rem;"><i class="fa fa-paper-plane"></i> Заявка поставщику</h4>
              <p style="font-size:0.82rem;color:#8a8372;margin:0 0 10px;">
                Ветка «человек в Москве»: скопируйте заявку и отправьте её своему человеку/поставщику
                в мессенджере. При необходимости поправьте текст перед отправкой. Денег это не списывает.
              </p>
              <textarea id="zayavkaText" rows="<?= max(6, count($orderItems) + 6) ?>"
                        style="width:100%;font-family:monospace;font-size:0.82rem;padding:10px 12px;border:1px solid #d8d2c4;border-radius:8px;"><?= sanitize($zayavka) ?></textarea>
              <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="az-btn az-btn-primary az-btn-sm" onclick="copyZayavka()">
                  <i class="fa fa-copy"></i> Скопировать заявку
                </button>
                <a class="az-btn az-btn-outline az-btn-sm"
                   href="<?= APP_URL ?>/admin/messages.php?user=<?= (int)$orderDetail['user_id'] ?>&order=<?= (int)$orderDetail['id'] ?>">
                  <i class="fa fa-comments-o"></i> Переписка с покупателем
                </a>
              </div>
              <span id="zayavkaCopied" style="display:none;color:#1a7f4e;font-size:0.8rem;margin-left:4px;">Скопировано ✓</span>
            </div>
          </div>
          <script>
          function copyZayavka(){
            var t=document.getElementById('zayavkaText'); t.select(); t.setSelectionRange(0,99999);
            try { document.execCommand('copy'); } catch(e){}
            if (navigator.clipboard) { navigator.clipboard.writeText(t.value).catch(function(){}); }
            var m=document.getElementById('zayavkaCopied'); m.style.display='inline';
            setTimeout(function(){ m.style.display='none'; }, 2000);
          }
          </script>
        </div>
      </div>
      <?php endif; ?>

      <!-- Filters -->
      <div class="az-card mb-16">
        <div class="az-card-body">
          <form method="get" action="" class="d-flex align-items-center gap-8 flex-wrap">
            <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
              <option value="">Все статусы</option>
              <?php foreach ($statuses as $st): ?>
              <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= getOrderStatusLabel($st) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="user" class="form-control" style="width:200px;"
                   placeholder="Поиск покупателя..." value="<?= sanitize($filterUser) ?>">
            <button type="submit" class="az-btn az-btn-outline">Фильтр</button>
            <a href="<?= APP_URL ?>/admin/orders.php" class="az-btn az-btn-outline">Сбросить</a>
            <span style="margin-left:auto;color:#888;font-size:0.85rem;">Всего: <?= $total ?></span>
          </form>
        </div>
      </div>

      <!-- Orders table -->
      <div class="az-card">
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Покупатель</th>
                  <th>Email</th>
                  <th>Дата</th>
                  <th>Сумма</th>
                  <th>Статус</th>
                  <th>Изменить статус</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                  <td><strong>#<?= (int)$order['id'] ?></strong></td>
                  <td><?= sanitize($order['username']) ?></td>
                  <td style="font-size:0.8rem;color:#666;"><?= sanitize($order['email']) ?></td>
                  <td style="font-size:0.8rem;color:#888;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                  <td><strong><?= formatPrice($order['total_amount']) ?></strong></td>
                  <td>
                    <span class="badge badge-<?= getOrderStatusClass($order['status']) ?>">
                      <?= getOrderStatusLabel($order['status']) ?>
                    </span>
                  </td>
                  <td>
                    <form method="post" action="" class="d-flex align-items-center gap-4">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="status">
                      <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                      <select name="status" class="form-control form-control-sm" style="width:auto;font-size:0.78rem;">
                        <?php foreach ($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= getOrderStatusLabel($st) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="az-btn az-btn-sm az-btn-outline">OK</button>
                    </form>
                  </td>
                  <td>
                    <a href="?id=<?= (int)$order['id'] ?>" class="az-btn az-btn-outline az-btn-sm">Детали</a>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="8" style="text-align:center;color:#999;padding:24px;">Заказы не найдены</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($pages > 1): ?>
        <div class="az-card-footer">
          <div class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++):
              $q = array_merge($_GET, ['page' => $p]); unset($q['id']); ?>
            <a href="?<?= http_build_query($q) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
