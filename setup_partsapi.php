<?php
/**
 * Одноразовая настройка PartsAPI — запусти один раз, потом удали файл.
 * Запуск: php setup_partsapi.php   (из папки ~/public_html)
 * Или открой в браузере: https://avtozapchast.ru/setup_partsapi.php
 *   (но сразу после удали файл с сервера!)
 */
require_once __DIR__ . '/config/config.php';

$settings = [
    // ── VIN-декодер (метод VINdecodeOE) ──────────────────────────────────
    'vin_search_enabled'  => '1',
    'vin_api_provider'    => 'partsapi',
    'vin_api_url'         => 'https://api.partsapi.ru/?method=VINdecodeOE&key={KEY}&vin={VIN}&format=json',
    'vin_api_key'         => 'd69755043f0039590917b73e48c03aea',
    'vin_api_timeout'     => '10',

    // ── Каталог запчастей по VIN (метод getPartsbyVIN) ────────────────────
    'catalog_api_enabled'  => '1',
    'catalog_api_provider' => 'partsapi',
    'catalog_api_url'      => 'https://api.partsapi.ru/?method=getPartsbyVIN&key={KEY}&vin={VIN}&format=json',
    'catalog_api_key'      => '5c52f6e4db91259648e10e3dfab5828e',
    'catalog_api_timeout'  => '12',
];

$ok = 0; $fail = 0;
$isCli = (PHP_SAPI === 'cli');
$br = $isCli ? "\n" : "<br>\n";

foreach ($settings as $key => $value) {
    try {
        setSetting($key, $value);
        echo ($isCli ? "✓ " : "<span style='color:green'>✓ </span>") . "$key = " . (strpos($key,'key') !== false ? '***' : $value) . $br;
        $ok++;
    } catch (Exception $e) {
        echo ($isCli ? "✗ " : "<span style='color:red'>✗ </span>") . "$key: " . $e->getMessage() . $br;
        $fail++;
    }
}

echo $br . ($isCli ? "─────────────────────────────" : "<hr>") . $br;
echo "Готово: $ok из " . ($ok + $fail) . " настроек сохранено." . $br;
echo $br;

// Тест VIN-декодера
require_once __DIR__ . '/includes/vin_service.php';
$testVin = 'WBAWX31060PK42218'; // валидный BMW VIN
echo "Тест VIN-декодера ($testVin)..." . $br;
$decoded = VinService::decode($testVin);
if (!empty($decoded['make'])) {
    echo ($isCli ? "✓ " : "<span style='color:green'>✓ </span>") . "VIN OK: " . $decoded['make'] . ' ' . ($decoded['model'] ?? '') . ' ' . ($decoded['year'] ?? '') . " (источник: " . ($decoded['source'] ?? '?') . ")" . $br;
} else {
    echo ($isCli ? "✗ " : "<span style='color:orange'>⚠ </span>") . "VIN: ответ пустой (ключ может быть неактивен или URL не тот)" . $br;
    if (!empty($decoded)) echo "  Ответ: " . json_encode($decoded, JSON_UNESCAPED_UNICODE) . $br;
}

// Тест каталога
require_once __DIR__ . '/includes/catalog_api.php';
echo $br . "Тест каталога запчастей ($testVin)..." . $br;
$catalogTest = CatalogApi::testConnection($testVin);
if ($catalogTest['ok']) {
    echo ($isCli ? "✓ " : "<span style='color:green'>✓ </span>") . $catalogTest['message'] . $br;
} else {
    echo ($isCli ? "✗ " : "<span style='color:orange'>⚠ </span>") . $catalogTest['message'] . " (HTTP " . $catalogTest['http'] . ")" . $br;
    if (!empty($catalogTest['sample'])) {
        echo "  Фрагмент ответа: " . mb_substr($catalogTest['sample'], 0, 300) . $br;
    }
}

echo $br;
echo ($isCli ? "⚠  УДАЛИ ЭТОТ ФАЙЛ: rm ~/public_html/setup_partsapi.php" : "<strong style='color:red'>⚠ УДАЛИ ЭТОТ ФАЙЛ С СЕРВЕРА: rm ~/public_html/setup_partsapi.php</strong>") . $br;
