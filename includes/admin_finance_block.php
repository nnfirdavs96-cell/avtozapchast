<?php
/**
 * Блок «Финансовая часть — все продавцы» для админки.
 * Тот же вид, что в кабинете продавца (.sl-fin-block / .sl-stats), но цифры
 * суммарные по всем магазинам. Ожидает $db (PDO) в области видимости.
 * Данные тянутся из api/admin_stats.php (доступ только админу).
 */
if (!isset($db)) { $db = getDB(); }
require_once dirname(__DIR__) . '/includes/seller_stats.php';

$finInit   = sellerStatsAll($db, '30d');
$chartInit = visitsChartData($db, null, '30d');
?>
<div class="sl-fin-block" data-endpoint="<?= APP_URL ?>/api/admin_stats.php" style="margin-bottom:24px;">
  <div class="sl-fin-top">
    <h3 class="sl-fin-h">Финансовая часть — все продавцы<?php if (!empty($finInit['sellers'])): ?> <span style="color:#8a8a95;font-weight:600;">(<?= (int)$finInit['sellers'] ?> магазинов)</span><?php endif; ?></h3>
    <div class="sl-fin-right">
      <div class="sl-fin-periods" role="tablist">
        <button type="button" class="sl-per" data-period="today">Сегодня</button>
        <button type="button" class="sl-per" data-period="7d">7 дней</button>
        <button type="button" class="sl-per sl-per--active" data-period="30d">30 дней</button>
        <button type="button" class="sl-per" data-period="all">Всё время</button>
      </div>
      <a href="<?= APP_URL ?>/api/admin_stats_csv.php?period=30d" class="sl-csv" data-csv="<?= APP_URL ?>/api/admin_stats_csv.php"><i class="fa fa-download"></i> CSV</a>
    </div>
  </div>

  <div class="sl-stats sl-fin-stats">
    <div class="sl-stat">
      <span class="sl-stat-n" data-fin="visits"><?= (int)$finInit['visits'] ?></span>
      <span class="sl-stat-l">Посещения магазинов</span>
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
      <span class="sl-stat-l">Сообщения покупателей</span>
    </div>
  </div>

  <div class="sl-chart-host"><?= visitsChartHtml($chartInit['points'], $chartInit['caption']) ?></div>
</div>

<script>
/* Переключатель периода сводной статистики (общий с кабинетом: тот же .sl-fin-block). */
(function(){
  var block = document.querySelector('.sl-fin-block');
  if (!block || block.dataset.bound) return;
  block.dataset.bound = '1';
  var ep = block.getAttribute('data-endpoint');
  function setBusy(on){ block.classList.toggle('is-busy', !!on); }
  function apply(data){ for (var k in data){ var el = block.querySelector('[data-fin="'+k+'"]'); if (el) el.textContent = data[k]; } }
  block.addEventListener('click', function(e){
    var btn = e.target.closest('.sl-per'); if (!btn) return;
    var period = btn.getAttribute('data-period');
    block.querySelectorAll('.sl-per').forEach(function(b){ b.classList.toggle('sl-per--active', b === btn); });
    var csv = block.querySelector('.sl-csv');                       // ссылку выгрузки — под выбранный период
    if (csv) csv.href = csv.getAttribute('data-csv') + '?period=' + encodeURIComponent(period);
    setBusy(true);
    fetch(ep + '?period=' + encodeURIComponent(period), { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){ if (d && d.ok){ apply({ visits:d.visits, orders:d.orders, pay_count:d.pay_count, pay_sum:d.pay_sum_html, messages:d.messages });
      var host = block.querySelector('.sl-chart-host'); if (host && typeof d.chart_html === 'string') host.innerHTML = d.chart_html; } })
      .catch(function(){})
      .then(function(){ setBusy(false); });
  });
})();
</script>
