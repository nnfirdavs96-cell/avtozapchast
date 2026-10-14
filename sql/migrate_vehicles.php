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

// ── Модификации: реальные примеры для популярных моделей ─────────────────────
// [марка, модель, год_с, год_по, двигатель, объём, топливо, кузов/поколение].
// Вставляем, только если у модели ещё нет такой модификации (повтор безопасен).
$modSeed = [
    // Lexus
    ['Lexus','RX350',2006,2009,'3MZ-FE',3.3,'бензин','XU30'],
    ['Lexus','RX350',2009,2015,'2GR-FE',3.5,'бензин','AL10'],
    ['Lexus','RX350',2015,2022,'2GR-FKS',3.5,'бензин','AL20'],
    ['Lexus','RX450h',2009,2015,'2GR-FXE',3.5,'гибрид','AL10'],
    ['Lexus','RX450h',2015,2022,'2GR-FXS',3.5,'гибрид','AL20'],
    ['Lexus','LX570',2007,2021,'3UR-FE',5.7,'бензин','J200'],
    ['Lexus','GX460',2009,2023,'1UR-FE',4.6,'бензин','J150'],
    ['Lexus','ES350',2006,2012,'2GR-FE',3.5,'бензин','XV40'],
    ['Lexus','ES350',2012,2018,'2GR-FKS',3.5,'бензин','XV60'],
    // Toyota
    ['Toyota','Camry',2006,2011,'2AZ-FE',2.4,'бензин','XV40'],
    ['Toyota','Camry',2006,2011,'2GR-FE',3.5,'бензин','XV40'],
    ['Toyota','Camry',2011,2017,'2AR-FE',2.5,'бензин','XV50'],
    ['Toyota','Camry',2017,2023,'A25A-FKS',2.5,'бензин','XV70'],
    ['Toyota','Corolla',2007,2013,'1ZR-FE',1.6,'бензин','E150'],
    ['Toyota','Corolla',2013,2018,'2ZR-FE',1.8,'бензин','E170'],
    ['Toyota','RAV4',2006,2012,'2AZ-FE',2.4,'бензин','XA30'],
    ['Toyota','RAV4',2013,2018,'3ZR-FE',2.0,'бензин','XA40'],
    ['Toyota','Land Cruiser',2008,2021,'1UR-FE',4.6,'бензин','J200'],
    ['Toyota','Land Cruiser',2008,2021,'1VD-FTV',4.5,'дизель','J200'],
    ['Toyota','Land Cruiser Prado',2009,2023,'1GR-FE',4.0,'бензин','J150'],
    ['Toyota','Land Cruiser Prado',2009,2023,'1KD-FTV',3.0,'дизель','J150'],
    ['Toyota','Hilux',2015,2023,'2GD-FTV',2.4,'дизель','AN120'],
    ['Toyota','Highlander',2008,2013,'2GR-FE',3.5,'бензин','XU40'],
    // Hyundai
    ['Hyundai','Sonata',2009,2014,'G4KD',2.0,'бензин','YF'],
    ['Hyundai','Sonata',2014,2019,'G4KH',2.0,'бензин','LF'],
    ['Hyundai','Accent',2010,2017,'G4FC',1.6,'бензин','RB'],
    ['Hyundai','Elantra',2010,2015,'G4FC',1.8,'бензин','MD'],
    ['Hyundai','Tucson',2015,2020,'G4FD',2.0,'бензин','TL'],
    ['Hyundai','Santa Fe',2012,2018,'G4KH',2.4,'бензин','DM'],
    ['Hyundai','Creta',2016,2021,'G4FG',1.6,'бензин','GS'],
    // Kia
    ['Kia','Rio',2011,2017,'G4FC',1.6,'бензин','QB'],
    ['Kia','Cerato',2013,2018,'G4FG',1.6,'бензин','YD'],
    ['Kia','Sportage',2010,2015,'G4KD',2.0,'бензин','SL'],
    ['Kia','Sorento',2012,2015,'G4KE',2.4,'бензин','XM'],
    ['Kia','Optima',2010,2015,'G4KD',2.0,'бензин','TF'],
    ['Kia','Optima',2015,2020,'G4NA',2.0,'бензин','JF'],
    // Mercedes-Benz
    ['Mercedes-Benz','E-Class',2002,2009,'M272',3.5,'бензин','W211'],
    ['Mercedes-Benz','E-Class',2009,2016,'M274',2.0,'бензин','W212'],
    ['Mercedes-Benz','C-Class',2007,2014,'M271',1.8,'бензин','W204'],
    ['Mercedes-Benz','C-Class',2014,2021,'M274',2.0,'бензин','W205'],
    ['Mercedes-Benz','S-Class',2005,2013,'M273',4.7,'бензин','W221'],
    // BMW
    ['BMW','3 Series',2005,2012,'N52',2.5,'бензин','E90'],
    ['BMW','3 Series',2012,2019,'N20',2.0,'бензин','F30'],
    ['BMW','5 Series',2003,2010,'N52',3.0,'бензин','E60'],
    ['BMW','5 Series',2010,2017,'N20',2.0,'бензин','F10'],
    ['BMW','X5',2007,2013,'N55',3.0,'бензин','E70'],
    // Chevrolet
    ['Chevrolet','Cobalt',2011,2023,'B15D2',1.5,'бензин','T250'],
    ['Chevrolet','Lacetti',2004,2013,'F16D3',1.6,'бензин','J200'],
    ['Chevrolet','Captiva',2006,2018,'LE9',2.4,'бензин','C100'],
    ['Chevrolet','Spark',2010,2015,'B10D1',1.0,'бензин','M300'],
    ['Chevrolet','Malibu',2016,2023,'LTG',2.0,'бензин','IX'],
    ['Chevrolet','Nexia',2020,2023,'B15D2',1.5,'бензин','3'],
    // Opel
    ['Opel','Astra',2009,2015,'A16XER',1.6,'бензин','J'],
    ['Opel','Vectra',2002,2008,'Z18XE',1.8,'бензин','C'],
    // Daewoo
    ['Daewoo','Nexia',1995,2016,'A15MF',1.5,'бензин','N100'],
    ['Daewoo','Matiz',1998,2015,'B10S1',0.8,'бензин','M100'],
    ['Daewoo','Gentra',2013,2015,'F16D3',1.6,'бензин',''],
    // Honda
    ['Honda','Civic',2006,2011,'R18A',1.8,'бензин','FD'],
    ['Honda','Accord',2008,2013,'K24Z',2.4,'бензин','CU'],
    ['Honda','CR-V',2007,2012,'K24Z',2.4,'бензин','RE'],
    // Nissan
    ['Nissan','Almera',2012,2018,'K4M',1.6,'бензин','G15'],
    ['Nissan','X-Trail',2007,2013,'MR20DE',2.0,'бензин','T31'],
    ['Nissan','X-Trail',2014,2021,'MR20DD',2.0,'бензин','T32'],
    ['Nissan','Qashqai',2013,2019,'MR20DD',2.0,'бензин','J11'],
    ['Nissan','Teana',2008,2013,'VQ25DE',2.5,'бензин','J32'],
    // Mitsubishi
    ['Mitsubishi','Lancer',2007,2017,'4B11',2.0,'бензин','X'],
    ['Mitsubishi','Outlander',2012,2021,'4J12',2.4,'бензин','GF'],
    ['Mitsubishi','Pajero',2006,2021,'6G72',3.0,'бензин','IV'],
];

