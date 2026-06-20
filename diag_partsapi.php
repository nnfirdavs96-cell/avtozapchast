<?php
/**
 * Диагностика PartsAPI — показывает СЫРОЙ ответ обоих методов,
 * чтобы донастроить парсинг. Запусти, скопируй весь вывод, пришли его.
 * Запуск: php diag_partsapi.php
 * Потом удали: rm ~/public_html/diag_partsapi.php
 */
require_once __DIR__ . '/config/config.php';

$vinKey = getSetting('vin_api_key', '');
$catKey = getSetting('catalog_api_key', '');
$vin    = 'WBAWX31060PK42218';

function hit(string $url): array {
    $ctx = stream_context_create(['http' => [
        'timeout' => 15, 'ignore_errors' => true,
        'header'  => "Accept: application/json\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach (($http_response_header ?? []) as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
    }
    return ['http' => $code, 'body' => $body === false ? '(нет ответа)' : $body];
}

function show(string $title, string $url): void {
    echo "\n========================================================\n";
    echo "  $title\n";
    echo "  URL: " . preg_replace('/key=[^&]+/', 'key=***', $url) . "\n";
    echo "========================================================\n";
    $r = hit($url);
    echo "HTTP: {$r['http']}\n";
    // pretty-print если JSON
    $j = json_decode($r['body'], true);
    if (is_array($j)) {
        echo substr(json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000) . "\n";
    } else {
        echo substr($r['body'], 0, 2000) . "\n";
    }
}

echo "VIN: $vin\n";

// 1) VIN-декодер — посмотреть реальные поля (model/year отсутствовали)
show('1) VINdecodeOE', "https://api.partsapi.ru/?method=VINdecodeOE&key={$vinKey}&vin={$vin}&format=json");

// 2) Каталог — текущий вызов (даёт 5007)
show('2) getPartsbyVIN (vin)', "https://api.partsapi.ru/?method=getPartsbyVIN&key={$catKey}&vin={$vin}&format=json");

// 3) Каталог — пробуем альтернативные имена параметра
show('3) getPartsbyVIN (VIN заглавн.)', "https://api.partsapi.ru/?method=getPartsbyVIN&key={$catKey}&VIN={$vin}&format=json");

// 4) Может метод в пути, не в query
show('4) путь /getPartsbyVIN', "https://api.partsapi.ru/getPartsbyVIN?key={$catKey}&vin={$vin}&format=json");

// 5) Список методов / справка (часто помогает)
show('5) методы без параметров', "https://api.partsapi.ru/?method=getPartsbyVIN&key={$catKey}&format=json");

echo "\n⚠  Скопируй ВЕСЬ вывод выше и пришли. Потом: rm ~/public_html/diag_partsapi.php\n";
