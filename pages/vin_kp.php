<?php
/**
 * КП (коммерческое предложение) по узлу — печатная страница без зависимостей на
 * сервере: пользователь открывает её и сохраняет в PDF через диалог печати браузера
 * (Ctrl+P → «Сохранить как PDF»). Цены и наличие — живые со склада на момент открытия,
 * не архивные из библиотеки каталога.
 *
 * GET:
 *   vin=XXXXXXXXXXXXXXXXX&cat=N               — режим VIN
 *   carId=&catalogId=&criteria=&brand=&cat=N   — режим «по параметрам»
 *   node=Название узла (для заголовка, необязательно)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

if (getSetting('catalog_kp_enabled', '1') !== '1') {
    http_response_code(404);
    echo '<!DOCTYPE html><meta charset="UTF-8"><p style="font-family:sans-serif;padding:40px;text-align:center;color:#888;">Функция временно отключена.</p>';
    exit;
}

$cat = trim((string)($_GET['cat'] ?? ''));
$nodeTitle = trim((string)($_GET['node'] ?? ''));

$provider = Catalog::provider();
$carAttrs = [];
$vin = '';
$d = ['img' => '', 'caption' => '', 'parts' => [], 'enabled' => false, 'rate_limited' => false];

if ($cat !== '' && $provider->enabled()) {
    $carId = trim($_GET['carId'] ?? '');
    $catId = trim($_GET['catalogId'] ?? '');
    if ($carId !== '' && $catId !== '' && method_exists($provider, 'schemeByCar')) {
        $criteria = trim($_GET['criteria'] ?? '');
        $brand    = trim($_GET['brand'] ?? '');
        $d = $provider->schemeByCar($carId, $catId, $criteria, $cat, $brand);
        // Без modelId полные атрибуты (модель/год) здесь не дотянуть — используем то, что уже знаем.
        if ($brand !== '') $carAttrs['make'] = $brand;
    } else {
        $vin = strtoupper(trim($_GET['vin'] ?? ''));
        if (VinService::validate($vin)) {
            $d = $provider->schemeByVinCat($vin, $cat);
            if (method_exists($provider, 'vinCarInfo')) $carAttrs = $provider->vinCarInfo($vin);
        }
    }
}

$parts = array_values(array_filter($d['parts'] ?? [], fn($p) => trim((string)($p['part_number'] ?? '')) !== ''));
$total = 0; $hasPrices = false;
foreach ($parts as $p) {
    if ($p['price'] !== null && $p['price'] !== '') { $hasPrices = true; $total += (float)$p['price']; }
}

$siteName  = getSetting('site_name', t('site_name'));
$sitePhone = formatPhone(getSetting('site_phone', '+992 92 612-22-22'));
$today     = date('d.m.Y');
$carLabel  = trim(($carAttrs['make'] ?? '') . ' ' . ($carAttrs['model'] ?? ''));
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>КП — <?= sanitize($nodeTitle ?: 'запчасти') ?><?= $carLabel ? ' · ' . sanitize($carLabel) : '' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  *{box-sizing:border-box;}
  body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1a1a1a;margin:0;background:#f4f4f5;}
  .sheet{max-width:800px;margin:0 auto;background:#fff;padding:36px 40px;}
  .noprint{display:flex;justify-content:center;gap:10px;padding:16px;position:sticky;top:0;background:#f4f4f5;z-index:5;}
  .btn{background:#C70909;color:#fff;border:0;border-radius:8px;padding:10px 20px;font-size:0.9rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
  .btn.secondary{background:#e7e9ee;color:#1a1a1a;}
  .hd{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #1a1a1a;padding-bottom:16px;margin-bottom:20px;}
  .hd h1{font-size:1.3rem;margin:0 0 4px;}
  .hd .meta{font-size:0.82rem;color:#666;}
  .hd .company{text-align:right;font-size:0.82rem;color:#444;}
  .hd .company b{font-size:1rem;color:#1a1a1a;display:block;margin-bottom:2px;}
  .carline{background:#f8f9fa;border-radius:10px;padding:12px 16px;margin-bottom:22px;font-size:0.9rem;display:flex;gap:24px;flex-wrap:wrap;}
  .carline span{color:#888;margin-right:6px;}
  table{width:100%;border-collapse:collapse;font-size:0.86rem;}
  th{text-align:left;background:#1a1a1a;color:#fff;padding:9px 10px;font-size:0.72rem;text-transform:uppercase;letter-spacing:.03em;}
  td{padding:9px 10px;border-bottom:1px solid #ececec;vertical-align:top;}
  tr:nth-child(even) td{background:#fafafa;}
  .num{font-family:monospace;}
  .price{text-align:right;white-space:nowrap;font-weight:700;}
  .stock-ok{color:#1b7a3d;font-size:0.76rem;}
  .stock-no{color:#999;font-size:0.76rem;}
  tfoot td{border-top:2px solid #1a1a1a;border-bottom:0;font-weight:800;padding-top:12px;}
  .foot-note{margin-top:26px;font-size:0.76rem;color:#999;line-height:1.5;}
  .empty{padding:40px 0;text-align:center;color:#999;}
  @media print{
    body{background:#fff;}
    .noprint{display:none;}
    .sheet{padding:0;max-width:none;}
  }
</style>
</head>
<body>

<div class="noprint">
  <button type="button" class="btn" onclick="window.print()"><i>🖨</i> Печать / Сохранить в PDF</button>
  <a class="btn secondary" href="javascript:window.close()">Закрыть</a>
</div>

<div class="sheet">
  <div class="hd">
    <div>
      <h1>Коммерческое предложение</h1>
      <div class="meta">
        <?= sanitize($nodeTitle ?: 'Запчасти') ?><br>
        <?= sanitize($today) ?><?= $vin ? ' · VIN ' . sanitize($vin) : '' ?>
      </div>
    </div>
    <div class="company">
      <b><?= sanitize($siteName) ?></b>
      <?= sanitize($sitePhone) ?>
    </div>
  </div>

  <?php if ($carLabel || $vin): ?>
  <div class="carline">
    <?php if ($carLabel): ?><div><span>Автомобиль:</span><strong><?= sanitize($carLabel) ?></strong></div><?php endif; ?>
    <?php if (!empty($carAttrs['year'])): ?><div><span>Год:</span><strong><?= sanitize((string)$carAttrs['year']) ?></strong></div><?php endif; ?>
    <?php if ($vin): ?><div><span>VIN:</span><strong style="font-family:monospace;"><?= sanitize($vin) ?></strong></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($parts)): ?>
    <p class="empty"><?= $d['rate_limited'] ? 'Каталог временно недоступен (превышен лимит запросов).' : 'Список деталей пуст или узел не найден.' ?></p>
  <?php else: ?>
  <table>
    <thead><tr>
      <th style="width:36px;">№</th>
      <th>Наименование</th>
      <th>Бренд</th>
      <th>OEM-номер</th>
      <th style="text-align:right;">Цена</th>
    </tr></thead>
    <tbody>
      <?php foreach ($parts as $i => $p): ?>
      <tr>
        <td><?= (int)$i + 1 ?></td>
        <td>
          <?= sanitize($p['name'] ?: $p['part_number']) ?>
          <?php if (!empty($p['stock'])): ?><div class="stock-ok">в наличии</div>
          <?php else: ?><div class="stock-no">под заказ</div><?php endif; ?>
        </td>
        <td><?= sanitize($p['brand'] ?: '—') ?></td>
        <td class="num"><?= sanitize($p['part_number']) ?></td>
        <td class="price"><?= $p['price'] !== null && $p['price'] !== '' ? sanitize(formatPrice((float)$p['price'])) : '<span style="color:#999;font-weight:400;">уточняется</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php if ($hasPrices): ?>
    <tfoot><tr>
      <td colspan="4">Итого (по позициям с известной ценой)</td>
      <td class="price"><?= sanitize(formatPrice($total)) ?></td>
    </tr></tfoot>
    <?php endif; ?>
  </table>
  <p class="foot-note">
    Цены и наличие актуальны на момент формирования документа (<?= sanitize($today) ?>) и могут
    измениться. Позиции «под заказ» — цена и сроки уточняются менеджером.
  </p>
  <?php endif; ?>
</div>

</body>
</html>
