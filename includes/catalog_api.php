<?php
/**
 * External Catalog / VIN API integration (turnkey-ready).
 *
 * Goal: ship a site that ALREADY contains a working integration layer for an
 * external parts catalog + VIN decoder (e.g. PartsAPI / TecDoc). The client
 * buys a subscription later, pastes the API key in the admin panel, flips the
 * toggle — and the catalog goes live. Until then every method is inert and the
 * site behaves exactly as before (free local + NHTSA decode, own catalog).
 *
 * Design notes
 * ────────────
 *  • Provider-agnostic. The request URL is an admin-editable TEMPLATE with
 *    {VIN} and {KEY} placeholders, so the exact endpoint can be tuned from the
 *    panel without touching code (different providers differ in URL shape).
 *  • The key is ALSO sent as Authorization / X-Api-Key headers, covering both
 *    query-param and header auth styles.
 *  • Response parsing is defensive: it scans common field names used by
 *    PartsAPI / TecDoc / generic JSON catalogs and normalises to one shape.
 *  • Every public method degrades gracefully (returns [] / ok=false) and never
 *    throws into the page.
 *
 * Settings keys (site_settings):
 *   catalog_api_enabled   '0' | '1'      master toggle
 *   catalog_api_provider  'partsapi' | 'custom'
 *   catalog_api_url       template, e.g. https://api.partsapi.ru/?method=PartsByVIN&key={KEY}&vin={VIN}&format=json
 *   catalog_api_key       the secret key
 *   catalog_api_timeout   seconds (default 10)
 */
class CatalogApi
{
    /** Master switch: only true when toggled on AND a URL template exists. */
    public static function enabled(): bool
    {
        return getSetting('catalog_api_enabled', '0') === '1'
            && trim(getSetting('catalog_api_url', '')) !== '';
    }

    /** Whether a key has been supplied (used for status badges in admin). */
    public static function hasKey(): bool
    {
        return trim(getSetting('catalog_api_key', '')) !== '';
    }

    /**
     * Search the external catalog for parts matching a VIN.
     * Returns a list of normalised items or [] on any problem.
     *
     * Item shape: [
     *   'name' => string, 'part_number' => string, 'brand' => string,
     *   'price' => string|float|null, 'image' => string|null, 'url' => string|null,
     * ]
     */
    public static function searchByVin(string $vin): array
    {
        if (!self::enabled()) return [];
        $vin = strtoupper(trim($vin));
        if ($vin === '') return [];

        $resp = self::request($vin);
        if (!$resp['ok']) return [];

        $json = json_decode($resp['body'], true);
        if (!is_array($json)) return [];

        return self::normalizeParts($json);
    }

    /**
     * Test the configured endpoint with a sample/real VIN.
     * Returns ['ok'=>bool, 'http'=>int, 'message'=>string, 'count'=>int, 'sample'=>string].
     * Used by the admin "Проверить соединение" button.
     */
    public static function testConnection(string $vin = ''): array
    {
        $url = trim(getSetting('catalog_api_url', ''));
        if ($url === '') {
            return ['ok' => false, 'http' => 0, 'message' => 'URL каталога не задан.', 'count' => 0, 'sample' => ''];
        }
        $vin  = strtoupper(trim($vin)) ?: 'WBAWX31060PK42218'; // valid sample VIN
        $resp = self::request($vin);

        if (!$resp['ok']) {
            $msg = $resp['error'] !== ''
                ? $resp['error']
                : ('Сервер вернул HTTP ' . $resp['http'] . '.');
            return ['ok' => false, 'http' => $resp['http'], 'message' => $msg, 'count' => 0,
                    'sample' => mb_substr($resp['body'], 0, 600)];
        }

        $json  = json_decode($resp['body'], true);
        $items = is_array($json) ? self::normalizeParts($json) : [];
        $ok    = is_array($json);

        return [
            'ok'      => $ok,
            'http'    => $resp['http'],
            'message' => $ok
                ? ('Соединение успешно. Найдено позиций: ' . count($items) . '.')
                : 'Ответ получен, но это не JSON. Проверьте URL-шаблон и параметр format=json.',
            'count'   => count($items),
            'sample'  => mb_substr($resp['body'], 0, 600),
        ];
    }

    // ── Internal: HTTP ─────────────────────────────────────────────────────

