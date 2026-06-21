<?php
/**
 * Починка + проверка VIN/каталога PartsAPI.
 * Запуск:  php fix_vin_catalog.php   →  проверь вывод  →  rm fix_vin_catalog.php
 *
 * Что делает:
 *  1) приводит конфиг к рабочему (provider=partsapi, type=oem и т.д.) — НЕ трогает ключи;
 *  2) ЧИСТИТ оба кэша (vin_cache + partsapi_catalog_cache) — снимает «прилипший» пустой результат;
 *  3) тестирует декод VIN и каталог на двух VIN, печатает диагностику.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/vin_service.php';
require_once __DIR__ . '/includes/catalog_api.php';

$br = (PHP_SAPI === 'cli') ? "\n" : "<br>\n";

// ── 1. Конфиг (без ключей — они уже в БД) ────────────────────────────────
$cfg = [
    // VIN-декодер
    'vin_search_enabled' => '1',
    'vin_api_provider'   => 'partsapi',
    'vin_api_url'        => 'https://api.partsapi.ru/?method=VINdecodeOE&key={KEY}&vin={VIN}&format=json',
    'vin_api_timeout'    => '10',
    // Каталог
    'catalog_api_enabled'    => '1',
    'catalog_api_base'       => 'https://api.partsapi.ru/',
    'catalog_api_type'       => 'oem',   // оригинал; '' = неоригинал
    'catalog_api_max_groups' => '25',
    'catalog_api_timeout'    => '12',
];
foreach ($cfg as $k => $v) { setSetting($k, $v); echo "✓ $k = $v$br"; }

echo $br;
echo (trim(getSetting('vin_api_key','')) !== '' ? "✓ ключ VINdecodeOE задан" : "✗ ключ VINdecodeOE НЕ задан — впишите в админке") . $br;
echo (CatalogApi::hasKey() ? "✓ ключ каталога задан" : "✗ ключ каталога НЕ задан — впишите в админке") . $br;

// ── 2. Чистим кэши (root cause: прилипший пустой результат) ──────────────
$n = VinService::clearCache();
CatalogApi::clearCache();
echo $br . "✓ кэши очищены (vin_cache + partsapi_catalog_cache)" . $br;

echo $br . "════════════════════════════════════" . $br;

// ── 3. Тесты ─────────────────────────────────────────────────────────────
foreach (['XW7BF4FK60S145161', 'Z8TXLCW6WCM902224'] as $vin) {
    echo $br . "### VIN $vin" . $br;

    // декод
    $d = VinService::decode($vin);
    echo "  Авто: " . trim(($d['make'] ?? '') . ' ' . ($d['model'] ?? '') . ' ' . ($d['year'] ?? ''))
       . "  | страна: " . ($d['country'] ?? '?')
       . "  | источник: " . ($d['source'] ?? '?') . $br;

    // каталог
    $res = CatalogApi::searchByVin($vin, false); // без кэша — свежий перебор
    echo "  Каталог: групп " . $res['groups_scanned'] . ", ошибок " . ($res['errors'] ?? 0)
       . ", найдено " . $res['count'] . " (type=" . ($res['type'] ?? '?') . ")" . $br;
    foreach (array_slice($res['items'], 0, 6) as $it) {
        echo "    • [" . $it['group'] . "] " . $it['name'] . " — " . $it['brand'] . ' ' . $it['part_number']
           . ($it['in_catalog'] ? " (склад: " . $it['price'] . ")" : " (под заказ)") . $br;
    }
    if ($res['count'] === 0) {
        echo "    ⚠ 0 позиций. Если ошибок == групп — лимит демо-ключа (≈50/сутки) или сеть." . $br;
    }
}

echo $br . "⚠  УДАЛИ ФАЙЛ: rm ~/public_html/fix_vin_catalog.php" . $br;
