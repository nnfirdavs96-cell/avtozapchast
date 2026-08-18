<?php
/**
 * Блок карточек поставщика AutoEuro на странице поиска.
 *
 * Ожидает: $aeCards (карточки), $aeMode ('A'|'B'), и для поиска по названию —
 * $aeIsName(bool), $aeHasMore(bool), $aePageSize(int), $q(строка запроса).
 *
 * Карточки: lazy=false (поиск по артикулу) — цена готова; lazy=true (поиск по
 * названию) — цена подгружается живьём (api/vin_price.php). Кнопка «Показать ещё»
 * (только для поиска по названию) дозагружает следующие порции (api/supplier_search.php).
 * «Купить» → модалка → POST api/vin_order_request.php. Нужен <meta name="csrf">.
 */
if (empty($aeCards)) return;
require_once dirname(__DIR__) . '/parts/supplier_card_render.php';

$aeMode     = ($aeMode ?? 'A') === 'B' ? 'B' : 'A';
$aeIsName   = !empty($aeIsName);
$aeHasMore  = !empty($aeHasMore);
$aePageSize = (int)($aePageSize ?? 8);
$aeQ        = (string)($q ?? '');
?>
<div class="sup-section">
  <div class="sup-head">
    <h3>Найдено у поставщика <span>AutoEuro</span></h3>
    <p>Детали, которых нет на нашем складе — привозим под заказ. Нажмите «Купить», и менеджер оформит заявку и уточнит доставку до Худжанда.</p>
  </div>

  <div class="row sup-grid" id="supGrid">
    <?php foreach ($aeCards as $c) { echo supplierCardHtml($c); } ?>
  </div>

  <?php if ($aeIsName && $aeHasMore): ?>
  <div class="sup-more-wrap">
    <button type="button" id="supMoreBtn"
            data-q="<?php echo htmlspecialchars($aeQ, ENT_QUOTES, 'UTF-8'); ?>"
            data-offset="<?php echo (int)$aePageSize; ?>"
            data-limit="<?php echo (int)$aePageSize; ?>">
      Показать ещё
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- ── Модалка выбора доставки (заявка на деталь поставщика) ─────────────────── -->
<div id="supDlvModal" class="sup-dlv-modal" onclick="if(event.target===this)supCloseDelivery()">
  <div class="sup-dlv-card">
    <button type="button" class="sup-dlv-x" onclick="supCloseDelivery()" aria-label="Закрыть">&times;</button>
    <div class="sup-dlv-head">
      <div class="sup-dlv-name">Деталь</div>
      <div class="sup-dlv-sub"></div>
    </div>
    <div class="sup-dlv-form">
      <div class="sup-dlv-label">Выберите вариант доставки</div>
      <div class="sup-dlv-list"></div>
      <div class="sup-dlv-row">
        <label>Количество</label>
        <input type="number" class="sup-dlv-qty" value="1" min="1" max="99" inputmode="numeric">
      </div>
      <div class="sup-dlv-row">
        <label>Комментарий (необязательно)</label>
        <textarea class="sup-dlv-comment" rows="2" placeholder="Адрес доставки, пожелания…"></textarea>
      </div>
      <div class="sup-dlv-note"><i class="fa fa-info-circle"></i> Это заявка менеджеру: он уточнит наличие у поставщика и свяжется с вами. Оплата — после подтверждения.</div>
      <button type="button" class="sup-dlv-submit" onclick="supSubmitDelivery()">Оформить заявку</button>
    </div>
    <div class="sup-dlv-msg"></div>
  </div>
</div>

