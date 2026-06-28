<?php
/**
 * AJAX endpoint аналогов-кроссов (PartsAPI getCrosses) — МОСТИК цепочки.
 * GET ?article=НОМЕР[&brand=БРЕНД]
 *   → { success, count, rate_limited, from_cache, items:[ {brand, part_number,
 *        is_original, in_catalog, part_id, price, price_raw, stock, url} … ] }
 *
 * По номеру детali из каталога получаем кроссы и сразу сверяем их со СВОИМ
 * складом: совпавший артикул → своя цена/наличие/«В корзину», иначе «под заказ».
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

$provider = Catalog::provider();
if (!$provider->enabled()) {
    echo json_encode(['success' => false, 'error' => 'disabled', 'items' => []]);
    exit;
}

$article = trim($_GET['article'] ?? $_GET['art'] ?? '');
$brand   = trim($_GET['brand'] ?? '');
if ($article === '') {
    echo json_encode(['success' => false, 'error' => 'no_article', 'items' => []]);
    exit;
}

@set_time_limit(60);

$data  = $provider->crossesWithWarehouse($article, $brand);
$items = [];
foreach ($data['items'] as $it) {
    $items[] = [
        'brand'       => $it['brand'],
        'part_number' => $it['part_number'],
        'is_original' => !empty($it['is_original']),
        'in_catalog'  => (bool)$it['in_catalog'],
        'part_id'     => $it['part_id'],
        'price'       => $it['price'] !== null ? formatPrice((float)$it['price']) : null,
        'price_raw'   => $it['price'],
        'stock'       => $it['stock'],
        'url'         => $it['url'],
    ];
}

echo json_encode([
    'success'      => true,
    'count'        => $data['count'],
    'rate_limited' => !empty($data['rate_limited']),
    'from_cache'   => !empty($data['from_cache']),
    'items'        => $items,
], JSON_UNESCAPED_UNICODE);
