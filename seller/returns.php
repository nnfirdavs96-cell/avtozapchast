<?php
/**
 * Кабинет продавца — возвраты (маркетплейс, Фаза 4).
 *
 * Продавец рассматривает заявки по СВОИМ заказам. Одобрение сразу снимает с его
 * баланса долю за возвращённый товар — то, что ему было начислено, за вычетом
 * комиссии. Полную стоимость не снимаем: комиссию площадка возвращает вместе с
 * товаром, а не перекладывает на продавца.
 *
 * Если продавец отказал, а покупатель не согласен — последнее слово за
 * администратором (`admin/returns.php`). Отдельной сущности «спор» нет: это то же
 * обращение, рассмотренное второй инстанцией.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/returns.php';
$seller = requireSeller();

$db   = getDB();
$sid  = (int)$seller['id'];
$csrf = generateCsrfToken();
$ready = returnsReady($db);

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности.');
    } else {
        // $sid третьим аргументом — продавец решает только по своим заявкам.
        [$ok, $msg] = returnResolve(
            $db,
            (int)($_POST['return_id'] ?? 0),
            (string)($_POST['decision'] ?? ''),
            (string)($_POST['resolution'] ?? ''),
            (int)($_SESSION['user_id'] ?? 0),
            $sid
        );
        flashMessage($ok ? 'success' : 'danger', $msg);
    }
    redirect(APP_URL . '/seller/returns.php');
}

$filter = (string)($_GET['status'] ?? '');
$rows   = $ready ? returnList($db, array_filter([
    'seller_id' => $sid,
    'status'    => in_array($filter, ['requested','approved','rejected','cancelled'], true) ? $filter : null,
])) : [];

$counts = [];
if ($ready) {
    $c = $db->prepare("SELECT status, COUNT(*) n FROM order_returns WHERE seller_id = ? GROUP BY status");
    $c->execute([$sid]);
    foreach ($c->fetchAll() as $r) $counts[$r['status']] = (int)$r['n'];
}

$sellerNavActive = 'returns';
$pageTitle = 'Возвраты — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">
    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-undo"></i> Возвраты</h1>
        <p class="sl-sub">Магазин: <?= sanitize($seller['shop_name']) ?></p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <?php if ($flash = getFlashMessage()): ?>
    <div class="sl-alert sl-alert-<?= $flash['type']==='success'?'ok':'err' ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!$ready): ?>
    <div class="sl-alert sl-alert-err">Раздел возвратов ещё не активирован администратором.</div>

    <?php else: ?>
    <div class="sl-toolbar" style="flex-wrap:wrap;gap:6px;">
      <a href="?" class="sl-btn <?= $filter===''?'sl-btn-primary':'' ?>">Все</a>
      <?php foreach (['requested','approved','rejected','cancelled'] as $st2): ?>
      <a href="?status=<?= $st2 ?>" class="sl-btn <?= $filter===$st2?'sl-btn-primary':'' ?>">
        <?= sanitize(returnStatusLabel($st2)) ?><?= isset($counts[$st2]) ? ' (' . $counts[$st2] . ')' : '' ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
    <div class="sl-empty"><i class="fa fa-undo"></i><p>Заявок на возврат нет<?= $filter ? ' в этом статусе' : '' ?>.</p></div>
    <?php else: ?>

    <?php foreach ($rows as $r): ?>
    <div class="sl-order">
      <div class="sl-order-head">
        <div>
          <strong>Заявка #<?= (int)$r['id'] ?></strong>
          <span class="sl-order-date">по заказу #<?= (int)$r['order_id'] ?> · <?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
        </div>
        <span class="sl-badge ret_badge--<?= sanitize($r['status']) ?>"><?= sanitize(returnStatusLabel($r['status'])) ?></span>
      </div>

      <div class="sl-order-money">
        <span>Покупатель: <b><?= sanitize($r['username'] ?? '—') ?></b></span>
        <span>Причина: <b><?= sanitize(returnReasons()[$r['reason']] ?? $r['reason']) ?></b></span>
        <span>Сумма возврата: <b><?= formatPrice((float)$r['amount']) ?></b></span>
      </div>

      <?php if (!empty($r['part_name'])): ?>
      <div class="sl-order-buyer">
        <i class="fa fa-cube"></i> <?= sanitize($r['part_name']) ?>
        <span class="sl-muted"><?= sanitize($r['part_number'] ?? '') ?></span>
      </div>
      <?php else: ?>
      <div class="sl-order-buyer sl-muted"><i class="fa fa-cubes"></i> Возврат всей вашей части заказа</div>
      <?php endif; ?>

      <?php if (!empty($r['comment'])): ?>
      <div class="sl-order-buyer">«<?= sanitize($r['comment']) ?>»</div>
      <?php endif; ?>

      <?php if ($r['status'] === 'requested'): ?>
      <?php
        // Показываем заранее, сколько снимут с баланса: решение о деньгах не должно
        // быть сюрпризом уже после нажатия кнопки.
        $share = returnPayoutShare((float)$r['amount'], (float)$r['subtotal'], (float)$r['payout_amount']);
      ?>
      <div class="ret_warn">
        При одобрении с вашего баланса будет снято <b><?= formatPrice($share) ?></b>
        — ваша доля за этот товар (комиссия площадки возвращается вместе с ним).
      </div>
      <form method="post" class="sl-order-acts">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <input type="hidden" name="return_id" value="<?= (int)$r['id'] ?>">
        <input type="text" name="resolution" maxlength="500" placeholder="Комментарий покупателю"
               style="flex:1 1 220px;padding:8px 10px;border:1px solid #d9d9e3;border-radius:8px;">
        <button type="submit" name="decision" value="approved" class="sl-btn sl-btn-primary"
                onclick="return confirm('Одобрить возврат? С баланса будет снята ваша доля.');">
          Одобрить возврат
        </button>
        <button type="submit" name="decision" value="rejected" class="sl-btn">Отказать</button>
      </form>
      <?php elseif (!empty($r['resolution'])): ?>
      <div class="sl-order-buyer sl-muted">Ваш ответ: <?= sanitize($r['resolution']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
