<?php
/**
 * Цена по OEM-номеру — для ленивой подгрузки цен в каталоге по VIN.
 * GET ?oem=АРТИКУЛ&brand=БРЕНД
 *   → {
 *        success, found,
 *        price, price_raw, stock, source, delivery, part_id, url,  // лучший вариант (совместимость)
 *        offer_mode,           // 'A' (только итог срока) | 'B' (с разбивкой)
 *        options: [            // варианты с ВЫБОРОМ срока/цены (для покупателя)
 *          { price, price_raw, stock, in_stock,
 *            delivery_ae, delivery_days, delivery_total, source }
 *        ]
 *      }
 *
 * Свой склад → (если включено и настроено) AutoEuro. У AutoEuro на один артикул
 * много предложений (разные склады) — отдаём список вариантов, чтобы покупатель
 * выбрал: быстрее-дороже или дешевле-дольше. Цена каждого варианта уже в сомони
 * с курсом+наценкой+надбавкой Москва→Худжанд, срок — до Худжанда. Результат
 * AutoEuro кэшируется на сервере, поэтому повторные запросы мгновенны.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

$oem   = trim($_GET['oem']   ?? $_GET['art'] ?? '');
$brand = trim($_GET['brand'] ?? '');
if ($oem === '') {
    echo json_encode(['success' => false, 'error' => 'no_oem', 'found' => false]);
    exit;
}

@set_time_limit(30);

$options = Catalog::price()->offersByOem($oem, $brand);
if (!$options) {
    echo json_encode(['success' => true, 'found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

// Режим показа срока: A — только итог до Худжанда; B — с разбивкой (Москва + наши дни).
$mode = getSetting('autoeuro_offer_mode', 'A') === 'B' ? 'B' : 'A';

$out = [];
foreach ($options as $o) {
    $out[] = [
        'price'          => formatPrice((float)$o['price']),
        'price_raw'      => $o['price'],
        'stock'          => $o['stock'],
        'in_stock'       => !empty($o['in_stock']),
        'delivery_ae'    => $o['delivery_ae'] ?? null,      // прибытие в Москву (YYYY-MM-DD)
        'delivery_days'  => $o['delivery_days'] ?? 0,       // наши дни Москва→Худжанд
        'delivery_total' => $o['delivery_total'] ?? null,   // прибытие в Худжанд (YYYY-MM-DD)
        'source'         => $o['source'] ?? 'autoeuro',
    ];
}

$best = $options[0];
echo json_encode([
    'success'    => true,
    'found'      => true,
    // Лучший вариант плоскими полями — для обратной совместимости фронта.
    'price'      => formatPrice((float)$best['price']),
    'price_raw'  => $best['price'],
    'stock'      => $best['stock'],
    'source'     => $best['source'] ?? 'autoeuro',
    'delivery'   => $best['delivery_total'] ?? $best['delivery_ae'] ?? null,
    'part_id'    => $best['part_id'] ?? null,
    'url'        => $best['url'] ?? null,
    // Полный список вариантов + режим показа срока.
    'offer_mode' => $mode,
    'options'    => $out,
], JSON_UNESCAPED_UNICODE);
