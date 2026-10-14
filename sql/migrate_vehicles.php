<?php
/**
 * Справочник автомобилей + связь товар↔авто (подбор запчастей по марке/модели).
 *
 * Создаёт таблицы vehicle_makes / vehicle_models / vehicle_mods / part_vehicles
 * (идемпотентно) и наполняет справочник популярными для Таджикистана марками и
 * моделями. Повторный запуск безопасен (марки/модели добавляются по уникальному
 * slug, существующие не трогаются).
 *
 * Запуск:  php sql/migrate_vehicles.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vehicles.php';

$db = getDB();
echo "Справочник автомобилей — миграция\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

ensureVehicleSchema();
echo "  [OK] таблицы vehicle_makes / vehicle_models / vehicle_mods / part_vehicles\n";

// ── Начальные данные: марка → [модели] ───────────────────────────────────────
$seed = [
    'Toyota'        => ['Camry','Corolla','RAV4','Land Cruiser','Land Cruiser Prado','Highlander','Hilux','Avensis','Yaris','Vitz','Premio','Allion','Mark II','Sequoia','Fortuner'],
    'Lexus'         => ['RX350','RX450h','LX570','GX460','ES350','ES300h','LS460','NX200','GX470','RX300'],
    'Hyundai'       => ['Accent','Elantra','Sonata','Tucson','Santa Fe','Creta','Getz','i30','Starex','Solaris','Grandeur'],
    'Kia'           => ['Rio','Cerato','Sportage','Sorento','Optima','Picanto','Carnival','Cee\'d','K5','Soul'],
    'Mercedes-Benz' => ['C-Class','E-Class','S-Class','GLE','GLC','ML','GL','Sprinter','Vito','A-Class','CLS'],
    'BMW'           => ['3 Series','5 Series','7 Series','X5','X6','X3','X1','1 Series'],
    'Chevrolet'     => ['Cobalt','Lacetti','Captiva','Cruze','Spark','Malibu','Nexia','Aveo','Tracker','Equinox'],
    'Opel'          => ['Astra','Vectra','Zafira','Corsa','Insignia','Antara'],
    'Daewoo'        => ['Nexia','Matiz','Gentra','Lanos','Damas','Labo','Nubira'],
    'Honda'         => ['Civic','Accord','CR-V','Fit','Odyssey','Pilot','Stepwgn'],
    'Nissan'        => ['Almera','X-Trail','Qashqai','Teana','Patrol','Sunny','Note','Juke','Pathfinder'],
    'Mitsubishi'    => ['Lancer','Outlander','Pajero','ASX','Montero','Pajero Sport','Galant'],
];

$mkIns  = $db->prepare("INSERT IGNORE INTO vehicle_makes (name, slug, sort_order) VALUES (?,?,?)");
$mkGet  = $db->prepare("SELECT id FROM vehicle_makes WHERE slug = ? LIMIT 1");
$mdIns  = $db->prepare("INSERT IGNORE INTO vehicle_models (make_id, name, slug, sort_order) VALUES (?,?,?,?)");

$madeMk = 0; $madeMd = 0; $mkSort = 0;
foreach ($seed as $makeName => $models) {
    $mkSort += 10;
    $mkSlug = vehSlug($makeName);
    $mkIns->execute([$makeName, $mkSlug, $mkSort]);
    if ($mkIns->rowCount() > 0) $madeMk++;
    $mkGet->execute([$mkSlug]);
    $makeId = (int)$mkGet->fetchColumn();
    if ($makeId <= 0) continue;

    $mdSort = 0;
    foreach ($models as $modelName) {
        $mdSort += 10;
        $mdIns->execute([$makeId, $modelName, vehSlug($modelName), $mdSort]);
        if ($mdIns->rowCount() > 0) $madeMd++;
    }
}

echo "  [OK] марок добавлено: $madeMk, моделей добавлено: $madeMd\n";

$totalMk = (int)$db->query("SELECT COUNT(*) FROM vehicle_makes")->fetchColumn();
$totalMd = (int)$db->query("SELECT COUNT(*) FROM vehicle_models")->fetchColumn();
echo str_repeat('-', 60) . "\n";
echo "Всего в справочнике: марок $totalMk, моделей $totalMd.\n";
echo "Модификации (годы/двигатель) добавляются в админке «Автомобили» или авто-скриптом.\n";
echo "Готово.\n";
