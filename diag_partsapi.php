<?php
/**
 * Диагностика PartsAPI v2 — тянет ДОКУМЕНТАЦИЮ методов с partsapi.ru
 * (с хостинга это доступно) + пробует рабочие варианты вызова.
 * Запуск: php diag_partsapi.php   →  пришли весь вывод  →  rm diag_partsapi.php
 */
require_once __DIR__ . '/config/config.php';

$vinKey = getSetting('vin_api_key', '');
$catKey = getSetting('catalog_api_key', '');
$vin    = 'WBAWX31060PK42218';

function fetchRaw(string $url, int $timeout = 20): array {
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout, 'ignore_errors' => true,
        'header'  => "Accept: text/html,application/json\r\n" .
                     "User-Agent: Mozilla/5.0 (compatible; SetupBot/1.0)\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach (($http_response_header ?? []) as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
    }
    return ['http' => $code, 'body' => $body === false ? '' : $body];
}

/* Очистить HTML документации до читаемого текста */
function docText(string $html): string {
    // вырезать script/style
    $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html);
    // сохранить переносы у блочных тегов
    $html = preg_replace('#<(br|/p|/div|/li|/tr|/h\d|/pre|/table)[^>]*>#i', "\n", $html);
    $txt  = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $txt  = preg_replace('/[ \t]+/', ' ', $txt);
    $txt  = preg_replace('/\n{3,}/', "\n\n", $txt);
    return trim($txt);
}

function showDoc(string $title, string $url): void {
    echo "\n############################################################\n";
    echo "#  $title\n#  $url\n";
    echo "############################################################\n";
    $r = fetchRaw($url);
    echo "HTTP: {$r['http']}\n";
    if ($r['body'] === '') { echo "(пустой ответ)\n"; return; }
    $j = json_decode($r['body'], true);
    if (is_array($j)) { echo json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n"; return; }
    echo substr(docText($r['body']), 0, 3500) . "\n";
}

function showApi(string $title, string $url): void {
    echo "\n========================================================\n";
    echo "  $title\n  " . preg_replace('/key=[^&]+/', 'key=***', $url) . "\n";
    echo "========================================================\n";
    $r = fetchRaw($url, 15);
    echo "HTTP: {$r['http']}\n";
    $j = json_decode($r['body'], true);
    echo is_array($j)
        ? substr(json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 0, 2500) . "\n"
        : substr($r['body'], 0, 1500) . "\n";
}

echo "VIN: $vin\n";

/* ── 1. Документация методов (главное!) ─────────────────────────── */
showDoc('ДОК: getPartsbyVIN', 'https://partsapi.ru/method/doc/getPartsbyVIN');
showDoc('ДОК: VINdecodeOE',   'https://partsapi.ru/method/doc/VINdecodeOE');
showDoc('ДОК: список методов', 'https://partsapi.ru/docs');

/* ── 2. Пробы вызова каталога с разными параметрами ─────────────── */
$base = 'https://api.partsapi.ru/?format=json&key=' . $catKey;
showApi('getPartsbyVIN + vin',           $base . '&method=getPartsbyVIN&vin=' . $vin);
showApi('getPartsByVIN (camelCase)',     $base . '&method=getPartsByVIN&vin=' . $vin);
showApi('getPartsbyVIN + q',             $base . '&method=getPartsbyVIN&q=' . $vin);

echo "\n⚠  Скопируй ВЕСЬ вывод и пришли. Потом: rm ~/public_html/diag_partsapi.php\n";
