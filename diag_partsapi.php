<?php
/**
 * Диагностика PartsAPI v3.
 *  - проверяет VINdecodeOE на «рабочем» VIN из документации (богатый ответ);
 *  - ищет метод списка товарных групп (нужен для getPartsbyVIN: vin+type+cat);
 *  - пробует getPartsbyVIN с разными type/cat.
 * Запуск: php diag_partsapi.php  →  пришли весь вывод  →  rm diag_partsapi.php
 */
require_once __DIR__ . '/config/config.php';

$vinKey = getSetting('vin_api_key', '');
$catKey = getSetting('catalog_api_key', '');

function hit(string $url, int $t = 15): array {
    $ctx = stream_context_create(['http' => [
        'timeout' => $t, 'ignore_errors' => true,
        'header'  => "Accept: application/json\r\nUser-Agent: Mozilla/5.0\r\n",
    ]]);
    $b = @file_get_contents($url, false, $ctx);
    $c = 0;
    foreach (($http_response_header ?? []) as $h)
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $c = (int)$m[1];
    return ['http' => $c, 'body' => $b === false ? '' : $b];
}
function show(string $t, string $url): void {
    echo "\n=== $t ===\n" . preg_replace('/key=[^&]+/', 'key=***', $url) . "\n";
    $r = hit($url);
    echo "HTTP {$r['http']}\n";
    $j = json_decode($r['body'], true);
    echo is_array($j)
        ? substr(json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 0, 2500)
        : substr($r['body'], 0, 800);
    echo "\n";
}

$api = 'https://api.partsapi.ru/?format=json';
$docVin = 'Z8TXLCW6WCM902224'; // VIN из документации PartsAPI (точно в OE-каталоге)

// 1) VIN-декодер на рабочем VIN — увидеть реальные поля
show('VINdecodeOE (рабочий VIN из доки)', "$api&method=VINdecodeOE&key=$vinKey&vin=$docVin");

// 2) Поиск метода списка товарных групп (имена-кандидаты)
foreach ([
    'getGroupsbyVIN', 'getCatsbyVIN', 'getCategoriesbyVIN', 'getTypesbyVIN',
    'getGroups', 'getCats', 'getOECategoriesbyVIN', 'getNodesbyVIN',
] as $method) {
    show("проба метода: $method", "$api&method=$method&key=$catKey&vin=$docVin");
}

// 3) getPartsbyVIN с разными type/cat (вдруг подойдёт простое значение)
foreach ([
    ['all', 'all'], ['0', '0'], ['1', '1'], ['oem', 'all'], ['original', 'all'],
] as [$type, $cat]) {
    show("getPartsbyVIN type=$type cat=$cat", "$api&method=getPartsbyVIN&key=$catKey&vin=$docVin&type=$type&cat=$cat");
}

echo "\n⚠  Пришли весь вывод. Потом: rm ~/public_html/diag_partsapi.php\n";
