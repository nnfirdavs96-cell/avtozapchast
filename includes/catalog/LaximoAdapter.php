<?php
/**
 * Laximo — оригинальные каталоги (OEM-схемы по VIN). Этап 4.
 *
 * Laximo НЕ описывается профилем GenericRestAdapter: это не простой REST, а HMAC-
 * подписанный шлюз `ec.api` с XML-ответами и цепочкой токенов `ssd`, переходящих
 * между шагами (FindVehicle → ListCategories/ListQuickGroups → ListUnits →
 * ListDetailByUnit). Поэтому — отдельный код-адаптер, написанный ЗАРАНЕЕ, чтобы
 * при покупке подписки клиенту осталось лишь ввести логин/секрет в админке.
 *
 * ── Статус ──────────────────────────────────────────────────────────────────
 * Подпись запроса и XML-разбор реализованы по схеме Laximo `ec.api`. Полностью
 * проверяется ТОЛЬКО на боевом аккаунте Laximo (логин + секретный ключ): без него
 * сервис отвечает ошибкой авторизации. Кнопка «Проверить соединение» в админке
 * выполняет реальный запрос и показывает сырой ответ — по нему на боевом ключе
 * донастраиваются коды каталогов и маппинг узлов (catalog_laximo_*).
 *
 * Доступы (site_settings):
 *   catalog_laximo_login   — OEM-логин аккаунта Laximo
 *   catalog_laximo_secret  — секретный ключ для HMAC-подписи
 */
require_once __DIR__ . '/Provider.php';
require_once __DIR__ . '/../catalog_api.php';

class LaximoAdapter implements CatalogProvider
{
    private const ENDPOINT = 'https://ws.laximo.ru/ec.api/';

    public function id(): string    { return 'laximo'; }
    public function title(): string { return 'Laximo (оригинал)'; }

    public function enabled(): bool
    {
        return getSetting('catalog_api_enabled', '0') === '1' && $this->hasKey();
    }

    public function hasKey(): bool
    {
        return trim(getSetting('catalog_laximo_login', '')) !== ''
            && trim(getSetting('catalog_laximo_secret', '')) !== '';
    }

    // ── Дерево узлов ─────────────────────────────────────────────────────────

    public function oemNodes(): array
    {
        // Узлы Laximo зависят от каталога авто и токена ssd (получаются после
        // FindVehicle). До боевой проверки используем общий справочник узлов из
        // настройки, чтобы дерево на странице VIN было кликабельным.
        $raw   = trim(getSetting('catalog_api_oem_nodes', ''));
        $nodes = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '=') === false) continue;
            [$id, $name] = array_map('trim', explode('=', $line, 2));
            if (ctype_digit($id) && $name !== '') $nodes[] = ['cat' => (int)$id, 'name' => $name];
        }
        return $nodes;
    }

    // ── Каталог / кроссы ─────────────────────────────────────────────────────

    public function searchByVinCat(string $vin, int $cat, bool $useCache = true): array
    {
        $empty = ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled()) return $empty;

        // TODO(боевой аккаунт): FindVehicle(VIN) → ssd → ListDetailByUnit(cat, ssd)
        // → разбор XML в позиции. Цепочка ssd проверяется на реальном ключе.
        $veh = $this->findVehicleByVin($vin);
        if ($veh === null) return $empty;

        // Каркас: до согласования формата деталей на боевом аккаунте возвращаем
        // пусто (без падений), чтобы остальной сайт работал штатно.
        return $empty;
    }

    public function searchByVin(string $vin, bool $useCache = true): array
    {
        $empty = ['items' => [], 'count' => 0, 'groups_scanned' => 0, 'errors' => 0,
                  'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled()) return $empty;
        $this->findVehicleByVin($vin); // прогреваем/проверяем доступ; детали — на боевом ключе
        return $empty;
    }

    public function crossesWithWarehouse(string $article, string $brand = ''): array
    {
        // Кроссы у Laximo — отдельная команда; реализуется при наличии аккаунта.
        $items = CatalogApi::enrichItemsFromWarehouse([[
            'name' => $article, 'group' => '', 'brand' => $brand, 'part_number' => $article,
            'is_original' => true, 'in_catalog' => false, 'part_id' => null,
            'price' => null, 'stock' => null, 'url' => null,
        ]]);
        return ['items' => $items, 'count' => count($items), 'rate_limited' => false, 'from_cache' => false];
    }

    public function testConnection(string $vin = ''): array
    {
        if (!$this->hasKey()) {
            return ['ok' => false, 'count' => 0, 'sample' => '',
                    'message' => 'Laximo: укажите логин и секретный ключ в настройках.'];
        }
        // Реальный запрос: список каталогов (минимальная команда, не требует ssd).
        [$body, $err] = $this->request('GetListCatalogs:Locale=ru_RU');
        $sample = mb_substr((string)$body, 0, 600);
        if ($err !== '') {
            return ['ok' => false, 'count' => 0, 'sample' => $sample,
                    'message' => 'Laximo: ошибка соединения — ' . $err];
        }
        if (stripos((string)$body, 'authoriz') !== false || stripos((string)$body, 'signature') !== false) {
            return ['ok' => false, 'count' => 0, 'sample' => $sample,
                    'message' => 'Laximo: доступ отклонён — проверьте логин/секрет.'];
        }
        if (stripos((string)$body, '<row') !== false || stripos((string)$body, 'GetListCatalogs') !== false) {
            return ['ok' => true, 'count' => 0, 'sample' => $sample,
                    'message' => 'Laximo: соединение и подпись работают. Каталоги получены — '
                               . 'дальше донастраиваются коды каталогов и маппинг узлов.'];
        }
        return ['ok' => false, 'count' => 0, 'sample' => $sample,
                'message' => 'Laximo: ответ получен, но не распознан. См. сырой ответ ниже.'];
    }

    public function clearCache(): void { /* нет серверного кэша на этапе каркаса */ }

    // ── Laximo ec.api: подпись + транспорт ───────────────────────────────────

    /** FindVehicle по VIN → ассоц. массив авто (или null). Каркас: вернёт null без боевого ключа. */
    private function findVehicleByVin(string $vin)
    {
        $vin = strtoupper(trim($vin));
        if ($vin === '') return null;
        [$body, $err] = $this->request("FindVehicle:VIN={$vin}|Localized=true");
        if ($err !== '' || $body === '') return null;
        $xml = @simplexml_load_string($body);
        if ($xml === false) return null;
        // Структура зависит от каталога; полный разбор согласуется на боевом аккаунте.
        return ['raw' => $body];
    }

    /**
     * Подписанный запрос к Laximo ec.api.
     * Подпись: base64( md5( request . secret, raw=true ) ). Логин и подпись —
     * в query, команда — в теле POST (поле request). Возврат [body, error].
     */
    private function request(string $command): array
    {
        $login  = trim(getSetting('catalog_laximo_login', ''));
        $secret = trim(getSetting('catalog_laximo_secret', ''));
        if ($login === '' || $secret === '') return ['', 'нет логина/секрета'];

        $signature = base64_encode(md5($command . $secret, true));
        $url = self::ENDPOINT . '?' . http_build_query(['oem' => $login, 'signature' => $signature]);

        if (!function_exists('curl_init')) {
            return ['', 'cURL не установлен'];
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['request' => $command]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/xml', 'Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) return ['', $err ?: 'нет ответа'];
        return [(string)$body, ''];
    }
}
