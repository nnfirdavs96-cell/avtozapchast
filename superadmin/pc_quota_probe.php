<?php
/**
 * Диагностика лимитов Parts-Catalogs (Tradesoft) — ТОЛЬКО CLI.
 *
 * Зачем: в личном кабинете Tradesoft расход тарифа не показывается
 * («Отображает распределение запросов по маркам, не отражая расход тарифа»).
 * Многие API отдают остаток квоты в ЗАГОЛОВКАХ ответа (X-RateLimit-Remaining,
 * X-Quota-*, RateLimit-*). Наш httpGet() заголовки не возвращает, поэтому здесь
 * делаем «сырой» запрос через cURL с CURLOPT_HEADER и печатаем ВСЕ заголовки.
 *
 * ВАЖНО: скрипт делает РЕАЛЬНЫЕ обращения к API — по одному на выбранный метод.
 * По умолчанию — самый дешёвый (список каталогов), 1 запрос.
 *
 * Запуск:
 *   php superadmin/pc_quota_probe.php              # 1 запрос: список каталогов
 *   php superadmin/pc_quota_probe.php catalogs     # то же явно
 *   php superadmin/pc_quota_probe.php vin XTA21099 # VIN-декод (тратит VIN-лимит!)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из консоли (CLI).\n"); }

require dirname(__DIR__) . '/config/config.php';

$mode = strtolower(trim((string)($argv[1] ?? 'catalogs')));
$vin  = trim((string)($argv[2] ?? ''));

$base = rtrim(trim(getSetting('catalog_pc_base', 'https://api.parts-catalogs.com/')), '/') . '/';
$key  = trim(getSetting('catalog_pc_key', ''));
$lang = trim(getSetting('catalog_pc_lang', 'ru')) ?: 'ru';

if ($key === '') exit("Не задан ключ catalog_pc_key (Суперадмин → VIN-поиск).\n");

// Способ авторизации — ТОЧНО как в PartsCatalogsAdapter: header | bearer | query.
$authStyle = strtolower(trim(getSetting('catalog_pc_auth', 'header'))) ?: 'header';
if (!in_array($authStyle, ['header', 'bearer', 'query'], true)) $authStyle = 'header';
$keyParam  = trim(getSetting('catalog_pc_key_param', 'api_key')) ?: 'api_key';

switch ($mode) {
    case 'vin':
        if ($vin === '') exit("Укажите VIN: php superadmin/pc_quota_probe.php vin <VIN>\n");
        $path  = 'v1/car/info/' . rawurlencode($vin);
        $query = ['lang' => $lang];
        $note  = 'VIN-декод — списывает VIN-лимит тарифа';
        break;
    case 'catalogs':
    default:
        $path  = 'v1/catalogs';
        $query = ['lang' => $lang];
        $note  = 'список каталогов — самый дешёвый вызов';
        break;
}

if ($authStyle === 'query') $query[$keyParam] = $key;
$url = $base . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');

echo "Метод : $mode ($note)\n";
echo "URL   : " . str_replace($key, '***', $url) . "\n";
echo str_repeat('─', 70) . "\n";

if (!function_exists('curl_init')) exit("cURL недоступен.\n");

// Заголовки — как authHdr() в адаптере.
$hdrs = ['Accept: application/json', 'Accept-Language: ' . $lang, 'Language: ' . $lang];
if     ($authStyle === 'header') $hdrs[] = 'Authorization: ' . $key;
elseif ($authStyle === 'bearer') $hdrs[] = 'Authorization: Bearer ' . $key;
// query → ключ уже в URL

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,       // ← нам нужны заголовки ответа
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => $hdrs,
]);
$resp   = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hsize  = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$err    = curl_error($ch);
curl_close($ch);

if ($resp === false) exit("Ошибка соединения: $err\n");

$rawHeaders = substr($resp, 0, $hsize);
$body       = substr($resp, $hsize);

echo "HTTP  : $status\n\n";
echo "── ВСЕ ЗАГОЛОВКИ ОТВЕТА ────────────────────────────────────────────\n";
echo trim($rawHeaders) . "\n\n";

// Ищем что-нибудь про лимиты/квоту.
$found = [];
foreach (preg_split('/\r\n|\n/', $rawHeaders) as $line) {
    if (preg_match('/^([^:]+):\s*(.*)$/', trim($line), $m)) {
        $name = strtolower($m[1]);
        if (preg_match('/(ratelimit|rate-limit|quota|limit|remaining|usage|balance|credit)/i', $name)) {
            $found[] = trim($m[1]) . ': ' . trim($m[2]);
        }
    }
}

echo "── ЗАГОЛОВКИ ПРО ЛИМИТ ─────────────────────────────────────────────\n";
if ($found) {
    foreach ($found as $f) echo "  ✔ $f\n";
    echo "\nЕсть данные о квоте — можно показывать остаток в админке.\n";
} else {
    echo "  Ничего не найдено — API не сообщает остаток тарифа в заголовках.\n";
    echo "  Значит расход считаем сами (свой лог запросов) и уточняем правила у Tradesoft.\n";
}

echo "\n── НАЧАЛО ТЕЛА ОТВЕТА (500 символов) ───────────────────────────────\n";
echo mb_substr(trim($body), 0, 500) . "\n";
