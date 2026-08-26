<?php
/**
 * Оплаты заказов — админка (Фаза 3b, каркас).
 *
 * Пока эквайринга нет, деньги приходят вне сайта: наличными при получении, переводом
 * на карту, в офисе. Раньше этот факт нигде не фиксировался — заказ был либо
 * «доставлен», либо нет, а получены ли деньги, знал только владелец. Здесь он
 * отмечает оплату, и она попадает в тот же журнал, куда потом будет писать банк.
 *
 * Когда эквайринг подключат, страница не изменится: подтверждения начнут приходить
 * на webhook, а ручная отметка останется для наличных.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/payments/gateway.php';
requireRole(['admin', 'superadmin']);

$db    = getDB();
$csrf  = generateCsrfToken();
$ready = paymentsReady($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
    } elseif (!$ready) {
        flashMessage('danger', 'Миграция платежей не применена: php sql/migrate_payments.php');
    } else {
        $oid = (int)($_POST['order_id'] ?? 0);
        $do  = (string)($_POST['do'] ?? '');
        $uid = (int)($_SESSION['user_id'] ?? 0);

        if ($do === 'paid') {
            // Сумму берём из самого заказа, а не из формы: иначе опечатка в поле
            // тихо закрыла бы заказ неполным платежом.
            $st = $db->prepare("SELECT total_amount FROM orders WHERE id = ? LIMIT 1");
            $st->execute([$oid]);
            $total = (float)$st->fetchColumn();
            [$ok, $msg] = paymentMarkPaid($db, $oid, $total, 'manual', null, null, $uid);
            flashMessage($ok ? 'success' : 'danger', $msg);
        } elseif ($do === 'refund') {
            $st = $db->prepare("SELECT total_amount FROM orders WHERE id = ? LIMIT 1");
            $st->execute([$oid]);
            [$ok, $msg] = paymentMarkRefunded($db, $oid, (float)$st->fetchColumn(), 'manual', $uid);
            flashMessage($ok ? 'success' : 'danger', $msg);
        }
    }
    redirect(APP_URL . '/admin/payments.php' . (isset($_GET['status']) ? '?status=' . urlencode((string)$_GET['status']) : ''));
}

$filter = (string)($_GET['status'] ?? 'unpaid');
$valid  = ['unpaid','pending','paid','refunded','failed','all'];
if (!in_array($filter, $valid, true)) $filter = 'unpaid';

$rows = [];
$counts = [];
if ($ready) {
    $where = $filter === 'all' ? '' : 'WHERE o.payment_status = ' . $db->quote($filter);
    $rows = $db->query(
        "SELECT o.id, o.total_amount, o.status, o.payment_status, o.payment_method,
                o.paid_at, o.created_at, u.username, u.phone,
                (SELECT COUNT(*) FROM payment_transactions t WHERE t.order_id = o.id) AS tx_count
           FROM orders o
           LEFT JOIN users u ON u.id = o.user_id
         $where
       ORDER BY o.created_at DESC
          LIMIT 200"
    )->fetchAll();

    foreach ($db->query("SELECT payment_status, COUNT(*) n FROM orders GROUP BY payment_status") as $r) {
        $counts[$r['payment_status']] = (int)$r['n'];
    }
}

$pageTitle = 'Оплаты — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('payments'); ?>

  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Оплаты заказов</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <?php if (!$ready): ?>
      <div class="az-card"><div class="az-card-body">
        <p><b>Раздел не активирован.</b> Примените миграцию:</p>
        <pre style="background:#f6f6f8;padding:12px;border-radius:8px;overflow:auto;">php sql/migrate_payments.php</pre>
      </div></div>

      <?php else: ?>
      <div class="az-card mb-16"><div class="az-card-body">
        <div class="d-flex align-items-center gap-8 flex-wrap" style="justify-content:space-between;">
          <div class="d-flex gap-8 flex-wrap">
            <?php foreach (['unpaid','pending','paid','refunded','failed','all'] as $st2): ?>
            <a href="?status=<?= $st2 ?>" class="az-btn <?= $filter===$st2?'az-btn-primary':'az-btn-outline' ?>">
              <?= $st2 === 'all' ? 'Все' : sanitize(paymentStatusLabel($st2)) ?>
              <?= $st2 !== 'all' ? ' (' . ($counts[$st2] ?? 0) . ')' : '' ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <p class="text-muted mb-0" style="margin-top:10px;font-size:.84rem;">
          Активный способ приёма: <b><?= sanitize(paymentProvider()->title()) ?></b>.
          Онлайн-эквайринг подключится отдельным адаптером, когда будет договор с банком —
          страница при этом не изменится.
        </p>
      </div></div>

      <div class="az-card"><div class="az-card-body p-0">
        <div class="table-responsive">
          <table class="az-table">
            <thead>
              <tr>
                <th>Заказ</th><th>Покупатель</th><th>Способ</th>
                <th style="text-align:right;">Сумма</th>
                <th>Статус заказа</th><th>Оплата</th><th>Действия</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">Заказов в этом статусе нет.</td></tr>
            <?php else: foreach ($rows as $o): ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/admin/orders.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a>
                  <div class="text-muted" style="font-size:.76rem;"><?= date('d.m.Y', strtotime($o['created_at'])) ?></div>
                </td>
                <td>
                  <?= sanitize($o['username'] ?? '—') ?>
                  <?php if (!empty($o['phone'])): ?>
                  <div class="text-muted" style="font-size:.76rem;"><?= sanitize($o['phone']) ?></div>
                  <?php endif; ?>
                </td>
                <td style="font-size:.82rem;"><?= sanitize($o['payment_method']) ?></td>
                <td style="text-align:right;font-weight:700;white-space:nowrap;"><?= formatPrice((float)$o['total_amount']) ?></td>
                <td style="font-size:.82rem;"><?= sanitize(getOrderStatusLabel($o['status'])) ?></td>
                <td>
                  <span class="pay_badge pay_badge--<?= sanitize($o['payment_status']) ?>">
                    <?= sanitize(paymentStatusLabel($o['payment_status'])) ?>
                  </span>
                  <?php if (!empty($o['paid_at'])): ?>
                  <div class="text-muted" style="font-size:.74rem;"><?= date('d.m.Y H:i', strtotime($o['paid_at'])) ?></div>
                  <?php endif; ?>
                  <?php if ((int)$o['tx_count'] > 0): ?>
                  <div class="text-muted" style="font-size:.72rem;">записей в журнале: <?= (int)$o['tx_count'] ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($o['payment_status'] !== 'paid' && $o['payment_status'] !== 'refunded'): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="do" value="paid">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <button type="submit" class="az-btn az-btn-sm az-btn-success"
                            onclick="return confirm('Отметить заказ оплаченным на <?= number_format((float)$o['total_amount'], 2, '.', ' ') ?>?');">
                      Оплачен
                    </button>
                  </form>
                  <?php elseif ($o['payment_status'] === 'paid'): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="do" value="refund">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <button type="submit" class="az-btn az-btn-sm az-btn-outline"
                            onclick="return confirm('Отметить, что деньги возвращены покупателю?');">
                      Возврат средств
                    </button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