<script>
(function(){
  var SUP_MODE = <?php echo json_encode($aeMode); ?>;
  var SUP_URL  = <?php echo json_encode(APP_URL); ?>;
  var SUP_SEL = 0, SUP_META = null, SUP_OPTS = [];
  function fmtDate(d){ var m=/^(\d{4})-(\d{2})-(\d{2})/.exec(d||''); return m ? m[3]+'.'+m[2]+'.'+m[1] : ''; }
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function offerFull(o){
    var st = o.in_stock ? 'В наличии' : 'Под заказ', eta = '';
    if (o.delivery_total){
      if (SUP_MODE==='B' && o.delivery_ae && o.delivery_days>0){
        eta = 'Москва '+fmtDate(o.delivery_ae)+' + '+o.delivery_days+' дн → '+fmtDate(o.delivery_total);
      } else { eta = 'в Худжанде ≈ '+fmtDate(o.delivery_total); }
    }
    return st + (eta ? ' · '+eta : '');
  }

  /* ── Ленивая подгрузка цены для карточек словаря (в т.ч. дозагруженных) ──── */
  function supLoadPrices(scope){
    var root = scope || document;
    var btns = root.querySelectorAll('.sup-buy-btn[data-lazy="1"]');
    Array.prototype.forEach.call(btns, function(btn){
      if (btn.getAttribute('data-loading')) return;
      btn.setAttribute('data-loading','1');
      var card = btn.closest('.sup-card');
      var oem = btn.getAttribute('data-oem')||'', brand = btn.getAttribute('data-brand')||'';
      if(!oem){ if(card) card.remove(); return; }
      fetch(SUP_URL+'/api/vin_price.php?oem='+encodeURIComponent(oem)+'&brand='+encodeURIComponent(brand), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
          if(!d || !d.found || !d.options || !d.options.length){ if(card) card.remove(); return; }
          var opts = d.options, best = opts[0];
          btn.setAttribute('data-offers', JSON.stringify(opts));
          btn.removeAttribute('data-lazy');
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-shopping-cart"></i> Купить';
          if(card){
            var pr = card.querySelector('.sup-price');
            if(pr){ pr.classList.remove('sup-price-load'); pr.innerHTML = 'от <strong>'+best.price+'</strong>'; }
            var thumb = card.querySelector('.sup-thumb');
            if(thumb && !thumb.querySelector('.sup-badge')){
              var b = document.createElement('span');
              b.className = 'sup-badge ' + (best.in_stock ? 'in' : 'pre');
              b.textContent = best.in_stock ? 'В наличии' : 'Под заказ';
              thumb.appendChild(b);
            }
            var inner = card.querySelector('.product_content_inner');
            if(inner && best.delivery_total && !inner.querySelector('.sup-eta')){
              var e = document.createElement('p');
              e.className = 'sup-eta';
              e.innerHTML = '<i class="fa fa-truck"></i> в Худжанде ≈ '+fmtDate(best.delivery_total);
              inner.appendChild(e);
            }
          }
        })
        .catch(function(){ if(card) card.remove(); });
    });
  }
  if (document.readyState !== 'loading') supLoadPrices();
  else document.addEventListener('DOMContentLoaded', function(){ supLoadPrices(); });

  /* ── Кнопка «Показать ещё» — дозагрузка следующей порции карточек ────────── */
  var moreBtn = document.getElementById('supMoreBtn');
  if (moreBtn){
    moreBtn.addEventListener('click', function(){
      if (moreBtn.getAttribute('data-busy')) return;
      moreBtn.setAttribute('data-busy','1');
      var q = moreBtn.getAttribute('data-q')||'';
      var offset = parseInt(moreBtn.getAttribute('data-offset')||'0',10);
      var limit  = parseInt(moreBtn.getAttribute('data-limit')||'8',10);
      var orig = moreBtn.textContent;
      moreBtn.textContent = 'Загрузка…';
      fetch(SUP_URL+'/api/supplier_search.php?q='+encodeURIComponent(q)+'&offset='+offset+'&limit='+limit, {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
          moreBtn.removeAttribute('data-busy');
          moreBtn.textContent = orig;
          if(!d || !d.html){ moreBtn.parentNode.removeChild(moreBtn); return; }
          var grid = document.getElementById('supGrid');
          var tmp = document.createElement('div');
          tmp.innerHTML = d.html;
          var added = [];
          while (tmp.firstElementChild){ var el = tmp.firstElementChild; grid.appendChild(el); added.push(el); }
          // подгрузить цены только для новых карточек
          added.forEach(function(el){ supLoadPrices(el); });
          if (d.has_more){ moreBtn.setAttribute('data-offset', String(d.next_offset)); }
          else { moreBtn.parentNode.removeChild(moreBtn); }
        })
        .catch(function(){ moreBtn.removeAttribute('data-busy'); moreBtn.textContent = orig; });
    });
  }

  /* ── Модалка выбора доставки ──────────────────────────────────────────────── */
  window.supOpenDelivery = function(btn){
    try { SUP_OPTS = JSON.parse(btn.getAttribute('data-offers')||'[]'); } catch(e){ SUP_OPTS=[]; }
    if(!SUP_OPTS.length) return;
    SUP_META = { oem: btn.getAttribute('data-oem')||'', brand: btn.getAttribute('data-brand')||'', name: btn.getAttribute('data-name')||'' };
    SUP_SEL = 0;
    var m = document.getElementById('supDlvModal');
    m.querySelector('.sup-dlv-name').textContent = SUP_META.name || 'Деталь';
    m.querySelector('.sup-dlv-sub').textContent  = (SUP_META.brand ? SUP_META.brand+' · ' : '') + SUP_META.oem;
    var list = m.querySelector('.sup-dlv-list'), h='';
    SUP_OPTS.forEach(function(o,i){
      h += '<label class="sup-dlv-opt'+(i===0?' sel':'')+'" data-i="'+i+'">'
         + '<span class="sup-dlv-radio"><input type="radio" name="supDlv" value="'+i+'"'+(i===0?' checked':'')+'></span>'
         + '<span class="sup-dlv-body"><span class="sup-dlv-price">'+o.price+'</span>'
         + '<span class="sup-dlv-meta">'+esc(offerFull(o))+'</span></span></label>';
    });
    list.innerHTML = h;
    list.onchange = function(e){ if(!e.target||e.target.type!=='radio')return; SUP_SEL=parseInt(e.target.value,10);
      Array.prototype.forEach.call(list.querySelectorAll('.sup-dlv-opt'),function(l,j){ l.classList.toggle('sel', j===SUP_SEL); }); };
    m.querySelector('.sup-dlv-qty').value = 1;
    m.querySelector('.sup-dlv-comment').value = '';
    m.querySelector('.sup-dlv-form').style.display = '';
    m.querySelector('.sup-dlv-msg').textContent = '';
    m.classList.add('open'); document.body.style.overflow='hidden';
  };
  window.supCloseDelivery = function(){ var m=document.getElementById('supDlvModal'); m.classList.remove('open'); document.body.style.overflow=''; };
  window.supSubmitDelivery = function(){
    var o = SUP_OPTS[SUP_SEL]; if(!o||!SUP_META) return;
    var m = document.getElementById('supDlvModal');
    var qty = Math.max(1,Math.min(99,parseInt(m.querySelector('.sup-dlv-qty').value||1,10)));
    var comment = m.querySelector('.sup-dlv-comment').value||'';
    var btn = m.querySelector('.sup-dlv-submit'), orig=btn.innerHTML;
    var csrfEl = document.querySelector('meta[name="csrf"]'), csrf = csrfEl ? csrfEl.getAttribute('content') : '';
    btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Отправка…';
    fetch(SUP_URL+'/api/vin_order_request.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ _csrf:csrf, oem:SUP_META.oem, brand:SUP_META.brand, name:SUP_META.name, qty:qty,
        price:o.price, price_raw:o.price_raw, delivery_total:o.delivery_total, delivery_ae:o.delivery_ae,
        delivery_days:o.delivery_days, in_stock:o.in_stock?1:0, comment:comment })})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.redirect){ window.location.href=d.redirect; return; }
      btn.disabled=false; btn.innerHTML=orig;
      var msg=m.querySelector('.sup-dlv-msg');
      if(d.success){ m.querySelector('.sup-dlv-form').style.display='none';
        msg.innerHTML='<div class="sup-dlv-ok"><i class="fa fa-check-circle"></i> '+(d.message||'Заявка отправлена.')+(d.messages_url?' <a href="'+d.messages_url+'">Открыть переписку</a>':'')+'</div>'; }
      else { msg.innerHTML='<div class="sup-dlv-err">'+(d.message||'Не удалось отправить заявку.')+'</div>'; }
    })
    .catch(function(){ btn.disabled=false; btn.innerHTML=orig; m.querySelector('.sup-dlv-msg').innerHTML='<div class="sup-dlv-err">Ошибка сети.</div>'; });
  };
  document.addEventListener('click',function(e){ var b=e.target.closest?e.target.closest('.sup-buy-btn'):null; if(b && !b.hasAttribute('data-lazy') && !b.disabled){ e.preventDefault(); supOpenDelivery(b); } });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ var m=document.getElementById('supDlvModal'); if(m&&m.classList.contains('open')) supCloseDelivery(); } });
})();
</script>
