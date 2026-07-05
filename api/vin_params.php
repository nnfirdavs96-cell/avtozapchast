<?php
/**
 * AJAX: каскад «По параметрам» (Марка → Модель → уточнения → авто) — Parts-Catalogs.
 * Один эндпоинт со `step`:
 *   ?step=brands                                        → { items:[{id,name}] }
 *   ?step=models&catalogId=..                           → { items:[{id,name,img}] }
 *   ?step=carparams&catalogId=..&modelId=..&parameter=  → { items:[{key,name,values:[{idx,value}]}] }
 *   ?step=cars&catalogId=..&modelId=..&parameter=       → { items:[{carId,catalogId,criteria,name,modelName,brand}] }
 *
 * `parameter` — накопленные idx уточнений через запятую (для сужения набора).
 * Работает только у провайдера, который умеет каскад (Parts-Catalogs). Иначе — пусто.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

$provider = Catalog::provider();
if (!$provider->enabled()) {
    echo json_encode(['success' => false, 'error' => 'disabled', 'items' => []]);
    exit;
}

$step  = $_GET['step'] ?? '';
$cid   = trim($_GET['catalogId'] ?? '');
$mid   = trim($_GET['modelId'] ?? '');
$par   = trim($_GET['parameter'] ?? '');
$has   = fn(string $m): bool => method_exists($provider, $m);

@set_time_limit(60);

$items = [];
switch ($step) {
    case 'brands':
        if ($has('pcBrands')) $items = $provider->pcBrands();
        break;
    case 'models':
        if ($cid !== '' && $has('pcModels')) $items = $provider->pcModels($cid);
        break;
    case 'carparams':
        if ($cid !== '' && $mid !== '' && $has('pcCarParams')) $items = $provider->pcCarParams($cid, $mid, $par);
        break;
    case 'cars':
        if ($cid !== '' && $mid !== '' && $has('pcCars')) $items = $provider->pcCars($cid, $mid, $par);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'bad_step', 'items' => []]);
        exit;
}

echo json_encode(['success' => true, 'step' => $step, 'count' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE);
