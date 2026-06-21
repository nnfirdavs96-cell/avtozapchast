<?php
/**
 * Диагностика связи с PartsAPI.ru — отвечает на вопрос «почему ошибок == групп».
 *
 * Запуск:   php diag_partsapi.php      (или открой в браузере)
 * Удалить:  rm ~/public_html/diag_partsapi.php
 *
 * Что проверяет:
 *   1) транспорт PHP: есть ли cURL, openssl, включён ли allow_url_fopen;
 *   2) ОДИН и тот же запрос к api.partsapi.ru ДВУМЯ способами — cURL и
 *      file_get_contents — и печатает сырой ответ/ошибку каждого. Это сразу
 *      показывает причину:
 *        • cURL работает, fopen падает   → транспорт (правит наш httpGet);
 *        • оба отдают HTTP 200 + текст про лимит → исчерпан лимит ключа;
 *        • оба не коннектятся            → сеть/файрвол хостинга;
 *   3) финальный прогон VinService/CatalogApi (уже через httpGet).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/vin_service.php';
require_once __DIR__ . '/includes/catalog_api.php';

$cli = (PHP_SAPI === 'cli');
$br  = $cli ? "\n" : "<br>\n";
if (!$cli) { header('Content-Type: text/plain; charset=utf-8'); }

function mask(string $s): string {
    $s = trim($s);
    if ($s === '') return '(пусто)';
    $n = strlen($s);
    return $n <= 6 ? str_repeat('*', $n) : substr($s, 0, 3) . str_repeat('*', max(0, $n - 6)) . substr($s, -3);
}

echo "════════ ТРАНСПОРТ PHP ════════$br";
echo "  cURL:            " . (function_exists('curl_init') ? '✓ есть (' . (curl_version()['version'] ?? '?') . ')' : '✗ НЕТ') . $br;
echo "  OpenSSL (curl):  " . (function_exists('curl_init') && (curl_version()['features'] ?? 0) & CURL_VERSION_SSL ? '✓ да' : '?') . $br;
echo "  allow_url_fopen: " . (ini_get('allow_url_fopen') ? '✓ On' : '✗ Off') . $br;
echo "  openssl ext:     " . (extension_loaded('openssl') ? '✓ да' : '✗ нет') . $br;

// ── Ключи и URL из БД ──────────────────────────────────────────────────────
$vinKey   = trim(getSetting('vin_api_key', ''));
$catKey   = trim(getSetting('catalog_api_key', ''));
$vinUrlTl = trim(getSetting('vin_api_url', 'https://api.partsapi.ru/?method=VINdecodeOE&key={KEY}&vin={VIN}&format=json'));
$catBase  = trim(getSetting('catalog_api_base', 'https://api.partsapi.ru/'));

echo $br . "════════ КЛЮЧИ (маскированы) ════════$br";
echo "  vin_api_key (VINdecodeOE): " . mask($vinKey) . $br;
echo "  catalog_api_key (getParts): " . mask($catKey) . $br;

/** Сырой cURL-замер: errno, error, http, время, тело. */
function rawCurl(string $url, int $timeout = 12): array {
    if (!function_exists('curl_init')) return ['skip' => true];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'AvtoZapchast/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $r = [
        'http'  => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'time'  => round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME), 2),
        'errno' => curl_errno($ch),
        'error' => curl_error($ch),
        'body'  => $body === false ? '' : (string)$body,
        'ok'    => $body !== false,
    ];
    curl_close($ch);
    return $r;
}

/** Сырой file_get_contents-замер. */
function rawFopen(string $url, int $timeout = 12): array {
    if (!ini_get('allow_url_fopen')) return ['skip' => true];
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true,
                   'header' => "Accept: application/json\r\nUser-Agent: AvtoZapchast/1.0\r\n"],
    ]);
    $t0   = microtime(true);
    $body = @file_get_contents($url, false, $ctx);
    $http = 0;
    foreach (($http_response_header ?? []) as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $http = (int)$m[1]; break; }
    }
    return [
        'http' => $http, 'time' => round(microtime(true) - $t0, 2),
        'body' => $body === false ? '' : (string)$body, 'ok' => $body !== false,
    ];
}