    /**
     * Perform the catalog request for a VIN.
     * Returns ['ok'=>bool, 'http'=>int, 'body'=>string, 'error'=>string].
     */
    private static function request(string $vin): array
    {
        $template = trim(getSetting('catalog_api_url', ''));
        $key      = trim(getSetting('catalog_api_key', ''));
        $timeout  = (int)getSetting('catalog_api_timeout', '10');
        if ($timeout < 2 || $timeout > 30) $timeout = 10;
        if ($template === '') {
            return ['ok' => false, 'http' => 0, 'body' => '', 'error' => 'URL не задан.'];
        }

        $url = self::buildUrl($template, $vin, $key);

        $hdrs = array_filter([
            'Accept: application/json',
            $key !== '' ? "Authorization: Bearer {$key}" : '',
            $key !== '' ? "X-Api-Key: {$key}" : '',
        ]);

        $ctx = stream_context_create(['http' => [
            'method'        => 'GET',
            'timeout'       => $timeout,
            'ignore_errors' => true,
            'header'        => implode("\r\n", $hdrs),
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        $http = self::statusFromHeaders($http_response_header ?? []);

        if ($body === false) {
            return ['ok' => false, 'http' => $http, 'body' => '', 'error' => 'Не удалось соединиться с сервером каталога (таймаут или сеть).'];
        }
        if ($http >= 400) {
            return ['ok' => false, 'http' => $http, 'body' => (string)$body, 'error' => ''];
        }
        return ['ok' => true, 'http' => $http ?: 200, 'body' => (string)$body, 'error' => ''];
    }

    /** Replace {VIN}/{KEY} placeholders (case-insensitive) and URL-encode the VIN. */
    private static function buildUrl(string $template, string $vin, string $key): string
    {
        return str_ireplace(
            ['{VIN}', '{KEY}'],
            [rawurlencode($vin), rawurlencode($key)],
            $template
        );
    }

    /** Extract HTTP status code from $http_response_header. */
    private static function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    // ── Internal: response normalisation ───────────────────────────────────

    /**
     * Defensively pull a list of parts out of an arbitrary JSON catalog
     * response and normalise field names. Handles the common shapes used by
     * PartsAPI / TecDoc and generic catalogs.
     */
    private static function normalizeParts(array $json): array
    {
        $list = self::locatePartsList($json);
        if (!$list) return [];

        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            $name  = self::firstField($row, ['name', 'title', 'description', 'articleName',
                                             'genericArticleDescription', 'productName', 'partName']);
            $num   = self::firstField($row, ['part_number', 'articleNumber', 'number', 'oem',
                                             'code', 'partNumber', 'article', 'sku']);
            $brand = self::firstField($row, ['brand', 'brandName', 'supplier', 'mfrName',
                                             'manufacturer', 'brand_name']);
            $price = self::firstField($row, ['price', 'cost', 'amount', 'retailPrice']);
            $img   = self::firstField($row, ['image', 'img', 'imageUrl', 'picture', 'thumbnail']);
            $url   = self::firstField($row, ['url', 'link', 'href']);

            // Skip rows with no usable identity at all.
            if ($name === '' && $num === '') continue;

            $out[] = [
                'name'        => $name !== '' ? $name : $num,
                'part_number' => $num,
                'brand'       => $brand,
                'price'       => $price !== '' ? $price : null,
                'image'       => $img !== '' ? $img : null,
                'url'         => $url !== '' ? $url : null,
            ];
            if (count($out) >= 60) break; // safety cap
        }
        return $out;
    }

    /** Find the array of part rows inside the response, trying common wrappers. */
    private static function locatePartsList(array $json): array
    {
        // Already a plain list of objects?
        if (isset($json[0]) && is_array($json[0])) return $json;

        foreach (['parts', 'articles', 'data', 'result', 'results', 'items', 'list', 'rows'] as $k) {
            if (isset($json[$k]) && is_array($json[$k])) {
                $cand = $json[$k];
                if (isset($cand[0]) && is_array($cand[0])) return $cand;
                // Nested one level (e.g. data.parts)
                foreach ($cand as $v) {
                    if (is_array($v) && isset($v[0]) && is_array($v[0])) return $v;
                }
            }
        }
        return [];
    }

    /** Return the first non-empty value among candidate keys (case-insensitive). */
    private static function firstField(array $row, array $keys): string
    {
        // Build a lower-cased lookup once.
        $lower = [];
        foreach ($row as $k => $v) {
            if (is_scalar($v)) $lower[strtolower((string)$k)] = (string)$v;
        }
        foreach ($keys as $k) {
            $lk = strtolower($k);
            if (isset($lower[$lk]) && trim($lower[$lk]) !== '') {
                return trim($lower[$lk]);
            }
        }
        return '';
    }
}