$modelIdCache = [];
$resolveModel = static function (string $make, string $model) use ($db, &$modelIdCache): int {
    $key = vehSlug($make) . '/' . vehSlug($model);
    if (isset($modelIdCache[$key])) return $modelIdCache[$key];
    $st = $db->prepare("SELECT md.id FROM vehicle_models md JOIN vehicle_makes mk ON mk.id=md.make_id
                         WHERE mk.slug=? AND md.slug=? LIMIT 1");
    $st->execute([vehSlug($make), vehSlug($model)]);
    return $modelIdCache[$key] = (int)$st->fetchColumn();
};
$modExists = $db->prepare("SELECT 1 FROM vehicle_mods WHERE model_id=? AND year_from<=>? AND year_to<=>? AND engine<=>? LIMIT 1");
$modIns    = $db->prepare("INSERT INTO vehicle_mods (model_id, name, year_from, year_to, engine, volume, fuel, body) VALUES (?,'',?,?,?,?,?,?)");
$madeMo = 0;
foreach ($modSeed as [$mk, $md, $yf, $yt, $eng, $vol, $fuel, $body]) {
    $mid = $resolveModel($mk, $md);
    if ($mid <= 0) continue;
    $modExists->execute([$mid, $yf ?: null, $yt ?: null, $eng ?: null]);
    if ($modExists->fetchColumn()) continue;   // уже есть — пропускаем
    $modIns->execute([$mid, $yf ?: null, $yt ?: null, $eng ?: null, $vol ?: null, $fuel ?: null, $body ?: null]);
    $madeMo++;
}
echo "  [OK] модификаций добавлено: $madeMo\n";

$totalMk = (int)$db->query("SELECT COUNT(*) FROM vehicle_makes")->fetchColumn();
$totalMd = (int)$db->query("SELECT COUNT(*) FROM vehicle_models")->fetchColumn();
echo str_repeat('-', 60) . "\n";
echo "Всего в справочнике: марок $totalMk, моделей $totalMd.\n";
echo "Модификации (годы/двигатель) добавляются в админке «Автомобили» или авто-скриптом.\n";
echo "Готово.\n";
