<?php
/**
 * Дозагрузка карточек поставщика AutoEuro по НАЗВАНИЮ (кнопка «Показать ещё»).
 *
 * GET: q (запрос), offset (сколько уже показано), limit (размер порции, по умолч. 8).
 * Отдаёт следующую порцию карточек словаря (HTML) + флаг has_more. Цена на
 * карточках подгружается на клиенте живьём (api/vin_price.php) — здесь цены нет.
 *   → { html, has_more, next_offset, count }
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/catalog/AutoEuroPriceProvider.php';
require_once dirname(__DIR__) . '/includes/parts/supplier_card_render.php';

header('Content-Type: application/json; charset=utf-8');

$q      = trim((string)($_GET['q'] ?? ''));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = min(24, max(1, (int)($_GET['limit'] ?? 8)));

if (getSetting('autoeuro_enabled') !== '1' || mb_strlen($q) < 2) {
    echo json_encode(['html' => '', 'has_more' => false, 'next_offset' => $offset, 'count' => 0]);
    exit;
}

$rows = AutoEuroPriceProvider::dictionarySearch($q, $limit, $offset);
$db   = getDB();

$html = '';
foreach ($rows as $d) {
    $code = (string)($d['oem'] ?? '');
    if ($code === '') continue;
    $html .= supplierCardHtml([
        'brand'   => (string)($d['brand'] ?? ''),
        'code'    => $code,
        'name'    => (string)($d['name'] ?? ''),
        'image'   => supplierHybridImage($db, $code),
        'lazy'    => true,
        'best'    => null,
        'options' => [],
    ]);
}

$count = count($rows);
echo json_encode([
    'html'        => $html,
    'has_more'    => $count === $limit,   // полная порция → возможно, есть ещё
    'next_offset' => $offset + $count,
    'count'       => $count,
], JSON_UNESCAPED_UNICODE);
