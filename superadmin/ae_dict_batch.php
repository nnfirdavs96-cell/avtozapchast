<?php
/**
 * Пакетное наполнение словаря названий AutoEuro (ae_part_dictionary) — ТОЛЬКО CLI.
 *
 * Берёт артикулы, которые УЖЕ есть у вас в базе (OEM-номера из сохранённых
 * Tradesoft-схем + part_number из каталога) ИЛИ из файла со списком номеров,
 * и по каждому спрашивает AutoEuro — название сохраняется в словарь
 * (через AutoEuroPriceProvider::rememberName). Цену НЕ храним.
 *
 * Аккуратно, чтобы не поймать бан:
 *   • за один запуск — не больше LIMIT артикулов (по умолчанию 100);
 *   • пауза между запросами (SLEEP_MS, по умолчанию 500 мс);
 *   • пропускаем артикулы, которые уже есть в словаре (докручиваем по чуть-чуть);
 *   • не больше 3 брендов на артикул (меньше запросов).
 *
 * Запуск:
 *   php superadmin/ae_dict_batch.php [limit] [sleep_ms] [файл_со_списком]
 * Примеры:
 *   php superadmin/ae_dict_batch.php                 # 100 шт, пауза 500мс, из БД
 *   php superadmin/ae_dict_batch.php 100 800         # 100 шт, пауза 800мс
 *   php superadmin/ae_dict_batch.php 50 500 arts.txt # 50 шт из файла (по номеру в строке)
 *
 * Можно ставить в cron раз в день — так словарь растёт «по 100 в день».
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Только из консоли (CLI).\n");
}

require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/includes/catalog/AutoEuroPriceProvider.php';

$limit   = max(1, (int)($argv[1] ?? 100));
$sleepMs = max(0, (int)($argv[2] ?? 500));
$file    = trim((string)($argv[3] ?? ''));

if (AutoEuro::fromSettings() === null) {
    exit("AutoEuro выключен или не настроен (autoeuro_enabled + ключ). Включите в Суперадмин → Склад.\n");
}
if (trim(getSetting('autoeuro_delivery_key', '')) === '') {
    exit("Не задан delivery_key (Суперадмин → Склад).\n");
}

$db = getDB();
$norm = static fn(string $s): string => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));

// Уже известные артикулы — чтобы не спрашивать повторно.
$known = [];
try {
    foreach ($db->query("SELECT oem_key FROM ae_part_dictionary") as $r) {
        $known[$r['oem_key']] = true;
    }
} catch (Exception $e) { /* таблицы ещё нет — ничего страшного */ }

// ── Сбор кандидатов ──────────────────────────────────────────────────────────
$cands = [];
$seen  = [];
$add = static function (string $num) use (&$cands, &$seen, $known, $norm, $limit): bool {
    $num = trim($num);
    if ($num === '') return true;
    $key = $norm($num);
    if ($key === '' || isset($seen[$key]) || isset($known[$key])) return true;
    $seen[$key] = true;
    $cands[]    = $num;
    return count($cands) < $limit;   // false → достигли лимита
};

if ($file !== '') {
    if (!is_readable($file)) exit("Файл не найден/не читается: $file\n");
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!$add($line)) break;
    }
} else {
    // 1) OEM-номера из сохранённых Tradesoft-схем.
    try {
        $st = $db->query("SELECT parts_json FROM catalog_library_schemes WHERE parts_json IS NOT NULL");
        foreach ($st as $row) {
            $parts = json_decode($row['parts_json'], true);
            if (!is_array($parts)) continue;
            foreach ($parts as $p) {
                if (!$add((string)($p['number'] ?? ''))) break 2;
            }
        }
    } catch (Exception $e) { fwrite(STDERR, "Схемы читать не удалось: " . $e->getMessage() . "\n"); }

    // 2) Добор из каталога, если ещё не набрали лимит.
    if (count($cands) < $limit) {
        try {
            foreach ($db->query("SELECT part_number FROM parts WHERE part_number <> ''") as $r) {
                if (!$add((string)$r['part_number'])) break;
            }
        } catch (Exception $e) { /* каталог мог быть пуст */ }
    }
}

$total = count($cands);
echo "К обработке: $total артикулов (лимит $limit, пауза {$sleepMs}мс).\n";
if ($total === 0) {
    echo "Новых артикулов нет — либо всё уже в словаре, либо нет источника номеров.\n";
    exit(0);
}

// ── Прогон ───────────────────────────────────────────────────────────────────
$prov = new AutoEuroPriceProvider();
$withName = 0;
foreach ($cands as $i => $num) {
    $got = 0;
    try {
        $cards = $prov->offersByArticle($num, 3);   // ≤3 брендов → меньше запросов; имена пишутся сами
        $got   = count($cards);
    } catch (Throwable $e) {
        fwrite(STDERR, "  ошибка на $num: " . $e->getMessage() . "\n");
    }
    if ($got > 0) $withName++;
    echo sprintf("[%d/%d] %-22s → %d\n", $i + 1, $total, $num, $got);
    if ($sleepMs > 0 && $i < $total - 1) usleep($sleepMs * 1000);
}

echo "Готово. С названиями: $withName из $total. ";
try {
    $n = $db->query("SELECT COUNT(*) FROM ae_part_dictionary")->fetchColumn();
    echo "Всего в словаре: $n.\n";
} catch (Exception $e) { echo "\n"; }
