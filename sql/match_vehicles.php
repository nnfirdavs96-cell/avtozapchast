<?php
/**
 * Авто-привязка товаров к авто по названию и описанию (поле «Применимость»).
 *
 * Для товаров БЕЗ привязки к авто пытается определить марку/модель из текста
 * (например «Крепление Бампера Lexus Rx350» → Lexus RX350; «TOYOTA 2GR» →
 * модели с двигателем 2GR). Найденные связи сохраняются с пометкой «требует
 * проверки» (needs_review=1) — их потом видно в админке. Товары, у которых уже
 * есть привязки, не трогаем (если не задан --force).
 *
 * Запуск:
 *   php sql/match_vehicles.php            # привязать (только товары без авто)
 *   php sql/match_vehicles.php --dry      # только показать, что нашлось
 *   php sql/match_vehicles.php --limit=500
 *   php sql/match_vehicles.php --force    # переобработать и те, где уже есть авто-связи
 *   php sql/match_vehicles.php --reset    # удалить прежние авто-привязки и собрать заново
 *
 * Сопоставление идёт по ЦЕЛЫМ словам (а не подстроке), поэтому «Optimal» не
 * привязывается к модели «Optima», а «rx350» в «rx350/450h» находится.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vehicles.php';

$db  = getDB();
$dry   = in_array('--dry', $argv, true);
$force = in_array('--force', $argv, true);
$limit = 0;
foreach ($argv as $a) { if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int)$m[1]; }

echo "Авто-привязка товаров к авто\n";
echo "База: " . DB_NAME . ($dry ? "  [ПРОБНЫЙ ПРОГОН]\n" : "\n");
echo str_repeat('-', 60) . "\n";

if (!vehSchemaReady($db)) { exit("Справочник авто не создан — сначала php sql/migrate_vehicles.php\n"); }

// Справочник в память.
$makes  = $db->query("SELECT id, name FROM vehicle_makes WHERE is_active=1")->fetchAll();
$models = $db->query("SELECT md.id, md.name, md.make_id, mk.name AS make_name
                        FROM vehicle_models md JOIN vehicle_makes mk ON mk.id=md.make_id
                       WHERE md.is_active=1")->fetchAll();
$mods   = $db->query("SELECT id, model_id, engine FROM vehicle_mods WHERE is_active=1 AND engine IS NOT NULL AND engine<>''")->fetchAll();

// Нормализация: компакт (только буквы/цифры, верхний регистр).
$compact = static fn(string $s): string => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));

// Модель уникальна по компакт-имени? (иначе марку надо подтверждать текстом)
$modelNameCount = [];
foreach ($models as $md) { $k = $compact($md['name']); $modelNameCount[$k] = ($modelNameCount[$k] ?? 0) + 1; }

// Токены имени (ЦЕЛЫЕ слова, верхний регистр) — чтобы «Optimal» не ловил «Optima».
$toTokens = static fn(string $s): array => array_values(array_filter(
    preg_split('/[^A-Za-z0-9]+/', strtoupper($s)), static fn($t) => $t !== ''));
// Совпадение: ВСЕ слова имени встречаются в тексте как отдельные слова и хотя бы
// одно слово длиной ≥3 (чтобы не цепляться за одиночную цифру вроде «3»).
$nameHit = static function (array $nameTokens, array $set): bool {
    if (!$nameTokens) return false;
    $maxLen = 0;
    foreach ($nameTokens as $t) { if (!isset($set[$t])) return false; if (strlen($t) > $maxLen) $maxLen = strlen($t); }
    return $maxLen >= 3;
};

foreach ($makes as &$mk)  { $mk['t'] = $toTokens($mk['name']); } unset($mk);
foreach ($models as &$md) { $md['t'] = $toTokens($md['name']); $md['c'] = $compact($md['name']); } unset($md);
// Двигатель: код до первого дефиса/пробела, целым словом: «2GR-FE 3.5» → «2GR».
foreach ($mods as &$mo)   { $firstTok = preg_split('/[-\s]+/', trim((string)$mo['engine']))[0] ?? ''; $mo['e'] = strtoupper($compact($firstTok)); } unset($mo);

// --reset: очистить прежние авто-привязки (needs_review=1), чтобы перепрогон был чистым.
if (in_array('--reset', $argv, true) && !$dry) {
    $n = $db->exec("DELETE FROM part_vehicles WHERE needs_review=1");
    echo "  [reset] удалено прежних авто-привязок: " . (int)$n . "\n";
}

// Товары без привязки (или все авто-связи, если --force).
$cond = $force
    ? "NOT EXISTS (SELECT 1 FROM part_vehicles pv WHERE pv.part_id=p.id AND pv.needs_review=0)"
    : "NOT EXISTS (SELECT 1 FROM part_vehicles pv WHERE pv.part_id=p.id)";
$sql = "SELECT p.id, p.name, p.description FROM parts p WHERE p.is_active=1 AND $cond ORDER BY p.id";
if ($limit > 0) $sql .= " LIMIT $limit";
$parts = $db->query($sql)->fetchAll();

$matchedParts = 0; $totalLinks = 0; $examples = 0;

foreach ($parts as $p) {
    $text   = (string)$p['name'] . ' ' . (string)$p['description'];
    $tokens = $toTokens($text);
    if (!$tokens) continue;
    $set = array_flip($tokens);

    $linkModels = [];   // model_id => mod_id|null
    $makesHit   = [];   // make_id => true
    foreach ($makes as $mk) { if ($nameHit($mk['t'], $set)) $makesHit[(int)$mk['id']] = true; }

    foreach ($models as $md) {
        if (!$nameHit($md['t'], $set)) continue;
        $unique = ($modelNameCount[$md['c']] ?? 0) === 1;
        $makeOk = isset($makesHit[(int)$md['make_id']]);
        // Берём модель, если её марка упомянута, или имя модели уникально в справочнике.
        if ($makeOk || $unique) $linkModels[(int)$md['id']] = null;
    }

    // Двигатель: «2GR» (целым словом) → конкретная модификация.
    foreach ($mods as $mo) {
        if ($mo['e'] === '' || strlen($mo['e']) < 3 || !isset($set[$mo['e']])) continue;
        $linkModels[(int)$mo['model_id']] = (int)$mo['id'];
    }

    if (!$linkModels) continue;

    $items = [];
    foreach ($linkModels as $mid => $modId) $items[] = ['model_id' => $mid, 'mod_id' => $modId];

    $matchedParts++; $totalLinks += count($items);
    if ($examples < 15) {
        $names = [];
        foreach (array_keys($linkModels) as $mid) {
            foreach ($models as $md) { if ((int)$md['id'] === $mid) { $names[] = $md['make_name'] . ' ' . $md['name']; break; } }
        }
        echo "  #{$p['id']} " . mb_substr($p['name'], 0, 42) . " → " . implode(', ', $names) . "\n";
        $examples++;
    }

    if (!$dry) partSaveVehicles($db, (int)$p['id'], $items, true);   // needs_review=1
}

echo str_repeat('-', 60) . "\n";
echo "Просмотрено товаров без авто: " . count($parts) . "\n";
echo ($dry ? "Нашлось бы" : "Привязано") . " товаров: $matchedParts, связей: $totalLinks (все помечены «требует проверки»).\n";
if ($dry) echo "Это пробный прогон — ничего не сохранено. Убери --dry, чтобы применить.\n";
echo "Готово.\n";
