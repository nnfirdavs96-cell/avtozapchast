<?php
/**
 * Кабинет продавца — финансы (маркетплейс, Фаза 3).
 *
 * Отвечает на единственный вопрос, который волнует продавца: «сколько мне должны
 * и когда заплатили». Страница только читает — начисления делает смена статуса
 * заказа, выплаты проводит владелец. Продавец ничего здесь изменить не может,
 * поэтому и форм тут нет: это выписка, а не панель управления.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/seller_finance.php';
$seller = requireSeller();

$db  = getDB();
$sid = (int)$seller['id'];

$ready   = sellerFinanceReady($db);
$sum     = $ready ? sellerFinanceSummary($db, $sid) : null;
$ledger  = [];
$payouts = [];

if ($ready) {
    // Журнал движений: показываем и номер заказа, чтобы строку можно было сверить.
    $st = $db->prepare(
        "SELECT sl.*, os.order_id
           FROM seller_ledger sl
           LEFT JOIN order_sellers os ON os.id = sl.order_seller_id
          WHERE sl.seller_id = ?
       ORDER BY sl.created_at DESC, sl.id DESC
          LIMIT 200"
    );
    $st->execute([$sid]);
    $ledger = $st->fetchAll();

    $st = $db->prepare("SELECT * FROM seller_payouts WHERE seller_id = ?
                     ORDER BY created_at DESC, id DESC LIMIT 100");
    $st->execute([$sid]);
    $payouts = $st->fetchAll();
}

$sellerNavActive = 'finance';
$pageTitle = 'Финансы — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">
    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-money"></i> Финансы</h1>
        <p class="sl-sub">Магазин: <?= sanitize($seller['shop_name']) ?></p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <?php if (!$ready): ?>
    <div class="sl-alert sl-alert-err">
      Раздел финансов ещё не активирован администратором. Обратитесь в поддержку.
    </div>

    <?php else: ?>

    <!-- Главная цифра — «сколько мне должны». Остальное поясняет, как она вышла. -->
    <div class="sl-fin-hero">
      <div class="sl-fin-hero-label">К выплате сейчас</div>
      <div class="sl-fin-hero-sum"><?= formatPrice($sum['balance']) ?></div>
      <div class="sl-fin-hero-hint">
        <?php if ($sum['pending'] > 0): ?>
          Ещё <b><?= formatPrice($sum['pending']) ?></b> в пути — попадёт в баланс, когда заказы будут доставлены.
        <?php else: ?>
          Деньги начисляются, когда заказ получает статус «Доставлен».
        <?php endif; ?>
      </div>
    </div>

    <div class="sl-stats">
      <div class="sl-stat">
        <span class="sl-stat-n sl-green"><?= formatPrice($sum['earned']) ?></span>
        <span class="sl-stat-l">Заработано всего</span>
      </div>
      <div class="sl-stat">
        <span class="sl-stat-n sl-muted"><?= formatPrice($sum['commission']) ?></span>
        <span class="sl-stat-l">Комиссия площадки</span>
      </div>
      <div class="sl-stat">
        <span class="sl-stat-n"><?= formatPrice($sum['paid']) ?></span>
        <span class="sl-stat-l">Уже выплачено</span>
      </div>
      <div class="sl-stat">
        <span class="sl-stat-n sl-amber"><?= formatPrice($sum['pending']) ?></span>
        <span class="sl-stat-l">В пути (не доставлено)</span>
      </div>
    </div>

    <!-- Выплаты -->
    <h3 class="sl-fin-h">Выплаты</h3>
    <?php if (!$payouts): ?>
    <div class="sl-empty"><i class="fa fa-money"></i><p>Выплат ещё не было.</p></div>
    <?php else: ?>
    <div class="sl-fin-table-wrap">
      <table class="sl-fin-table">
        <thead>
          <tr><th>Дата</th><th>Сумма</th><th>Способ</th><th>Номер перевода</th><th>Период</th></tr>
        </thead>
        <tbody>
        <?php foreach ($payouts as $p): ?>
          <tr>
            <td><?= date('d.m.Y', strtotime($p['created_at'])) ?></td>
            <td><b><?= formatPrice((float)$p['amount']) ?></b></td>
            <td><?= sanitize(sellerPayoutMethodLabel($p['method'])) ?></td>
            <td><?= $p['reference'] ? sanitize($p['reference']) : '—' ?></td>
            <td>
              <?php if ($p['period_from'] && $p['period_to']): ?>
                <?= date('d.m.Y', strtotime($p['period_from'])) ?> — <?= date('d.m.Y', strtotime($p['period_to'])) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Полная история движений: из чего сложился баланс -->
    <h3 class="sl-fin-h">История начислений</h3>
    <?php if (!$ledger): ?>
    <div class="sl-empty"><i class="fa fa-list"></i><p>Движений пока нет — они появятся после первой доставки.</p></div>
    <?php else: ?>
    <div class="sl-fin-table-wrap">
      <table class="sl-fin-table">
        <thead>
          <tr><th>Дата</th><th>Операция</th><th>Заказ</th><th class="sl-ta-r">Сумма</th></tr>
        </thead>
        <tbody>
        <?php foreach ($ledger as $l): $amt = (float)$l['amount']; ?>
          <tr>
            <td><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
            <td><?= sanitize(sellerLedgerTypeLabel($l['type'])) ?>
              <?php if (!empty($l['note'])): ?><span class="sl-muted">· <?= sanitize($l['note']) ?></span><?php endif; ?>
            </td>
            <td><?= $l['order_id'] ? '#' . (int)$l['order_id'] : '—' ?></td>
            <td class="sl-ta-r <?= $amt < 0 ? 'sl-red' : 'sl-green' ?>">
              <?= $amt > 0 ? '+' : '−' ?><?= formatPrice(abs($amt)) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="sl-how">
      <h3>Как считаются деньги</h3>
      <ol>
        <li>Покупатель оформляет заказ — ваша часть становится отдельным подзаказом.</li>
        <li>Вы ведёте его по статусам. Пока заказ не <b>доставлен</b>, деньги не начисляются.</li>
        <li>После доставки на баланс падает сумма заказа <b>за вычетом комиссии площадки</b>
            (<?= rtrim(rtrim(number_format((float)$seller['commission_percent'], 2, '.', ''), '0'), '.') ?>%).</li>
        <li>Если доставленный заказ отменяется, начисление сторнируется.</li>
        <li>Владелец перечисляет накопленный баланс и отмечает выплату — она появится в таблице выше.</li>
      </ol>
    </div>

    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
