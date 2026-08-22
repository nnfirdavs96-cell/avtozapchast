<?php
/**
 * Карточки автомобиля из библиотеки каталога (админка).
 *
 * Данные берутся из `catalog_library_cars.attrs_json`, который заполняет
 * PartsCatalogsAdapter::parseCarAttrs(): make, model, model_line, series, year,
 * engine, body_type, region, steering, transmission.
 *
 * Две формы:
 *   carCardTile($row)  — плитка для списка (марка/модель/год + прогресс схем);
 *   carCardFull($row)  — крупная карточка для страницы авто (как на VIN-странице).
 *
 * Стили печатаются один раз через carCardCss().
 *
 * ВАЖНО: у части авто (заводились seed-скриптом «по параметрам») attrs_json пуст —
 * тогда показываем марку и честную пометку «нет данных модели», а не пустоту.
 */

if (!function_exists('carCardAttrs')) {
    /** Разбор attrs_json + запасные значения из самой строки авто. */
    function carCardAttrs(array $row): array
    {
        $a = json_decode((string)($row['attrs_json'] ?? '[]'), true);
        if (!is_array($a)) $a = [];
        $a['make'] = trim((string)($a['make'] ?? ($row['brand'] ?? '')));
        return $a;
    }

    /** Заголовок карточки: «Lexus RX 350» либо марка, либо «—». */
    function carCardTitle(array $attrs, array $row): string
    {
        $make  = trim((string)($attrs['make'] ?? ''));
        $model = trim((string)($attrs['model'] ?? $attrs['model_line'] ?? ''));
        $t = trim($make . ' ' . $model);
        return $t !== '' ? $t : (trim((string)($row['brand'] ?? '')) ?: '—');
    }

    /** Подпись: «2012 · РОДСТЕР · 3.5» — только непустые части. */
    function carCardSub(array $attrs): string
    {
        $parts = [];
        if (!empty($attrs['year']))      $parts[] = (int)$attrs['year'] . ' г.';
        if (!empty($attrs['body_type'])) $parts[] = mb_strimwidth((string)$attrs['body_type'], 0, 28, '…');
        if (!empty($attrs['engine']))    $parts[] = (string)$attrs['engine'];
        return implode(' · ', $parts);
    }
}

