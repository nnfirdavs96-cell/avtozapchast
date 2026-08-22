<?php
/**
 * Кабинет продавца — мои заказы (подзаказы из order_sellers).
 *
 * Продавец видит и меняет ТОЛЬКО свою часть заказа: строку order_sellers со своим
 * seller_id и её позиции. Данные покупателя (адрес/телефон) показываем лишь после
 * принятия заказа — до этого продавцу они не нужны.
 *
 * Комиссия и сумма к выплате зафиксированы в момент заказа (order_sellers), поэтому
 * здесь их только показываем, не пересчитываем.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/seller_finance.php';
$seller = requireSeller();

$db   = getDB();
$sid  = (int)$seller['id'];
$csrf = generateCsrfToken();

// Схема Фазы 2 применена?
$ready = false;
try {
    $ready = (bool)$db->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_sellers'"
    )->fetchColumn();
} catch (Throwable $e) { $ready = false; }

// ── Смена статуса своего подзаказа ───────────────────────────────────────────
// Разрешаем только переходы вперёд по цепочке + отмену, и только для СВОЕГО
// подзаказа (WHERE seller_id = ?) — чужой чужим статусом не тронуть.
$FLOW = ['pending' => 'В обработке', 'processing' => 'Собирается',
         'shipped' => 'Отправлен',   'delivered'  => 'Доставлен', 'cancelled' => 'Отменён'];

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности.');
    } else {
        $osId   = (int)($_POST['os_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!isset($FLOW[$status])) {
            flashMessage('danger', 'Неизвестный статус.');
        } else {
            $upd = $db->prepare("UPDATE order_sellers SET status = ?, updated_at = NOW()
                                  WHERE id = ? AND seller_id = ?");
            $upd->execute([$status, $osId, $sid]);
            // Деньги двигаются вслед за статусом: «доставлен» — начисление в баланс,
            // отмена уже доставленного — сторно. Функция идемпотентна, поэтому
            // повторные клики и возвраты по цепочке долг не удваивают.
            if ($upd->rowCount()) sellerLedgerSyncOrderSeller($db, $osId);
            flashMessage($upd->rowCount() ? 'success' : 'danger',
                $upd->rowCount() ? 'Статус обновлён: ' . $FLOW[$status] : 'Заказ не найден.');
        }
    }
    redirect(APP_URL . '/seller/orders.php');
}

// ── Список моих подзаказов ───────────────────────────────────────────────────
$filter = (string)($_GET['status'] ?? '');
$rows = [];
$counts = [];
if ($ready) {
    $where  = 'os.seller_id = ?';
    $params = [$sid];
    if (isset($FLOW[$filter])) { $where .= ' AND os.status = ?'; $params[] = $filter; }

    $st = $db->prepare(
        "SELECT os.*, o.created_at AS order_date, o.shipping_address, o.payment_method,
                o.status AS order_status, u.username, u.phone,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_seller_id = os.id) AS items_count
           FROM order_sellers os
           JOIN orders o ON o.id = os.order_id
           LEFT JOIN users u ON u.id = o.user_id
          WHERE $where
       ORDER BY (os.status='pending') DESC, os.created_at DESC
          LIMIT 200"
    );
    $st->execute($params);
    $rows = $st->fetchAll();

    $cs = $db->prepare("SELECT status, COUNT(*) c FROM order_sellers WHERE seller_id = ? GROUP BY status");
    $cs->execute([$sid]);
    foreach ($cs->fetchAll() as $r) $counts[$r['status']] = (int)$r['c'];

    // Позиции всех показанных подзаказов — одним запросом.
    $items = [];
    if ($rows) {
        $ids = array_column($rows, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $is  = $db->prepare(
            "SELECT oi.*, p.name, p.part_number, p.images
               FROM order_items oi
               LEFT JOIN parts p ON p.id = oi.part_id
              WHERE oi.order_seller_id IN ($ph)"
        );
        $is->execute($ids);
        foreach ($is->fetchAll() as $r) $items[(int)$r['order_seller_id']][] = $r;
    }
}

$sellerNavActive = 'orders';
$pageTitle = 'Мои заказы — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">
    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-shopping-bag"></i> Мои заказы</h1>
        <p class="sl-sub">Магазин: <?= sanitize($seller['shop_name']) ?><?= $rows ? ' · показано ' . count($rows) : '' ?></p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <?php if ($flash = getFlashMessage()): ?>
    <div class="sl-alert sl-alert-<?= $flash['type']==='success'?'ok':'err' ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!$ready): ?>
    <div class="sl-alert sl-alert-err">
      Раздел заказов ещё не активирован администратором. Обратитесь в поддержку.
    </div>

    <?php else: ?>
    <div class="sl-toolbar" style="flex-wrap:wrap;gap:6px;">
      <a href="?" class="sl-btn <?= $filter===''?'sl-btn-primary':'' ?>">Все</a>
      <?php foreach ($FLOW as $k => $label): ?>
      <a href="?status=<?= $k ?>" class="sl-btn <?= $filter===$k?'sl-btn-primary':'' ?>">
        <?= $label ?><?= isset($counts[$k]) ? ' (' . $counts[$k] . ')' : '' ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
    <div class="sl-empty">
      <i class="fa fa-shopping-bag"></i>
      <p>Заказов пока нет<?= $filter ? ' в этом статусе' : '' ?>.</p>
    </div>
    <?php else: ?>

    <?php foreach ($rows as $r):
      $accepted = !in_array($r['status'], ['pending', 'cancelled'], true);
    ?>
    <div class="sl-order">
      <div class="sl-order-head">
        <div>
          <strong>Заказ #<?= (int)$r['order_id'] ?></strong>
          <span class="sl-order-date"><?= date('d.m.Y H:i', strtotime($r['order_date'])) ?></span>
        </div>
        <span class="sl-badge sl-badge-<?= sanitize($r['status']) ?>"><?= $FLOW[$r['status']] ?? sanitize($r['status']) ?></span>
      </div>

      <div class="sl-order-items">
        <?php foreach ($items[(int)$r['id']] ?? [] as $it): ?>
        <div class="sl-order-item">
          <span class="sl-oi-name"><?= sanitize($it['name'] ?? '—') ?></span>
          <span class="sl-oi-art"><?= sanitize($it['part_number'] ?? '') ?></span>
          <span class="sl-oi-qty">× <?= (int)$it['quantity'] ?></span>
          <span class="sl-oi-sum"><?= formatPrice((float)$it['unit_price'] * (int)$it['quantity']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="sl-order-money">
        <span>Сумма: <b><?= formatPrice((float)$r['subtotal']) ?></b></span>
        <?php if ((float)$r['commission_percent'] > 0): ?>
        <span class="sl-muted">комиссия <?= rtrim(rtrim(number_format((float)$r['commission_percent'], 2, '.', ''), '0'), '.') ?>%
          (<?= formatPrice((float)$r['commission_amount']) ?>)</span>
        <?php endif; ?>
        <span>К выплате: <b><?= formatPrice((float)$r['payout_amount']) ?></b></span>
      </div>

      <?php if ($accepted): ?>
      <div class="sl-order-buyer">
        <i class="fa fa-map-marker"></i> <?= sanitize($r['shipping_address'] ?: '—') ?>
        <?php if (!empty($r['phone'])): ?> · <i class="fa fa-phone"></i> <?= sanitize($r['phone']) ?><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="sl-order-buyer sl-muted">
        <i class="fa fa-lock"></i> Адрес и телефон покупателя откроются после принятия заказа
      </div>
      <?php endif; ?>

      <?php if ($r['status'] !== 'delivered' && $r['status'] !== 'cancelled'): ?>
      <form method="post" class="sl-order-acts">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="os_id" value="<?= (int)$r['id'] ?>">
        <?php
          // Следующий шаг по цепочке + возможность отменить.
          $next = ['pending' => 'processing', 'processing' => 'shipped', 'shipped' => 'delivered'][$r['status']] ?? '';
        ?>
        <?php if ($next): ?>
        <button type="submit" name="status" value="<?= $next ?>" class="sl-btn sl-btn-primary">
          <i class="fa fa-arrow-right"></i> <?= $FLOW[$next] ?>
        </button>
        <?php endif; ?>
        <button type="submit" name="status" value="cancelled" class="sl-btn"
                onclick="return confirm('Отменить эту часть заказа? Покупатель увидит отмену.');">
          Отменить
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
