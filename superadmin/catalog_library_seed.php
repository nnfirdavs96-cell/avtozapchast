<?php
/**
 * Разовое/периодическое наращивание библиотеки каталога Parts-Catalogs «вслепую»:
 * обходит выбранные марки → модели → авто (по параметрам, без VIN) и сохраняет
 * узлы + схемы в постоянную библиотеку (`catalog_library_*`), чтобы каталог был
 * заполнён популярными на рынке авто ещё до того, как их спросит реальный покупатель.
 *
 * В отличие от `catalog_library_cron.php` (который лишь ДОБИРАЕТ схемы уже
 * известных авто), этот скрипт САМ ОТКРЫВАЕТ новые марки/модели/авто через
 * pcBrands()/pcModels()/pcCars() — каждое новое авто это отдельный платный запрос
 * к Parts-Catalogs (тариф — за авто/сутки). Запускать осознанно, не по расписанию.
 *
 * Марки обходятся ПО КРУГУ (round-robin): за один «круг» берётся по одной
 * следующей модели от каждой марки — так разнообразие марок растёт сразу,
 * а не только после того, как первая марка будет выжата до дна.
 *
 * Запуск:
 *   php superadmin/catalog_library_seed.php [budget_cars=100] [schemes_per_car=6] [pause_ms=250]
 *
 *   budget_cars     — сколько НОВЫХ авто максимум сохранить за один запуск (по умолчанию 100)
 *   schemes_per_car — сколько схем узлов тянуть на каждое авто (по умолчанию 6):
 *                       0  = не собирать схемы (только узлы)
 *                       N>0 = собрать N первых схем
 *                       -1  = собрать ВСЕ схемы узлов авто (у некоторых авто их сотня —
 *                             сильно дольше и дороже, см. предупреждение ниже)
 *   pause_ms        — пауза между запросами к API, мс (по умолчанию 250)
 *
 * ⚠️ У авто может быть 100+ узлов — schemes_per_car=-1 означает 100+ запросов схем
 * НА КАЖДОЕ авто, это в разы дольше и дороже, чем сбор 6 схем. Оценивайте бюджет.
 *
 * Список марок и лимиты моделей/авто на модель — ниже, в $BRANDS / $MODELS_PER_BRAND /
 * $CARS_PER_MODEL. Правьте под свой рынок перед запуском.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/catalog.php';

function slog(string $m): void { echo '[' . date('c') . '] ' . $m . "\n"; }

// ── Марки, актуальные для рынка Таджикистана — правьте список под себя ──
$BRANDS = [
    'Toyota', 'Hyundai', 'Kia', 'Chevrolet', 'Daewoo', 'Lada', 'VAZ',
    'Nissan', 'Honda', 'Mercedes-Benz', 'BMW', 'Opel', 'Mitsubishi',
    'Volkswagen', 'Ford', 'Renault', 'Audi', 'Lexus',
];
$MODELS_PER_BRAND = 15;  // сколько моделей марки обходить (0 = все)
$CARS_PER_MODEL    = 5;   // сколько конкретных авто (модификаций) на модель сохранять

// ── Провайдер должен быть Parts-Catalogs и включён ──
$prov = Catalog::provider();
if (!($prov instanceof PartsCatalogsAdapter) || !$prov->enabled()) {
    slog('Провайдер Parts-Catalogs выключен или не выбран (Суперадмин → VIN → провайдер) — выход.');
    exit(1);
}

$budgetCars    = max(1, (int)($argv[1] ?? 100));
$schemesPerCar = (int)($argv[2] ?? 6); // может быть -1 («все»), поэтому без max(0, ...)
$pauseMs       = max(0, (int)($argv[3] ?? 250));

$brandsAll = $prov->pcBrands();
if (!$brandsAll) { slog('pcBrands() вернул пусто — проверьте ключ/подключение.'); exit(1); }

// Марка ищется по подстроке имени (без учёта регистра) среди списка каталога.
$wanted = [];
foreach ($brandsAll as $b) {
    foreach ($BRANDS as $want) {
        if (stripos($b['name'], $want) !== false || stripos($want, $b['name']) !== false) {
            $wanted[] = $b;
            break;
        }
    }
}
if (!$wanted) { slog('Ни одна марка из списка не найдена в каталоге — проверьте написание в $BRANDS.'); exit(1); }
slog('Найдено марок для обхода: ' . count($wanted) . ' из ' . count($BRANDS) . ' запрошенных.');

// ── Предзагрузка моделей КАЖДОЙ марки — нужно заранее, чтобы обходить по кругу ──
$modelsByBrand = [];
$maxModels = 0;
foreach ($wanted as $brand) {
    usleep($pauseMs * 1000);
    $models = $prov->pcModels($brand['id']);
    if (!$models) { slog("{$brand['name']}: моделей не найдено, пропуск."); continue; }
    if ($MODELS_PER_BRAND > 0) $models = array_slice($models, 0, $MODELS_PER_BRAND);
    $modelsByBrand[] = ['brand' => $brand, 'models' => $models];
    $maxModels = max($maxModels, count($models));
}
if (!$modelsByBrand) { slog('Ни у одной марки не нашлось моделей — выход.'); exit(1); }
slog(sprintf('Марок с моделями: %d, максимум моделей у марки: %d. Обход по кругу.', count($modelsByBrand), $maxModels));

$savedCars   = 0;
$emptyStreak = 0;
$stop        = false;

// ── Круг за кругом: на круге N берём N-ю модель у каждой марки по очереди ──
for ($round = 0; $round < $maxModels && !$stop; $round++) {
    foreach ($modelsByBrand as $entry) {
        if ($savedCars >= $budgetCars) { $stop = true; break; }

        $brand  = $entry['brand'];
        $models = $entry['models'];
        if (!isset($models[$round])) continue; // у этой марки моделей меньше, чем номер круга

        $model = $models[$round];

        usleep($pauseMs * 1000);
        $cars = $prov->pcCars($brand['id'], $model['id']);
        if (!$cars) {
            $emptyStreak++;
            if ($emptyStreak >= 8) {
                slog('8 пустых ответов подряд — похоже на лимит/сбой API, останавливаемся.');
                $stop = true;
                break;
            }
            continue;
        }
        $emptyStreak = 0;
        if ($CARS_PER_MODEL > 0) $cars = array_slice($cars, 0, $CARS_PER_MODEL);

        foreach ($cars as $car) {
            if ($savedCars >= $budgetCars) { $stop = true; break 2; }

            usleep($pauseMs * 1000);
            $nodes = $prov->oemNodesForCar($car['carId'], $car['catalogId'], $car['criteria'], $car['brand'] ?: $brand['name']);
            if (!$nodes) {
                $emptyStreak++;
                if ($emptyStreak >= 8) {
                    slog('8 пустых ответов подряд — похоже на лимит/сбой API, останавливаемся.');
                    $stop = true;
                    break 3;
                }
                continue;
            }
            $emptyStreak = 0;
            $savedCars++;
            slog(sprintf('+ %s %s [%s/%s]: %d узлов (сохранено авто: %d/%d)',
                $brand['name'], $model['name'], $car['catalogId'], $car['carId'], count($nodes), $savedCars, $budgetCars));

            // Схемы: 0 = не собирать, N>0 = первые N, -1 (и меньше) = все узлы авто.
            $groupIds = array_values(array_filter(array_column($nodes, 'cat'), fn($g) => $g !== ''));
            if ($schemesPerCar === 0) {
                $groupIds = [];
            } elseif ($schemesPerCar > 0) {
                $groupIds = array_slice($groupIds, 0, $schemesPerCar);
            }
            // schemesPerCar < 0 → $groupIds не обрезаем, тянем все узлы этого авто.

            if ($groupIds) {
                $res = $prov->harvestSchemes($car['catalogId'], $car['carId'], $car['criteria'], $car['brand'] ?: $brand['name'], $groupIds, count($groupIds), $pauseMs);
                slog("  схем собрано: {$res['fetched']} из " . count($groupIds) . ($res['rate_limited'] ? ' — STOP: rate-limit' : ''));
                if ($res['rate_limited']) { $stop = true; break 3; }
            }
        }
    }
}

slog("Готово. Новых авто сохранено: {$savedCars} (лимит {$budgetCars}).");