function dump(string $label, array $r, string $br): void {
    if (!empty($r['skip'])) { echo "  $label: пропущено (транспорт недоступен)$br"; return; }
    echo "  $label: " . ($r['ok'] ? '✓' : '✗')
       . "  http=" . ($r['http'] ?? '-')
       . "  time=" . ($r['time'] ?? '-') . "s"
       . (isset($r['errno']) && $r['errno'] ? "  errno=" . $r['errno'] : '')
       . (!empty($r['error']) ? "  err=" . $r['error'] : '')
       . $br;
    $snip = trim(preg_replace('/\s+/', ' ', mb_substr($r['body'] ?? '', 0, 400)));
    if ($snip !== '') echo "      body: " . $snip . $br;
}

// ── 1. VINdecodeOE ─────────────────────────────────────────────────────────
$vinTest = 'Z8TXLCW6WCM902224';
$vinUrl  = str_ireplace(['{VIN}', '{KEY}'], [rawurlencode($vinTest), rawurlencode($vinKey)], $vinUrlTl);
echo $br . "════════ 1) VINdecodeOE ($vinTest) ════════$br";
dump('cURL ', rawCurl($vinUrl), $br);
dump('fopen', rawFopen($vinUrl), $br);

// ── 2. getPartsbyVIN (одна группа) ─────────────────────────────────────────
$catUrl = rtrim($catBase, '/') . '/?' . http_build_query([
    'method' => 'getPartsbyVIN', 'key' => $catKey, 'vin' => $vinTest,
    'type' => 'oem', 'cat' => 1191, 'format' => 'json',
]);
echo $br . "════════ 2) getPartsbyVIN (cat=1191, type=oem) ════════$br";
dump('cURL ', rawCurl($catUrl), $br);
dump('fopen', rawFopen($catUrl), $br);

// ── 3. Финальный прогон через наш код (httpGet) ────────────────────────────
echo $br . "════════ 3) ЧЕРЕЗ НАШ КОД (httpGet) ════════$br";
VinService::clearCache();
CatalogApi::clearCache();
$d = VinService::decode($vinTest);
echo "  decode: " . trim(($d['make'] ?? '') . ' ' . ($d['model'] ?? '') . ' ' . ($d['year'] ?? ''))
   . " | источник=" . ($d['source'] ?? '?') . $br;
$res = CatalogApi::searchByVin($vinTest, false);
echo "  catalog: групп=" . $res['groups_scanned'] . " ошибок=" . ($res['errors'] ?? 0)
   . " найдено=" . $res['count'] . " (type=" . ($res['type'] ?? '?') . ")" . $br;
foreach (array_slice($res['items'], 0, 5) as $it) {
    echo "    • [" . $it['group'] . "] " . $it['name'] . " — " . $it['brand'] . ' ' . $it['part_number'] . $br;
}

// ── Вывод-вердикт ──────────────────────────────────────────────────────────
echo $br . "════════ ВЕРДИКТ ════════$br";
$c = rawCurl($vinUrl); $f = rawFopen($vinUrl);
$isLimit = function (array $r): bool {
    $b = $r['body'] ?? '';
    if (stripos($b, 'Exceeded the number of requests') !== false) return true;
    $j = json_decode($b, true);
    return is_array($j) && (int)($j['error_code'] ?? 0) === 5000;
};
if ($isLimit($c) || $isLimit($f)) {
    echo "  → Соединение есть, ключ принят, но ПРЕВЫШЕН ЛИМИТ запросов с IP сервера$br";
    echo "    (демо-ключ PartsAPI ≈ 50/сутки, error_code 5000). Код исправен.$br";
    echo "    Решение: дождаться суточного сброса ИЛИ подключить платный тариф PartsAPI.$br";
} elseif (!empty($c['ok']) && empty($f['ok']) && empty($f['skip'])) {
    echo "  → Транспорт: cURL работает, file_get_contents — нет. Это и была причина$br";
    echo "    «источник: local / ошибок==групп». Фикс httpGet (cURL) уже применён.$br";
} elseif (empty($c['ok']) && empty($f['ok'])) {
    echo "  → Ни cURL, ни fopen не достучались до api.partsapi.ru — сеть/файрвол хостинга.$br";
    echo "    Проверь у хостера исходящие соединения на api.partsapi.ru:443.$br";
} else {
    echo "  → Смотри сырые тела выше: в них точная причина (лимит/ключ/тип ответа).$br";
}

echo $br . "⚠  УДАЛИ ФАЙЛ: rm ~/public_html/diag_partsapi.php" . $br;
