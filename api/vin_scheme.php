<?php
/**
 * AJAX: визуальная взрыв-схема одного узла (провайдеры со схемами — Parts-Catalogs).
 * GET ?vin=XXXXXXXXXXXXXXXXX&cat=N →
 *   { success, enabled, rate_limited, img, caption,
 *     hotspots:[{n,x,y,w,h}], parts:[{name,group,brand,part_number,pos,
 *                                     in_catalog,part_id,price,stock,url}] }
 *
 * Картинка (img) и координаты хотспотов приходят из PC parts2. Если у ответа нет
 * positions — hotspots пустой, фронт деградирует до «картинка + список деталей».
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

$provider = Catalog::provider();
if (!$provider->enabled() || !method_exists($provider, 'schemeByVinCat')) {
    echo json_encode(['success' => false, 'error' => 'no_scheme', 'enabled' => false]);
    exit;
}

$cat = trim((string)($_GET['cat'] ?? ''));   // ID узла Parts-Catalogs — нечисловая строка
if ($cat === '') {
    echo json_encode(['success' => false, 'error' => 'bad_params', 'enabled' => true]);
    exit;
}

@set_time_limit(60);

// Режим «По параметрам»: авто задано carId+catalogId (без VIN) → тот же parts2.
$carId = trim($_GET['carId'] ?? '');
$catId = trim($_GET['catalogId'] ?? '');
if ($carId !== '' && $catId !== '' && method_exists($provider, 'schemeByCar')) {
    $d = $provider->schemeByCar($carId, $catId, trim($_GET['criteria'] ?? ''), $cat, trim($_GET['brand'] ?? ''));
} else {
    $vin = strtoupper(trim($_GET['vin'] ?? ''));
    if (!VinService::validate($vin)) {
        echo json_encode(['success' => false, 'error' => 'bad_params', 'enabled' => true]);
        exit;
    }
    $d = $provider->schemeByVinCat($vin, $cat);
}

$parts = [];
foreach (($d['parts'] ?? []) as $it) {
    $parts[] = [
        'name'        => $it['name'],
        'group'       => $it['group'],
        'brand'       => $it['brand'],
        'part_number' => $it['part_number'],
        'pos'         => $it['pos'] ?? '',
        'in_catalog'  => (bool)$it['in_catalog'],
        'part_id'     => $it['part_id'],
        'price'       => $it['price'] !== null ? formatPrice((float)$it['price']) : null,
        'stock'       => $it['stock'],
        'url'         => $it['url'],
    ];
}

echo json_encode([
    'success'      => !empty($d['enabled']),
    'enabled'      => !empty($d['enabled']),
    'rate_limited' => !empty($d['rate_limited']),
    'img'          => $d['img'] ?? '',
    'caption'      => $d['caption'] ?? '',
    'hotspots'     => $d['hotspots'] ?? [],
    'parts'        => $parts,
], JSON_UNESCAPED_UNICODE);
