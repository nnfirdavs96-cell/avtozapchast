<?php
/**
 * Импорт прайс-листа AutoEuro (CSV) в словарь названий ae_part_dictionary — CLI.
 *
 * Прайс AutoEuro (личный кабинет → Прайс-лист, формат CSV, UTF-8, разделитель «;»)
 * содержит столбцы:
 *   Производитель ; Марка ; КаталожныйНомер ; НомерПроизводителя ; ОригинальныйНомер ;
 *   Применение ; Цена ; Единица ; Наличие ; МинУпаковка ; Комментарий ; Валюта ; …
 *
 * Берём: Производитель → бренд, НомерПроизводителя → артикул, Применение → название.
 * Заливаем ТОЛЬКО названия (артикул+бренд+название). Цену НЕ храним — она живая из API.
 * Никакой нагрузки на AutoEuro: это разбор файла, ни одного запроса к API.
 *
 * Запуск:
 *   php superadmin/ae_dict_import_csv.php <файл.csv>          # импорт в БД
 *   php superadmin/ae_dict_import_csv.php <файл.csv> --dry    # проверка разбора без БД
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из консоли (CLI).\n"); }

$file = (string)($argv[1] ?? '');
$dry  = in_array('--dry', $argv, true);
if ($file === '' || !is_readable($file)) {
    exit("Файл не найден/не читается.\nЗапуск: php superadmin/ae_dict_import_csv.php <файл.csv> [--dry]\n");
}

$db = null;
if (!$dry) {
    require dirname(__DIR__) . '/config/config.php';
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS ae_part_dictionary (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        oem_key VARCHAR(64) NOT NULL,
        oem VARCHAR(64) NOT NULL,
        brand VARCHAR(120) NOT NULL DEFAULT '',
        name VARCHAR(255) NOT NULL DEFAULT '',
        source VARCHAR(20) NOT NULL DEFAULT 'search',
        hits INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_oem_brand (oem_key, brand),
        KEY idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Полнотекстовый индекс — для быстрого поиска по словам (если ещё нет).
    try { $db->exec("ALTER TABLE ae_part_dictionary ADD FULLTEXT KEY ft_name (name)"); }
    catch (Exception $e) { /* уже есть — не страшно */ }
}

$fh = fopen($file, 'rb');
if (!$fh) exit("Не удалось открыть файл.\n");

$header = fgetcsv($fh, 0, ';');
if (!$header) exit("Пустой файл или не CSV.\n");
// Снять BOM и пробелы в заголовках.
$header = array_map(static fn($h) => trim((string)$h, "\xEF\xBB\xBF \t\r\n"), $header);
$idx = array_flip($header);
$col = static fn(string $n): int => $idx[$n] ?? -1;

$iBrand = $col('Производитель');
$iArt   = $col('НомерПроизводителя');
$iName  = $col('Применение');
if ($iBrand < 0 || $iArt < 0 || $iName < 0) {
    exit("Не найдены нужные столбцы (Производитель/НомерПроизводителя/Применение).\nЗаголовок: " . implode(' | ', $header) . "\n");
}

$norm = static fn(string $s): string => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));

$BATCH = 1000;
$batch = [];
$total = 0; $ins = 0; $skip = 0;

$flush = static function () use (&$batch, &$ins, $db) {
    if (!$batch) return;
    $ph  = implode(',', array_fill(0, count($batch), '(?,?,?,?,\'price\',0)'));
    $sql = "INSERT INTO ae_part_dictionary (oem_key,oem,brand,name,source,hits) VALUES $ph
            ON DUPLICATE KEY UPDATE name=VALUES(name), oem=VALUES(oem), updated_at=NOW()";
    $args = [];
    foreach ($batch as $r) { array_push($args, $r[0], $r[1], $r[2], $r[3]); }
    $db->beginTransaction();
    $db->prepare($sql)->execute($args);
    $db->commit();
    $ins += count($batch);
    $batch = [];
};

while (($row = fgetcsv($fh, 0, ';')) !== false) {
    $total++;
    $art   = trim((string)($row[$iArt]   ?? ''));
    $brand = trim((string)($row[$iBrand] ?? ''));
    $name  = trim((string)($row[$iName]  ?? ''));
    if ($art === '' || $name === '') { $skip++; continue; }
    $key = $norm($art);
    if ($key === '') { $skip++; continue; }

    if ($dry) {
        if ($total <= 12) echo "$key | $brand | $art | $name\n";
        continue;
    }

    $batch[] = [$key, mb_substr($art, 0, 64), mb_substr($brand, 0, 120), mb_substr($name, 0, 255)];
    if (count($batch) >= $BATCH) {
        $flush();
        if ($ins % 20000 === 0) echo "…залито строк: $ins\n";
    }
}
if (!$dry) $flush();
fclose($fh);

if ($dry) {
    echo "\n[DRY] Всего строк данных: $total, пропущено (пустой артикул/название): $skip.\n";
    echo "Столбцы найдены: Производитель=#$iBrand, НомерПроизводителя=#$iArt, Применение=#$iName.\n";
    exit(0);
}

echo "\nГотово. Прочитано строк: $total, пропущено: $skip, залито/обновлено: $ins.\n";
try {
    $n = $db->query("SELECT COUNT(*) FROM ae_part_dictionary")->fetchColumn();
    echo "Всего в словаре: $n.\n";
} catch (Exception $e) {}
