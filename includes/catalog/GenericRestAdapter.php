<?php
/**
 * Универсальный REST-адаптер (Этап 2). Исполняет ПРОФИЛЬ провайдера (см.
 * CatalogProfiles.php): строит запросы по шаблонам, ходит через httpGet(),
 * разбирает ответ по маппингу полей → отдаёт наш единый формат item.
 *
 * Благодаря ему подключение нового REST/JSON-сервиса = добавить профиль в админке,
 * без единой строки PHP. Фронт, корзина и слой цен не меняются.
 *
 * Логика разбора вынесена в чистые статические методы (buildUrl/getByPath/
 * parseParts) — они тестируются без сети.
 */
require_once __DIR__ . '/Provider.php';
require_once __DIR__ . '/CatalogProfiles.php';
require_once __DIR__ . '/../catalog_api.php';

class GenericRestAdapter implements CatalogProvider
{
    private array $p;

    public function __construct(array $profile)
    {
        $this->p = $profile;
    }

    public function id(): string    { return (string)($this->p['id'] ?? 'generic'); }
    public function title(): string { return (string)($this->p['title'] ?? $this->id()); }

    public function enabled(): bool
    {
        return getSetting('catalog_api_enabled', '0') === '1' && $this->hasKey();
    }

    public function hasKey(): bool
    {
        return trim(getSetting('catalog_api_key', '')) !== '';
    }

    // ── Дерево узлов ─────────────────────────────────────────────────────────

