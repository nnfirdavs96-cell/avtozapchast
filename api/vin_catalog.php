<?php
/**
 * AJAX endpoint каталога по VIN (PartsAPI getPartsbyVIN).
 * GET ?vin=XXXXXXXXXXXXXXXXX  →  { success, count, groups_scanned, from_cache, items:[…] }
 *
 * Перебор групп может занять несколько секунд при первом запросе VIN;
 * результат кэшируется на сервере, поэтому повторные запросы мгновенны.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';
require_once dirname(__DIR__) . '/includes/catalog_api.php';

header('Content-Type: application/json; charset=utf-8');

if (!CatalogApi::enabled()) {
    echo json_encode(['success' => false, 'error' => 'disabled', 'items' => []]);
    exit;
}

$vin = strtoupper(trim($_GET['vin'] ?? ''));
if (!VinService::validate($vin)) {
    echo json_encode(['success' => false, 'error' => 'bad_vin', 'items' => []]);
    exit;
}

// Долгий перебор групп — снимем лимит времени на сам запрос.
@set_time_limit(120);

// cat>0 → загрузка ОДНОГО узла (1 запрос, бережёт лимит ключа); иначе полный перебор.
$cat   = (int)($_GET['cat'] ?? 0);
$data  = $cat > 0 ? CatalogApi::searchByVinCat($vin, $cat) : CatalogApi::searchByVin($vin);
$items = [];
foreach ($data['items'] as $it) {
    $items[] = [
        'name'        => $it['name'],
        'group'       => $it['group'],
        'brand'       => $it['brand'],
        'part_number' => $it['part_number'],
        'in_catalog'  => (bool)$it['in_catalog'],
        'part_id'     => $it['part_id'],
        'price'       => $it['price'] !== null ? formatPrice((float)$it['price']) : null,
        'price_raw'   => $it['price'],
        'stock'       => $it['stock'],
        'url'         => $it['url'],
    ];
}

echo json_encode([
    'success'        => true,
    'count'          => $data['count'],
    'cat'            => $cat,
    'groups_scanned' => $data['groups_scanned'] ?? null,
    'errors'         => $data['errors'] ?? 0,
    'rate_limited'   => !empty($data['rate_limited']),
    'type'           => $data['type'] ?? '',
    'from_cache'     => $data['from_cache'],
    'items'          => $items,
], JSON_UNESCAPED_UNICODE);