if (!function_exists('carCardCss')) {
    /** Стили карточек — печатаются один раз на страницу. */
    function carCardCss(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        ?>
<style>
.cc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px;}
.cc-tile{background:#fff;border:1px solid #e7e9ee;border-radius:14px;overflow:hidden;display:flex;flex-direction:column;
         box-shadow:0 2px 10px rgba(20,22,30,.05);transition:box-shadow .2s,border-color .2s,transform .2s;}
.cc-tile:hover{border-color:#f1c2c2;box-shadow:0 10px 26px rgba(20,22,30,.12);transform:translateY(-3px);}
/* Тёмная «шапка». Бейдж — в потоке (раньше был absolute и длинный заголовок на него
   налезал), заголовок прижат книзу и обрезан двумя строками, поэтому все шапки
   одинаковой высоты. Вотермарк — внизу справа, сильно приглушён, чтобы не мешал тексту. */
.cc-head{position:relative;background:radial-gradient(120% 120% at 20% 18%,#2a2f3a,#14161b 72%);color:#fff;
         padding:12px 14px 13px;min-height:122px;display:flex;flex-direction:column;overflow:hidden;}
.cc-wm{position:absolute;right:-4px;bottom:-6px;z-index:0;font-size:2rem;font-weight:900;letter-spacing:.02em;
       text-transform:uppercase;line-height:1;pointer-events:none;white-space:nowrap;color:rgba(255,255,255,.055);}
.cc-badge{position:relative;z-index:2;align-self:flex-start;background:#C70909;color:#fff;font-size:.56rem;font-weight:800;
          letter-spacing:.05em;padding:3px 9px;border-radius:20px;text-transform:uppercase;line-height:1.4;}
.cc-titlewrap{position:relative;z-index:2;margin-top:auto;padding-top:10px;}
.cc-ttl{font-size:.94rem;font-weight:800;line-height:1.25;text-shadow:0 2px 8px rgba(0,0,0,.45);
        display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;word-break:break-word;}
/* Заголовок — ссылка на карточку авто (кнопки «Открыть» больше нет) */
a.cc-ttl-link{color:#fff!important;text-decoration:none;transition:color .15s;}
a.cc-ttl-link:hover{color:#ff8a8a!important;text-decoration:underline;}
.cc-sub{font-size:.73rem;opacity:.72;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cc-nodata{display:inline-block;font-size:.66rem;color:#ffc9c9;background:rgba(255,255,255,.09);
           border-radius:20px;padding:2px 8px;margin-top:6px;}
/* Тело: прогресс + мета */
.cc-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:9px;flex:1;}
.cc-meta{font-size:.74rem;color:#8a8f99;display:flex;justify-content:space-between;gap:8px;}
.cc-bar{height:7px;background:#eef0f3;border-radius:6px;overflow:hidden;}
.cc-bar i{display:block;height:100%;background:#C70909;border-radius:6px;transition:width .3s;}
.cc-bar.done i{background:#2e9e44;}
.cc-stat{font-size:.78rem;color:#4a4a55;display:flex;align-items:center;gap:6px;}
.cc-stat b{color:#14161b;}
.cc-warn{font-size:.72rem;color:#b9772a;}
.cc-acts{display:flex;gap:6px;margin-top:auto;padding-top:4px;}
.cc-acts .az-btn{flex:1;text-align:center;}
/* Крупная карточка на странице авто */
.cc-full{display:grid;grid-template-columns:minmax(230px,.55fr) minmax(0,1.45fr);border-radius:16px;overflow:hidden;
         border:1px solid #e7e9ee;background:#fff;box-shadow:0 3px 14px rgba(20,22,30,.07);margin-bottom:16px;}
@media(max-width:820px){.cc-full{grid-template-columns:1fr;}}
.cc-full .cc-head{min-height:158px;padding:18px 20px;}
.cc-full .cc-wm{font-size:2.6rem;}
.cc-full .cc-ttl{font-size:1.3rem;-webkit-line-clamp:3;}   /* на крупной карточке места больше */
.cc-full .cc-sub{white-space:normal;}
.cc-rows{display:grid;grid-template-columns:1fr 1fr;align-content:center;}
@media(max-width:820px){.cc-rows{grid-template-columns:1fr;}}
.cc-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid #edeff2;min-width:0;}
.cc-row:nth-child(odd){border-right:1px solid #edeff2;}
@media(max-width:820px){.cc-row:nth-child(odd){border-right:0;}}
.cc-k{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7480;font-weight:700;white-space:nowrap;}
.cc-k i{margin-right:6px;color:#aab0b8;}
.cc-v{font-weight:800;color:#14161b;font-size:.9rem;text-align:right;word-break:break-word;}
</style>
        <?php
    }
}

if (!function_exists('carCardTile')) {
    /**
     * Плитка авто для списка.
     * $row — строка catalog_library_cars + nodes_count / schemes_count.
     */
    function carCardTile(array $row, int $nodesLimit = 300, string $csrf = ''): void
    {
        carCardCss();
        $attrs  = carCardAttrs($row);
        $title  = carCardTitle($attrs, $row);
        $sub    = carCardSub($attrs);
        $nodes  = (int)($row['nodes_count'] ?? 0);
        $have   = (int)($row['schemes_count'] ?? 0);
        $pct    = $nodes > 0 ? min(100, (int)round($have / $nodes * 100)) : 0;
        $done   = $nodes > 0 && $have >= $nodes;
        $wm     = mb_strtoupper(mb_substr(trim((string)($attrs['make'] ?? $row['brand'] ?? '')), 0, 9));
        $noData = trim((string)($attrs['model'] ?? '')) === '' && trim((string)($attrs['model_line'] ?? '')) === '';
        $qs     = 'catalog_id=' . urlencode((string)$row['catalog_id']) . '&car_id=' . urlencode((string)$row['car_id']);
        ?>
    <div class="cc-tile">
      <div class="cc-head">
        <span class="cc-wm"><?= sanitize($wm) ?></span>
        <span class="cc-badge">Parts-Catalogs</span>
        <div class="cc-titlewrap">
          <a class="cc-ttl cc-ttl-link" href="?action=view&<?= $qs ?>"
             title="<?= sanitize($title) ?> — открыть карточку"><?= sanitize($title) ?></a>
          <?php if ($sub !== ''): ?><div class="cc-sub"><?= sanitize($sub) ?></div><?php endif; ?>
          <?php if ($noData): ?><div class="cc-nodata">нет данных модели</div><?php endif; ?>
        </div>
      </div>
      <div class="cc-body">
        <div class="cc-stat">
          <i class="fa fa-picture-o" style="color:#C70909;"></i>
          <?php if ($nodes > 0): ?>
            Схемы: <b><?= $have ?></b> из <b><?= $nodes ?></b>
            <?php if ($done): ?><span style="color:#2e9e44;">· готово</span><?php endif; ?>
          <?php else: ?>
            <?php /* Дерева нет: показывать «1 из 0» бессмысленно — это выглядит как поломка.
                     Так бывает у авто, чьё дерево ещё не собрано или было потеряно. */ ?>
            Схемы: <b><?= $have ?></b> · <span class="cc-warn">дерево не собрано</span>
          <?php endif; ?>
        </div>
        <?php if ($nodes > 0): ?>
        <div class="cc-bar <?= $done ? 'done' : '' ?>"><i style="width:<?= $pct ?>%"></i></div>
        <?php endif; ?>
        <?php if ($nodes > 0 && $nodes >= $nodesLimit): ?>
        <div class="cc-warn">⚠️ дерево, вероятно, обрезано лимитом</div>
        <?php endif; ?>
        <div class="cc-meta">
          <?php if ($row['vin']): ?>
            <span title="Авто найдено поиском по VIN — номер сохранён"><?= sanitize((string)$row['vin']) ?></span>
          <?php else: ?>
            <span title="Авто найдено по марке/модели/году — VIN никто не вводил, поэтому его нет. На работу каталога это не влияет: узлы и схемы тянутся по carId.">по параметрам</span>
          <?php endif; ?>
          <span><?= sanitize(mb_substr((string)($row['updated_at'] ?? ''), 0, 10)) ?></span>
        </div>
        <div class="cc-acts">
          <!-- Пересчёт — обычная форма: перезагрузка страницы со статусом во флеш-сообщении. -->
          <form method="post" action="?action=recount_nodes" style="flex:1;margin:0;"
                onsubmit="return confirm('Заново обойти дерево узлов этого авто?\n\nСобранные схемы сохранятся. Занимает 1–3 минуты, тарифный VIN-лимит не тратит.');">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="catalog_id" value="<?= sanitize((string)$row['catalog_id']) ?>">
            <input type="hidden" name="car_id" value="<?= sanitize((string)$row['car_id']) ?>">
            <input type="hidden" name="return_to" value="list">
            <button type="submit" class="az-btn az-btn-secondary az-btn-sm" style="width:100%;"
                    title="Пересчитать дерево узлов с текущим лимитом">
              <i class="fa fa-refresh"></i> Пересчитать
            </button>
          </form>
          <!-- Сбор схем — порциями через AJAX, прогресс идёт в полосу этой же плитки. -->
          <button type="button" class="az-btn az-btn-primary az-btn-sm cc-collect" style="flex:1;"
                  data-catalog="<?= sanitize((string)$row['catalog_id']) ?>"
                  data-car="<?= sanitize((string)$row['car_id']) ?>"
                  <?= ($done || $nodes === 0) ? 'disabled' : '' ?>
                  title="<?= $nodes === 0 ? 'Сначала пересчитайте дерево' : 'Догрузить недостающие схемы' ?>">
            <i class="fa fa-cloud-download"></i> <?= $done ? 'Готово' : 'Собрать схемы' ?>
          </button>
        </div>
      </div>
    </div>
        <?php
    }
}

if (!function_exists('carCardFull')) {
    /** Крупная карточка авто (страница просмотра). */
    function carCardFull(array $row): void
    {
        carCardCss();
        $attrs = carCardAttrs($row);
        $title = carCardTitle($attrs, $row);
        $sub   = carCardSub($attrs);
        $wm    = mb_strtoupper(mb_substr(trim((string)($attrs['make'] ?? $row['brand'] ?? '')), 0, 10));

        // Порядок и подписи — как на VIN-странице.
        $fields = [
            'make'         => ['Марка',            'fa-car'],
            'model'        => ['Модель',           'fa-tag'],
            'model_line'   => ['Модельный ряд',    'fa-list'],
            'series'       => ['Поколение',        'fa-code-fork'],
            'year'         => ['Год выпуска',      'fa-calendar'],
            'body_type'    => ['Тип кузова',       'fa-cube'],
            'engine'       => ['Двигатель',        'fa-cog'],
            'transmission' => ['Коробка передач',  'fa-cogs'],
            'steering'     => ['Руль',             'fa-dot-circle-o'],
            'region'       => ['Регион',           'fa-map-o'],
        ];
        $rows = [];
        foreach ($fields as $k => [$label, $icon]) {
            $v = $attrs[$k] ?? '';
            if ($v === '' || $v === 0 || $v === null) continue;
            $rows[] = [$label, $icon, (string)$v];
        }
        ?>
    <div class="cc-full">
      <div class="cc-head">
        <span class="cc-wm"><?= sanitize($wm) ?></span>
        <span class="cc-badge">Parts-Catalogs</span>
        <div class="cc-titlewrap">
          <div class="cc-ttl"><?= sanitize($title) ?></div>
          <?php if ($sub !== ''): ?><div class="cc-sub"><?= sanitize($sub) ?></div><?php endif; ?>
          <?php if (!$rows): ?><div class="cc-nodata">нет данных модели (авто заведено по параметрам)</div><?php endif; ?>
        </div>
      </div>
      <div class="cc-rows">
        <?php if ($rows): foreach ($rows as [$label, $icon, $v]): ?>
        <div class="cc-row">
          <span class="cc-k"><i class="fa <?= $icon ?>"></i><?= sanitize($label) ?></span>
          <span class="cc-v"><?= sanitize($v) ?></span>
        </div>
        <?php endforeach; else: ?>
        <div class="cc-row" style="border-right:0;">
          <span class="cc-k"><i class="fa fa-info-circle"></i> Характеристики</span>
          <span class="cc-v" style="font-weight:600;color:#8a8f99;">не сохранены</span>
        </div>
        <?php endif; ?>
      </div>
    </div>
        <?php
    }
}

if (!function_exists('carCardListScript')) {
    /**
     * JS для сетки плиток: кнопка «Собрать схемы» тянет недостающие схемы порциями
     * через `?action=collect_batch` и обновляет прогресс-полосу СВОЕЙ плитки.
     * Печатать один раз после сетки. $csrf — токен для POST.
     *
     * Строго ПО ОДНОЙ машине за раз: параллельный сбор в нескольких плитках
     * означал бы кратную нагрузку на API Tradesoft.
     */
    function carCardListScript(string $csrf): void
    {
        ?>
<script>
(function(){
  var BUSY = false;
  document.addEventListener('click', function(e){
    var btn = e.target.closest ? e.target.closest('.cc-collect') : null;
    if (!btn || btn.disabled) return;
    e.preventDefault();
    if (BUSY) { alert('Сбор уже идёт для другой машины — дождитесь окончания.'); return; }
    BUSY = true;

    var tile = btn.closest('.cc-tile');
    var bar  = tile ? tile.querySelector('.cc-bar i') : null;
    var stat = tile ? tile.querySelector('.cc-stat') : null;
    var orig = btn.innerHTML;
    btn.disabled = true;

    function step(){
      var body = new FormData();
      body.append('csrf_token', <?= json_encode($csrf) ?>);
      body.append('catalog_id', btn.getAttribute('data-catalog'));
      body.append('car_id',     btn.getAttribute('data-car'));
      fetch('?action=collect_batch', { method:'POST', body: body, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.error){
            btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + (d.message || d.error);
            btn.disabled = false; BUSY = false; return;
          }
          if (typeof d.have === 'number' && d.total){
            var pct = Math.round(d.have / d.total * 100);
            if (bar)  bar.style.width = pct + '%';
            if (stat) stat.innerHTML = '<i class="fa fa-picture-o" style="color:#C70909;"></i> Схемы: <b>' +
                                       d.have + '</b> из <b>' + d.total + '</b>';
          }
          if (d.rate_limited){
            btn.innerHTML = '<i class="fa fa-clock-o"></i> Пауза (лимит API)';
            btn.disabled = false; BUSY = false; return;
          }
          if (d.done || (d.remaining || 0) <= 0){
            btn.innerHTML = '<i class="fa fa-check"></i> Готово';
            if (bar) bar.parentNode.classList.add('done');
            BUSY = false; return;
          }
          btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Осталось ' + d.remaining;
          step();
        })
        .catch(function(){ btn.innerHTML = orig; btn.disabled = false; BUSY = false; });
    }
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Собираю…';
    step();
  });
})();
</script>
        <?php
    }
}
