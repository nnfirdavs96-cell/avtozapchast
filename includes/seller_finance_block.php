<?php
/**
 * Блок «Финансовая часть» для кабинета продавца.
 * Ожидает в области видимости: $db (PDO) и $sid (int, id ТЕКУЩЕГО продавца).
 * Четыре карточки + переключатель периода; цифры обновляются без перезагрузки
 * через api/seller_stats.php. По умолчанию период — 30 дней.
 *
 * Дизайн карточек 1:1 с .sl-stats/.sl-stat (белые, рамка, крупное число).
 */
if (!isset($sid))  { $sid = 0; }
if (!isset($db))   { $db  = getDB(); }
require_once dirname(__DIR__) . '/includes/seller_stats.php';

$finInit   = sellerStats($db, (int)$sid, '30d');   // стартовые цифры — серверно
$chartInit = visitsChartData($db, (int)$sid, '30d');
?>
<div class="sl-fin-block" data-endpoint="<?= APP_URL ?>/api/seller_stats.php">
  <div class="sl-fin-top">
    <h3 class="sl-fin-h">Финансовая часть</h3>
    <div class="sl-fin-periods" role="tablist">
      <button type="button" class="sl-per" data-period="today">Сегодня</button>
      <button type="button" class="sl-per" data-period="7d">7 дней</button>
      <button type="button" class="sl-per sl-per--active" data-period="30d">30 дней</button>
      <button type="button" class="sl-per" data-period="all">Всё время</button>
    </div>
  </div>

  <div class="sl-stats sl-fin-stats">
    <div class="sl-stat">
      <span class="sl-stat-n" data-fin="visits"><?= (int)$finInit['visits'] ?></span>
      <span class="sl-stat-l">Посещения магазина</span>
    </div>
    <div class="sl-stat">
      <span class="sl-stat-n" data-fin="orders"><?= (int)$finInit['orders'] ?></span>
      <span class="sl-stat-l">Заказы</span>
    </div>
    <div class="sl-stat">
      <span class="sl-stat-n sl-green" data-fin="pay_count"><?= (int)$finInit['pay_count'] ?></span>
      <span class="sl-stat-l">Оплаты</span>
      <span class="sl-fin-sub" data-fin="pay_sum"><?= formatPrice($finInit['pay_sum']) ?></span>
    </div>
    <div class="sl-stat">
      <span class="sl-stat-n" data-fin="messages"><?= (int)$finInit['messages'] ?></span>
      <span class="sl-stat-l">Сообщения от покупателей</span>
    </div>
  </div>

  <div class="sl-chart-host"><?= visitsChartHtml($chartInit['points'], $chartInit['caption']) ?></div>
</div>

<script>
/* Переключатель периода «Финансовой части»: тянет цифры из api/seller_stats.php
   и обновляет карточки без перезагрузки. Guard — чтобы не навесить дважды, если
   блок на странице один (на Обзоре/Финансах он один). */
(function(){
  var block = document.querySelector('.sl-fin-block');
  if (!block || block.dataset.bound) return;
  block.dataset.bound = '1';
  var ep = block.getAttribute('data-endpoint');

  function setBusy(on){ block.classList.toggle('is-busy', !!on); }

  function apply(data){
    for (var k in data){
      var el = block.querySelector('[data-fin="'+k+'"]');
      // pay_sum — готовый HTML (число + <span class="cur-sym">смн</span>), остальное — текст.
      if (el) el[k === 'pay_sum' ? 'innerHTML' : 'textContent'] = data[k];
    }
  }

  block.addEventListener('click', function(e){
    var btn = e.target.closest('.sl-per'); if (!btn) return;
    var period = btn.getAttribute('data-period');
    block.querySelectorAll('.sl-per').forEach(function(b){ b.classList.toggle('sl-per--active', b === btn); });
    setBusy(true);
    fetch(ep + '?period=' + encodeURIComponent(period), { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d && d.ok){
          apply({ visits: d.visits, orders: d.orders, pay_count: d.pay_count,
                  pay_sum: d.pay_sum_html, messages: d.messages });
          var host = block.querySelector('.sl-chart-host');
          if (host && typeof d.chart_html === 'string') host.innerHTML = d.chart_html;  // график за период
        }
      })
      .catch(function(){})
      .then(function(){ setBusy(false); });
  });
})();
</script>