    public function oemNodes(): array
    {
        $lines = $this->p['nodes'] ?? null;
        if (!is_array($lines) || !$lines) {
            // фолбэк — общая настройка дерева
            $raw   = trim(getSetting('catalog_api_oem_nodes', ''));
            $lines = $raw !== '' ? preg_split('/\r\n|\r|\n/', $raw) : [];
        }
        $nodes = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '=') === false) continue;
            [$id, $name] = array_map('trim', explode('=', $line, 2));
            if (ctype_digit($id) && $name !== '') $nodes[] = ['cat' => (int)$id, 'name' => $name];
        }
        return $nodes;
    }

    // ── Каталог по узлу / по VIN ────────────────────────────────────────────

    public function searchByVinCat(string $vin, int $cat, bool $useCache = true): array
    {
        $vin = strtoupper(trim($vin));
        $empty = ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || $vin === '' || $cat <= 0) return $empty;

        [$items, $err, $rl] = $this->fetchParts($vin, $cat);
        if ($rl)  return ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => true, 'from_cache' => false];
        $items = CatalogApi::enrichItemsFromWarehouse($items);
        return ['items' => $items, 'count' => count($items), 'cat' => $cat,
                'rate_limited' => false, 'type' => $this->id(), 'from_cache' => false];
    }

    public function searchByVin(string $vin, bool $useCache = true): array
    {
        $vin = strtoupper(trim($vin));
        $empty = ['items' => [], 'count' => 0, 'groups_scanned' => 0, 'errors' => 0,
                  'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || $vin === '') return $empty;

        $items = []; $seen = []; $scanned = 0; $errors = 0; $rl = false;
        foreach ($this->oemNodes() as $n) {
            [$rows, $err, $rateLimited] = $this->fetchParts($vin, (int)$n['cat']);
            $scanned++;
            if ($err) $errors++;
            if ($rateLimited) { $rl = true; break; }
            foreach ($rows as $r) {
                $k = mb_strtolower($r['brand'] . '|' . $r['part_number']);
                if ($r['part_number'] === '' || isset($seen[$k])) continue;
                $seen[$k] = true; $items[] = $r;
            }
            if (count($items) >= 300) break;
        }
        $items = CatalogApi::enrichItemsFromWarehouse($items);
        return ['items' => $items, 'count' => count($items), 'groups_scanned' => $scanned,
                'errors' => $errors, 'rate_limited' => $rl, 'type' => $this->id(), 'from_cache' => false];
    }

    public function crossesWithWarehouse(string $article, string $brand = ''): array
    {
        $article = trim($article);
        $empty = ['items' => [], 'count' => 0, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || $article === '') return $empty;

        $tpl = $this->p['endpoints']['crosses'] ?? '';
        $cands = [['brand' => $brand, 'part_number' => $article, 'is_original' => true]];
        $rl = false;
        if ($tpl !== '') {
            $url = self::buildUrl($this->base(), $tpl, [
                'ART' => $article, 'BRAND' => $brand, 'KEY' => $this->key(),
                'VIN' => '', 'CAT' => '', 'TYPE' => '',
            ]);
            [$json, , $rl] = $this->httpJson($url);
            if (!$rl && is_array($json)) {
                foreach (self::parseParts($json, $this->p['parse'] ?? []) as $r) {
                    $cands[] = ['brand' => $r['brand'], 'part_number' => $r['part_number'], 'is_original' => false];
                }
            }
        }
        $items = []; $seen = [];
        foreach ($cands as $c) {
            $k = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c['part_number']));
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = true;
            $items[] = ['name' => $c['part_number'], 'group' => '', 'brand' => $c['brand'],
                        'part_number' => $c['part_number'], 'is_original' => $c['is_original'],
                        'in_catalog' => false, 'part_id' => null, 'price' => null, 'stock' => null, 'url' => null];
        }
        $items = CatalogApi::enrichItemsFromWarehouse($items);
        return ['items' => $items, 'count' => count($items), 'rate_limited' => $rl, 'from_cache' => false];
    }

    public function testConnection(string $vin = ''): array
    {
        if (!$this->hasKey()) {
            return ['ok' => false, 'message' => 'API-ключ каталога не задан.', 'count' => 0, 'sample' => ''];
        }
        $vin   = strtoupper(trim($vin)) ?: 'Z8TXLCW6WCM902224';
        $nodes = $this->oemNodes();
        $cat   = $nodes ? (int)$nodes[0]['cat'] : 1;

        $url = self::buildUrl($this->base(), $this->p['endpoints']['parts'] ?? '', [
            'VIN' => $vin, 'CAT' => $cat, 'KEY' => $this->key(), 'TYPE' => '', 'ART' => '', 'BRAND' => '',
        ]);
        $res    = httpGet($url, $this->timeout(), ['Accept: application/json']);
        $sample = mb_substr((string)$res['body'], 0, 600);

        if ($res['error'] !== '' || $res['status'] >= 400) {
            return ['ok' => false, 'count' => 0, 'sample' => $sample,
                    'message' => 'Сервис не ответил (HTTP ' . $res['status'] . '). ' . ($res['error'] ?: 'Проверьте URL/ключ профиля.')];
        }
        $json  = json_decode((string)$res['body'], true);
        $items = is_array($json) ? self::parseParts($json, $this->p['parse'] ?? []) : [];
        if ($items) {
            return ['ok' => true, 'count' => count($items), 'sample' => $sample,
                    'message' => 'Соединение успешно. Получено ' . count($items) . ' позиций в первой группе.'];
        }
        return ['ok' => false, 'count' => 0, 'sample' => $sample,
                'message' => 'Ответ получен, но детали не распознаны. Проверьте маппинг полей в профиле (parse).'];
    }

    public function clearCache(): void { /* generic пока без серверного кэша */ }

    // ── Внутреннее: HTTP + конфиг ───────────────────────────────────────────

    private function base(): string    { return rtrim((string)($this->p['base_url'] ?? ''), '/'); }
    private function key(): string     { return trim(getSetting('catalog_api_key', '')); }
    private function timeout(): int    { $t = (int)($this->p['timeout'] ?? 12); return ($t < 2 || $t > 30) ? 12 : $t; }

    /** Запрос деталей одной группы → [items[], isError, rateLimited]. */
    private function fetchParts(string $vin, int $cat): array
    {
        $url = self::buildUrl($this->base(), $this->p['endpoints']['parts'] ?? '', [
            'VIN' => $vin, 'CAT' => $cat, 'KEY' => $this->key(),
            'TYPE' => trim(getSetting('catalog_api_type', 'oem')), 'ART' => '', 'BRAND' => '',
        ]);
        [$json, $err, $rl] = $this->httpJson($url);
        if ($rl)  return [[], true, true];
        if ($err) return [[], true, false];
        return [self::parseParts($json, $this->p['parse'] ?? []), false, false];
    }

    /** httpGet + декод + заголовок авторизации/лимит → [decodedJsonOrNull, isError, rateLimited]. */
    private function httpJson(string $url): array
    {
        $headers = ['Accept: application/json'];
        $auth = $this->p['auth'] ?? 'query';
        if ($auth === 'bearer')      $headers[] = 'Authorization: Bearer ' . $this->key();
        elseif ($auth === 'header')  $headers[] = (($this->p['key_header'] ?? 'X-Api-Key') . ': ' . $this->key());

        $res = httpGet($url, $this->timeout(), $headers);
        $raw = (string)$res['body'];
        if (self::isRateLimit($raw)) return [null, true, true];
        if ($res['error'] !== '' || $raw === '' || $res['status'] >= 400) return [null, true, false];
        $json = json_decode($raw, true);
        if (!is_array($json)) return [null, true, false];
        return [$json, false, false];
    }

    // ── Чистые функции разбора (тестируются без сети) ────────────────────────

    /** Подстановка плейсхолдеров {VIN}{KEY}{CAT}{ART}{BRAND}{TYPE} (регистр игнор.). */
    public static function buildUrl(string $base, string $tpl, array $vars): string
    {
        $search = []; $replace = [];
        foreach ($vars as $k => $v) {
            $search[]  = '{' . strtoupper($k) . '}';
            $replace[] = rawurlencode((string)$v);
        }
        $path = str_ireplace($search, $replace, $tpl);
        return $base . $path;
    }

    /** Достать значение по точечному пути ('' = сам $data). */
    public static function getByPath($data, string $path)
    {
        if ($path === '') return $data;
        foreach (explode('.', $path) as $seg) {
            if (is_array($data) && array_key_exists($seg, $data)) $data = $data[$seg];
            else return null;
        }
        return $data;
    }

    /**
     * Разбор ответа в позиции [['name','group','brand','part_number', …], …]
     * по правилам parse-секции профиля. Поддерживает два режима:
     *   pairs   — у элемента строка "БРЕНД|АРТ,БРЕНД|АРТ" в parts_field;
     *   objects — каждый элемент = одна деталь (brand_field/article_field).
     */
    public static function parseParts($json, array $parse): array
    {
        $list = self::getByPath($json, (string)($parse['list_path'] ?? ''));
        if (!is_array($list)) return [];
        // одиночный объект → обернуть как список из одного элемента
        $rows = (array_keys($list) === range(0, count($list) - 1)) ? $list : [$list];

        $mode  = $parse['mode'] ?? 'pairs';
        $nameF = $parse['name_field'] ?? 'shortname';
        $grpF  = $parse['group_field'] ?? 'group';
        $out   = [];

        foreach ($rows as $el) {
            if (!is_array($el)) continue;
            $name  = trim((string)(self::getByPath($el, $nameF) ?? ''));
            $group = trim((string)(self::getByPath($el, $grpF) ?? ''));

            if ($mode === 'objects') {
                $brand = trim((string)(self::getByPath($el, $parse['brand_field'] ?? 'brand') ?? ''));
                $art   = trim((string)(self::getByPath($el, $parse['article_field'] ?? 'article') ?? ''));
                if ($art === '') continue;
                $out[] = self::item($name ?: $art, $group, $brand, $art);
                continue;
            }
            // pairs
            $partsStr = (string)(self::getByPath($el, $parse['parts_field'] ?? 'parts') ?? '');
            if ($partsStr === '') continue;
            $psep = $parse['parts_sep'] ?? ',';
            $isep = $parse['pair_sep'] ?? '|';
            foreach (explode($psep, $partsStr) as $pair) {
                $seg = explode($isep, $pair);
                if (count($seg) < 2) continue;
                $brand = trim($seg[0]); $art = trim($seg[1]);
                if ($brand === '' || $art === '') continue;
                $out[] = self::item($name ?: $art, $group, $brand, $art);
            }
        }
        return $out;
    }

    private static function item(string $name, string $group, string $brand, string $art): array
    {
        return ['name' => $name, 'group' => $group, 'brand' => $brand, 'part_number' => $art,
                'in_catalog' => false, 'part_id' => null, 'price' => null, 'stock' => null, 'url' => null];
    }

    /** Лимит запросов (как у PartsAPI) — обобщённо. */
    private static function isRateLimit(string $raw): bool
    {
        if ($raw === '') return false;
        if (stripos($raw, 'Exceeded the number of requests') !== false) return true;
        $j = json_decode($raw, true);
        return is_array($j) && (int)($j['error_code'] ?? 0) === 5000;
    }
}
