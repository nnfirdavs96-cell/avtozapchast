<?php
/**
 * Карточки предложений поставщика AutoEuro для страницы поиска.
 *
 * Ожидает в области видимости:
 *   $aeCards — массив карточек из search/index.php, каждая:
 *     ['brand','code','name','image','lazy'=>bool,'best'=>opt|null,'options'=>[opt,...]]
 *     opt = ['price'(строка),'price_raw','stock','in_stock',
 *            'delivery_ae','delivery_days','delivery_total','source']
 *   $aeMode  — 'A' (итог срока) | 'B' (разбивка).
 *
 * Два вида карточек:
 *   • lazy=false (поиск по артикулу) — цена уже посчитана на сервере (data-offers).
 *   • lazy=true  (поиск по названию/словарю) — цена подгружается на клиенте через
 *     api/vin_price.php (у себя цену не храним, только названия).
 *
 * «Купить» → модалка выбора доставки → POST api/vin_order_request.php (заявка
 * менеджеру). CSS — .sup-* в assets/css/custom.css. Нужен <meta name="csrf">.
 */
if (empty($aeCards)) return;
$aeMode = ($aeMode ?? 'A') === 'B' ? 'B' : 'A';
$fmtD = static fn($d) => preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$d, $m) ? "$m[3].$m[2].$m[1]" : '';
?>
<div class="sup-section">
  <div class="sup-head">
    <h3>Найдено у поставщика <span>AutoEuro</span></h3>
    <p>Детали, которых нет на нашем складе — привозим под заказ. Нажмите «Купить», и менеджер оформит заявку и уточнит доставку до Худжанда.</p>
  </div>

  <div class="row sup-grid">
    <?php foreach ($aeCards as $c):
      $lazy     = !empty($c['lazy']);
      $best     = $c['best'] ?? null;
      $optsJson = htmlspecialchars(json_encode($c['options'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
      $title    = $c['name'] !== '' ? $c['name'] : $c['code'];
      $eta      = $best ? $fmtD($best['delivery_total'] ?? '') : '';
    ?>
    <div class="col-lg-3 col-md-4 col-6 mb-4">
      <article class="single_product sup-card<?php echo $lazy ? ' sup-lazy' : ''; ?>">
        <figure>
          <div class="product_thumb sup-thumb">
            <img src="<?php echo sanitize($c['image']); ?>" alt="<?php echo sanitize($title); ?>" loading="lazy">
            <?php if (!$lazy): ?>
            <span class="sup-badge <?php echo $best['in_stock'] ? 'in' : 'pre'; ?>">
              <?php echo $best['in_stock'] ? 'В наличии' : 'Под заказ'; ?>
            </span>
            <?php endif; ?>
          </div>
          <div class="product_content grid_content">
            <div class="product_content_inner">
              <p class="manufacture_product"><?php echo sanitize($c['brand']); ?></p>
              <h4 class="product_name"><?php echo sanitize(truncate($title, 55)); ?></h4>
              <p class="sup-art"><?php echo sanitize($c['code']); ?></p>
              <?php if ($lazy): ?>
              <div class="sup-price sup-price-load">цена <span class="sup-dots">…</span></div>
              <?php else: ?>
              <div class="sup-price">от <strong><?php echo $best['price']; ?></strong></div>
              <?php if ($eta !== ''): ?>
              <p class="sup-eta"><i class="fa fa-truck"></i> в Худжанде ≈ <?php echo $eta; ?></p>
              <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="action_links">
              <button type="button" class="sup-buy-btn"<?php echo $lazy ? ' data-lazy="1" disabled' : ''; ?>
                      data-oem="<?php echo sanitize($c['code']); ?>"
                      data-brand="<?php echo sanitize($c['brand']); ?>"
                      data-name="<?php echo sanitize($c['name']); ?>"
                      data-offers="<?php echo $optsJson; ?>">
                <i class="fa fa-shopping-cart"></i> <?php echo $lazy ? 'Загрузка…' : 'Купить'; ?>
              </button>
            </div>
          </div>
        </figure>
      </article>
    </div>
    <?php endforeach; ?>
  </div>
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
  function offerShort(o){ return (o.in_stock ? 'в наличии' : 'под заказ') + (o.delivery_total ? ' · ≈ '+fmtDate(o.delivery_total) : ''); }

  /* ── Ленивая подгрузка цены для карточек из словаря (поиск по названию) ──── */
  function supLoadPrices(){
    var btns = document.querySelectorAll('.sup-buy-btn[data-lazy="1"]');
    Array.prototype.forEach.call(btns, function(btn){
      var card = btn.closest('.sup-card');
      var oem = btn.getAttribute('data-oem')||'', brand = btn.getAttribute('data-brand')||'';
      if(!oem){ if(card) card.remove(); return; }
      fetch(SUP_URL+'/api/vin_price.php?oem='+encodeURIComponent(oem)+'&brand='+encodeURIComponent(brand), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
          if(!d || !d.found || !d.options || !d.options.length){ if(card) card.remove(); return; }  // нет у поставщика → убрать
          var opts = d.options, best = opts[0];
          btn.setAttribute('data-offers', JSON.stringify(opts));
          btn.removeAttribute('data-lazy');
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-shopping-cart"></i> Купить';
          if(card){
            var pr = card.querySelector('.sup-price');
            if(pr){ pr.classList.remove('sup-price-load'); pr.innerHTML = 'от <strong>'+esc(best.price)+'</strong>'; }
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
  else document.addEventListener('DOMContentLoaded', supLoadPrices);

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
         + '<span class="sup-dlv-body"><span class="sup-dlv-price">'+esc(o.price)+'</span>'
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
