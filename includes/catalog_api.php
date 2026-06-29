<?php
/**
 * Внешний каталог запчастей PartsAPI (метод getPartsbyVIN) — turnkey-ready.
 *
 * Как устроен PartsAPI:
 *   • Единого «дай все запчасти по VIN» нет. Запчасти выдаются по ТОВАРНЫМ
 *     ГРУППАМ: getPartsbyVIN(vin, type, cat) → запчасти одной группы `cat`.
 *   • Чтобы собрать каталог — перебираем группы (справочник в partsapi_cats.php).
 *   • Ответ группы: [{group, name, parts:"БРЕНД|АРТИКУЛ", shortname}, …] — без цен.
 *
 * Поэтому здесь:
 *   • перебор групп с ограничением catalog_api_max_groups (демо-лимит ключа);
 *   • агрессивный серверный кэш по VIN (таблица partsapi_catalog_cache, 30 дней);
 *   • обогащение: если артикул есть в своём складе — подставляем цену/наличие/ссылку.
 *
 * Всё инертно, пока catalog_api_enabled=0 — сайт работает как прежде.
 */

require_once __DIR__ . '/partsapi_cats.php';

class CatalogApi
{
    private const CACHE_DAYS = 30;
    private const CACHE_VER  = 4;     // bump to invalidate stale cache after logic changes (v4: httpGet/cURL транспорт)
    private const EMPTY_TTL  = 3600;  // пустой результат держим в кэше только 1 час (потом пересбор)

    // Подтверждённые рабочие OEM-группы (оригинал). Для type=oem опрашиваем их
    // первыми — у оригинального каталога ID групп частично отличаются от
    // справочника неоригинала (cat=1191 подтверждён рабочим примером PartsAPI).
    private const OEM_CATS = [1191, 1192, 1193, 1190, 1200, 1100, 1000, 1300, 1400, 1500];

    // ── Public API ─────────────────────────────────────────────────────────

    public static function enabled(): bool
    {
        return getSetting('catalog_api_enabled', '0') === '1'
            && trim(getSetting('catalog_api_key', '')) !== '';
    }

    public static function hasKey(): bool
    {
        return trim(getSetting('catalog_api_key', '')) !== '';
    }

    /**
     * Полный каталог по VIN: перебор групп → нормализация → обогащение складом.
     * Returns ['items'=>[…], 'count'=>int, 'groups_scanned'=>int, 'from_cache'=>bool].
     *
     * Item: [
     *   'name','group','brand','part_number',
     *   'in_catalog'(bool),'part_id'(?int),'price'(?float),'stock'(?int),'url'(?string)
     * ]
     */
    public static function searchByVin(string $vin, bool $useCache = true): array
    {
        $vin = strtoupper(trim($vin));
        $empty = ['items' => [], 'count' => 0, 'groups_scanned' => 0, 'from_cache' => false];
        if (!self::enabled() || $vin === '') return $empty;

        if ($useCache) {
            $cached = self::cacheGet($vin);
            if ($cached !== null) { $cached['from_cache'] = true; return $cached; }
        }

        $type      = self::type();
        $maxGroups = self::maxGroups();
        $cats      = self::catList($maxGroups, $type);

        $items       = [];
        $seen        = [];   // brand|article dedupe
        $scanned     = 0;
        $errors      = 0;
        $rateLimited = false;
        foreach ($cats as $cat) {
            [$rows, $raw, $err] = self::fetchGroupRaw($vin, $type, $cat);
            $scanned++;
            if ($err) $errors++;
            // Лимит запросов с IP (демо-ключ PartsAPI, error_code 5000 / HTTP 401):
            // дальше перебирать бессмысленно — каждый запрос тоже упрётся в лимит.
            // Прерываемся сразу, чтобы не жечь и без того исчерпанную квоту.
            if (self::isRateLimit($raw)) { $rateLimited = true; break; }
            foreach ($rows as $r) {
                $key = mb_strtolower($r['brand'] . '|' . $r['part_number']);
                if ($r['part_number'] === '' || isset($seen[$key])) continue;
                $seen[$key] = true;
                $items[] = $r;
            }
            if (count($items) >= 300) break; // hard safety cap
        }

        $items  = self::enrichFromWarehouse($items);
        $result = ['items' => $items, 'count' => count($items), 'groups_scanned' => $scanned,
                   'errors' => $errors, 'rate_limited' => $rateLimited,
                   'type' => $type, 'from_cache' => false, 'v' => self::CACHE_VER];

        // Не кэшируем транзиентный сбой (все группы упали — лимит ключа/сеть),
        // иначе пустой результат «прилипнет». Пустой по факту (деталей нет) кэшируем
        // ненадолго (EMPTY_TTL), непустой — на 30 дней.
        $transient = $rateLimited || ($scanned > 0 && $errors === $scanned);
        if (!$transient) {
            self::cacheSet($vin, $result);
        }
        return $result;
    }

    /**
     * Каталог по ОДНОМУ узлу (товарной группе) — getPartsbyVIN(vin, type, cat).
     * Это реализует «клик по узлу» из дерева МАШИНА → УЗЕЛ → ДЕТАЛЬ: один клик =
     * один запрос к API (а не перебор десятков групп), что бережёт лимит ключа.
     * Returns ['items','count','cat','rate_limited','from_cache'].
     */
    public static function searchByVinCat(string $vin, int $cat, bool $useCache = true): array
    {
        $vin   = strtoupper(trim($vin));
        $empty = ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => false, 'from_cache' => false];
        if (!self::enabled() || $vin === '' || $cat <= 0) return $empty;

        $type     = self::type();
        $cacheKey = 'g:' . $vin . '#' . $type . '#' . $cat;
        if ($useCache) {
            $cached = self::kvGet($cacheKey);
            if ($cached !== null) { $cached['from_cache'] = true; return $cached; }
        }

        [$rows, $raw, $err] = self::fetchGroupRaw($vin, $type, $cat);
        if (self::isRateLimit($raw)) {
            return ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => true, 'from_cache' => false];
        }

        $items = []; $seen = [];
        foreach ($rows as $r) {
            $key = mb_strtolower($r['brand'] . '|' . $r['part_number']);
            if ($r['part_number'] === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $items[] = $r;
        }
        $items  = self::enrichFromWarehouse($items);
        $result = ['items' => $items, 'count' => count($items), 'cat' => $cat,
                   'rate_limited' => false, 'type' => $type, 'from_cache' => false, 'v' => self::CACHE_VER];

        if (!$err) self::kvSet($cacheKey, $result); // сбой запроса (сеть/лимит) не кэшируем
        return $result;
    }

    /**
     * МОСТИК цепочки: по номеру детали (№) получить аналоги-кроссы (getCrosses).
     * Возвращает нормализованный список [['brand','part_number'], …].
     * Именно по этим номерам потом ищем товар на своём складе (crossesWithWarehouse).
     */
    public static function getCrosses(string $article, string $brand = '', bool $useCache = true): array
    {
        $article = trim($article);
        $empty   = ['crosses' => [], 'count' => 0, 'rate_limited' => false, 'from_cache' => false];
        if (!self::enabled() || $article === '') return $empty;

        $cacheKey = 'cr:' . self::normArt($article);
        if ($useCache) {
            $cached = self::kvGet($cacheKey);
            if ($cached !== null) { $cached['from_cache'] = true; return $cached; }
        }

        $key = trim(getSetting('catalog_api_key', ''));
        // Имя параметра номера у getCrosses в доке не зафиксировано — посылаем
        // распространённые псевдонимы (art/article/number), лишние API игнорирует.
        $params = ['method' => 'getCrosses', 'key' => $key,
                   'art' => $article, 'article' => $article, 'number' => $article, 'format' => 'json'];
        if ($brand !== '') { $params['brand'] = $brand; $params['brend'] = $brand; }
        $url = self::base() . '?' . http_build_query($params);

        $res = httpGet($url, self::timeout(), ['Accept: application/json']);
        $raw = (string)$res['body'];
        if (self::isRateLimit($raw)) return ['crosses' => [], 'count' => 0, 'rate_limited' => true, 'from_cache' => false];
        if ($res['error'] !== '' || $raw === '' || $res['status'] >= 400) return $empty;

        $crosses = self::parseCrosses($raw);
        $seen = []; $uniq = [];
        foreach ($crosses as $c) {
            $k = self::normArt($c['part_number']);
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = true;
            $uniq[] = $c;
        }
        $result = ['crosses' => $uniq, 'count' => count($uniq), 'rate_limited' => false, 'from_cache' => false, 'v' => self::CACHE_VER];
        self::kvSet($cacheKey, $result);
        return $result;
    }

    /**
     * № → кроссы → МОИ товары: исходный номер + его кроссы, обогащённые складом
     * (цена/наличие/ссылка там, где артикул совпал). Звено 3 для аналогов.
     */
    public static function crossesWithWarehouse(string $article, string $brand = ''): array
    {
        $cr    = self::getCrosses($article, $brand);
        $cands = [['brand' => $brand, 'part_number' => $article, 'is_original' => true]];
        foreach ($cr['crosses'] as $c) {
            $cands[] = ['brand' => $c['brand'] ?? '', 'part_number' => $c['part_number'], 'is_original' => false];
        }

        $items = []; $seen = [];
        foreach ($cands as $c) {
            $k = self::normArt($c['part_number']);
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = true;
            $items[] = [
                'name'        => $c['part_number'],
                'group'       => '',
                'brand'       => $c['brand'],
                'part_number' => $c['part_number'],
                'is_original' => !empty($c['is_original']),
                'in_catalog'  => false,
                'part_id'     => null,
                'price'       => null,
                'stock'       => null,
                'url'         => null,
            ];
        }
        $items = self::enrichFromWarehouse($items);
        return ['items' => $items, 'count' => count($items),
                'rate_limited' => $cr['rate_limited'], 'from_cache' => $cr['from_cache'] ?? false];
    }

    /**
     * Узлы для дерева каталога. Returns [['cat'=>int,'name'=>string], …].
     *
     * Приоритет: настройка «OEM-узлы» (строки «ID=Название»). Если не задана —
     * курируемый дефолтный набор ходовых узлов (реальные cat-ID из справочника
     * PartsAPI с короткими понятными именами), чтобы дерево сразу выглядело
     * полным, а не из одного пункта. На своём ключе можно переопределить точными
     * OEM-узлами в админке.
     */
    private const DEFAULT_NODES = [
        [54, 'Тормоза'],            [34, 'Подвеска'],       [12, 'Рулевое управление'],
        [56, 'Охлаждение'],         [183, 'Кондиционер'],   [683, 'Зажигание'],
        [7, 'Двигатель'],           [8, 'Впуск воздуха'],   [9, 'Топливная система'],
        [1, 'Аккумулятор'],         [4, 'Генератор'],       [14, 'Освещение'],
        [26, 'Выхлоп'],             [20, 'Кузов'],
    ];

    public static function oemNodes(): array
    {
        $raw   = trim(getSetting('catalog_api_oem_nodes', ''));
        $nodes = [];
        if ($raw !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '=') === false) continue;
                [$id, $name] = array_map('trim', explode('=', $line, 2));
                if (ctype_digit($id) && $name !== '') $nodes[] = ['cat' => (int)$id, 'name' => $name];
            }
        }
        if ($nodes) return $nodes;
        // Курируемый дефолт — чтобы дерево узлов выглядело полным без настройки.
        $out = [];
        foreach (self::DEFAULT_NODES as [$cat, $name]) $out[] = ['cat' => $cat, 'name' => $name];
        return $out;
    }

    /**
     * Тест соединения: декод + перебор небольшого числа групп.
     * ['ok','message','count','sample'].
     */
    public static function testConnection(string $vin = ''): array
    {
        if (!self::hasKey()) {
            return ['ok' => false, 'message' => 'API-ключ каталога не задан.', 'count' => 0, 'sample' => ''];
        }
        $vin = strtoupper(trim($vin)) ?: 'Z8TXLCW6WCM902224'; // VIN из доки PartsAPI

        // Пробуем несколько первых групп выбранного типа, не трогая кэш.
        $type = self::type();
        $cats = array_slice(self::catList(0, $type), 0, 8);
        $hits = []; $rawSample = ''; $rateLimited = false;
        foreach ($cats as $cat) {
            [$rows, $raw, ] = self::fetchGroupRaw($vin, $type, $cat);
            if ($rawSample === '' && $raw !== '') $rawSample = $raw;
            if (self::isRateLimit($raw)) { $rateLimited = true; break; }
            foreach ($rows as $r) $hits[] = $r;
            if (count($hits) >= 5) break;
        }

        if ($hits) {
            return [
                'ok'      => true,
                'message' => 'Соединение успешно. Пример: найдено ' . count($hits) . ' позиций в первых группах.',
                'count'   => count($hits),
                'sample'  => mb_substr($rawSample, 0, 600),
            ];
        }
        if ($rateLimited) {
            return [
                'ok'      => false,
                'message' => 'Соединение есть, ключ принят, но превышен лимит запросов с IP сервера '
                           . '(демо-ключ PartsAPI ≈ 50/сутки). Дождитесь сброса или подключите платный тариф.',
                'count'   => 0,
                'sample'  => mb_substr($rawSample, 0, 600),
            ];
        }
        return [
            'ok'      => false,
            'message' => 'Ключ принят, но запчасти не вернулись. Проверьте тариф/лимит ключа или попробуйте другой VIN.',
            'count'   => 0,
            'sample'  => mb_substr($rawSample, 0, 600),
        ];
    }

    /**
     * Лимит запросов PartsAPI: error_code 5000 / HTTP 401
     * («Exceeded the number of requests from the current IP address»).
     * Демо-ключ ограничен ≈50 запросами в сутки на IP.
     */
    private static function isRateLimit(string $raw): bool
    {
        if ($raw === '') return false;
        if (stripos($raw, 'Exceeded the number of requests') !== false) return true;
        $j = json_decode($raw, true);
        return is_array($j) && (int)($j['error_code'] ?? 0) === 5000;
    }

    // ── Settings helpers ────────────────────────────────────────────────────

    private static function type(): string
    {
        // PartsAPI getPartsbyVIN принимает только 'oem' (оригинал) или '' (неоригинал).
        // Значение 'all' (и любое другое) API НЕ поддерживает — отвечает пустым списком,
        // поэтому жёстко приводим к допустимому: всё некорректное → 'oem'.
        $t = trim(getSetting('catalog_api_type', 'oem'));
        return in_array($t, ['oem', ''], true) ? $t : 'oem';
    }

    private static function maxGroups(): int
    {
        $v = (int)getSetting('catalog_api_max_groups', '25');
        return $v < 0 ? 25 : $v; // 0 = все
    }

    private static function base(): string
    {
        $b = trim(getSetting('catalog_api_base', 'https://api.partsapi.ru/'));
        return $b !== '' ? $b : 'https://api.partsapi.ru/';
    }

    private static function timeout(): int
    {
        $t = (int)getSetting('catalog_api_timeout', '12');
        return ($t < 2 || $t > 30) ? 12 : $t;
    }

    /**
     * Список групп для перебора. Для оригинала (oem) подтверждённые OEM-группы идут
     * первыми, затем ходовые и остальные из справочника. 0 = все.
     */
    private static function catList(int $max, string $type = 'oem'): array
    {
        $oem     = ($type === 'oem') ? self::OEM_CATS : [];
        $popular = defined('PARTSAPI_POPULAR') ? PARTSAPI_POPULAR : [];
        $all     = defined('PARTSAPI_CATS') ? array_keys(PARTSAPI_CATS) : [];
        $ordered = array_values(array_unique(array_merge($oem, $popular, $all)));
        if ($max > 0 && count($ordered) > $max) {
            $ordered = array_slice($ordered, 0, $max);
        }
        return $ordered;
    }

    private static function catName(int $cat): string
    {
        return (defined('PARTSAPI_CATS') && isset(PARTSAPI_CATS[$cat])) ? PARTSAPI_CATS[$cat] : '';
    }

    // ── HTTP + parsing ───────────────────────────────────────────────────────

    /** Один запрос группы → нормализованные позиции. */
    private static function fetchGroup(string $vin, string $type, int $cat): array
    {
        return self::fetchGroupRaw($vin, $type, $cat)[0];
    }

    /**
     * Запрос группы. Возвращает [items, rawBody, isError]:
     *  - isError=true при сетевой ошибке/таймауте/ошибочной обёртке/лимите ключа.
     *  Это позволяет отличить «пусто, потому что нет деталей» от «пусто, потому
     *  что запрос не прошёл» (лимит, сеть) и не кэшировать сбой как факт.
     */
    private static function fetchGroupRaw(string $vin, string $type, int $cat): array
    {
        $key = trim(getSetting('catalog_api_key', ''));
        $url = self::base() . '?' . http_build_query([
            'method' => 'getPartsbyVIN',
            'key'    => $key,
            'vin'    => $vin,
            'type'   => $type,
            'cat'    => $cat,
            'format' => 'json',
        ]);

        // Через общий httpGet: cURL приоритетно, иначе file_get_contents.
        // На shared-хостинге прямой file_get_contents по HTTPS часто молча падает.
        $res  = httpGet($url, self::timeout(), ['Accept: application/json']);
        $raw  = $res['body'];
        $http = $res['status'];
        if ($res['error'] !== '' || $raw === '' || $http >= 400) return [[], (string)$raw, true];

        $json = json_decode($raw, true);
        if (!is_array($json)) return [[], $raw, true];

        // Ошибочные обёртки {"error_code":…} / {"status":…} → сбой запроса.
        if (isset($json['error_code']) || (isset($json['status']) && !isset($json[0]))) {
            return [[], $raw, true];
        }

        $catName = self::catName($cat);
        $items = [];
        foreach ($json as $row) {
            if (!is_array($row)) continue;
            $partsStr = (string)($row['parts'] ?? '');
            if ($partsStr === '') continue;

            $name  = trim((string)($row['shortname'] ?? $row['name'] ?? ''));
            $group = trim((string)($row['group'] ?? $catName));

            // Реальный формат PartsAPI: "БРЕНД|АРТИКУЛ,БРЕНД|АРТИКУЛ,…" —
            // несколько вариантов (бренд+артикул) одной детали через запятую,
            // внутри пары разделитель «|». Разворачиваем в отдельные позиции.
            foreach (explode(',', $partsStr) as $pair) {
                $seg = explode('|', $pair);
                if (count($seg) < 2) continue;
                $brand   = trim($seg[0]);
                $article = trim($seg[1]);
                if ($brand === '' || $article === '') continue;

                $items[] = [
                    'name'        => $name !== '' ? $name : $article,
                    'group'       => $group,
                    'brand'       => $brand,
                    'part_number' => $article,
                    'in_catalog'  => false,
                    'part_id'     => null,
                    'price'       => null,
                    'stock'       => null,
                    'url'         => null,
                ];
            }
        }
        return [$items, $raw, false];
    }

    /**
     * Разбор ответа getCrosses. Формат у PartsAPI документирован нечётко, поэтому
     * парсим устойчиво: массив строк «БРЕНД|АРТИКУЛ», массив объектов с ключами
     * бренда/артикула (англ. или транслит), вложенные {data:…}/{array:…}.
     */
    private static function parseCrosses(string $raw): array
    {
        $json = json_decode($raw, true);
        if (!is_array($json)) return [];
        if (isset($json['data'])  && is_array($json['data']))  $json = $json['data'];
        if (isset($json['array']) && is_array($json['array'])) $json = $json['array'];

        $out = [];
        foreach ($json as $row) {
            // Строка «БРЕНД|АРТИКУЛ,БРЕНД|АРТИКУЛ» или просто «АРТИКУЛ».
            if (is_string($row)) {
                foreach (explode(',', $row) as $pair) {
                    $seg = explode('|', $pair);
                    if (count($seg) >= 2) {
                        $out[] = ['brand' => trim($seg[0]), 'part_number' => trim($seg[1])];
                    } elseif (trim($pair) !== '') {
                        $out[] = ['brand' => '', 'part_number' => trim($pair)];
                    }
                }
                continue;
            }
            if (!is_array($row)) continue;

            // Вложенное поле parts:"БРЕНД|АРТИКУЛ" как в getPartsbyVIN.
            if (isset($row['parts']) && is_string($row['parts']) && $row['parts'] !== '') {
                foreach (explode(',', $row['parts']) as $pair) {
                    $seg = explode('|', $pair);
                    if (count($seg) >= 2) $out[] = ['brand' => trim($seg[0]), 'part_number' => trim($seg[1])];
                }
                continue;
            }

            $brand = '';
            foreach (['brand', 'brend', 'marka', 'proizvoditel', 'manufacturer', 'make'] as $k) {
                if (isset($row[$k]) && trim((string)$row[$k]) !== '') { $brand = trim((string)$row[$k]); break; }
            }
            $art = '';
            foreach (['article', 'artikul', 'oe', 'oem', 'code', 'number', 'part_number', 'partnumber', 'nomer', 'kod'] as $k) {
                if (isset($row[$k]) && trim((string)$row[$k]) !== '') { $art = trim((string)$row[$k]); break; }
            }
            if ($art !== '') $out[] = ['brand' => $brand, 'part_number' => $art];
        }
        return $out;
    }

    // ── Warehouse enrichment ─────────────────────────────────────────────────

    /**
     * Публичная обёртка обогащения складом — чтобы другие адаптеры (Mock, будущий
     * UMAPI/Laximo) использовали ТУ ЖЕ логику сопоставления артикулов со своим
     * складом. На Этапе 3 вынесется в отдельный PriceProvider.
     */
    public static function enrichItemsFromWarehouse(array $items): array
    {
        return self::enrichFromWarehouse($items);
    }

    /** Подставить цену/наличие/ссылку из своего склада там, где артикул совпал. */
    private static function enrichFromWarehouse(array $items): array
    {
        if (!$items) return $items;
        try {
            $db = getDB();

            // Соберём оригинальные и нормализованные артикулы для поиска.
            $articles = [];
            foreach ($items as $it) {
                $a = $it['part_number'];
                if ($a !== '') { $articles[$a] = true; }
            }
            if (!$articles) return $items;

            $list = array_keys($articles);
            $ph   = implode(',', array_fill(0, count($list), '?'));
            $stmt = $db->prepare(
                "SELECT id, part_number, price, stock, images
                   FROM parts
                  WHERE is_active = 1 AND part_number IN ($ph)"
            );
            $stmt->execute($list);

            // Индекс по нормализованному номеру.
            $map = [];
            foreach ($stmt->fetchAll() as $p) {
                $map[self::normArt($p['part_number'])] = $p;
            }
            if (!$map) return $items;

            foreach ($items as &$it) {
                $hit = $map[self::normArt($it['part_number'])] ?? null;
                if ($hit) {
                    $it['in_catalog'] = true;
                    $it['part_id']    = (int)$hit['id'];
                    $it['price']      = (float)$hit['price'];
                    $it['stock']      = (int)$hit['stock'];
                    $it['url']        = partUrl((int)$hit['id'], $hit['name'] ?? '');
                }
            }
            unset($it);
        } catch (Exception $e) { /* без обогащения — не критично */ }
        return $items;
    }

    private static function normArt(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }

    // ── Cache (runtime-migrated table) ───────────────────────────────────────

    private static function ensureCacheSchema(): void
    {
        static $done = false;
        if ($done) return;
        try {
            getDB()->exec(
                "CREATE TABLE IF NOT EXISTS partsapi_catalog_cache (
                    vin VARCHAR(20) NOT NULL PRIMARY KEY,
                    result MEDIUMTEXT NOT NULL,
                    cached_at DATETIME NOT NULL
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $done = true;
        } catch (Exception $e) { /* кэш необязателен */ }
    }

    private static function cacheGet(string $vin): ?array
    {
        self::ensureCacheSchema();
        try {
            $st = getDB()->prepare(
                "SELECT result, cached_at FROM partsapi_catalog_cache
                  WHERE vin = ? AND cached_at > DATE_SUB(NOW(), INTERVAL " . self::CACHE_DAYS . " DAY)"
            );
            $st->execute([$vin]);
            $row = $st->fetch();
            if (!$row) return null;

            $data = json_decode($row['result'], true);
            if (!is_array($data)) return null;
            // Игнорируем кэш старой версии логики.
            if (($data['v'] ?? 0) !== self::CACHE_VER) return null;
            // Игнорируем кэш, собранный для другого типа запчастей (oem/неоригинал).
            if (($data['type'] ?? null) !== self::type()) return null;
            // Пустой результат не считаем «вечным»: держим только EMPTY_TTL, потом пересбор.
            if ((int)($data['count'] ?? 0) === 0
                && (time() - strtotime($row['cached_at'])) > self::EMPTY_TTL) {
                return null;
            }
            return $data;
        } catch (Exception $e) { return null; }
    }

    private static function cacheSet(string $vin, array $data): void
    {
        self::ensureCacheSchema();
        try {
            getDB()->prepare(
                "INSERT INTO partsapi_catalog_cache (vin, result, cached_at)
                 VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE result = VALUES(result), cached_at = NOW()"
            )->execute([$vin, json_encode($data, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) { /* ignore */ }
    }

    public static function clearCache(): void
    {
        try { getDB()->exec("DELETE FROM partsapi_catalog_cache"); } catch (Exception $e) {}
        try { getDB()->exec("DELETE FROM partsapi_kv_cache"); } catch (Exception $e) {}
    }

    // ── Generic key→value cache (per-node groups «g:…» и кроссы «cr:…») ───────
    // Отдельная таблица, т.к. ключ длиннее 20-символьного PK partsapi_catalog_cache.

    private static function ensureKvSchema(): void
    {
        static $done = false;
        if ($done) return;
        try {
            getDB()->exec(
                "CREATE TABLE IF NOT EXISTS partsapi_kv_cache (
                    k VARCHAR(96) NOT NULL PRIMARY KEY,
                    result MEDIUMTEXT NOT NULL,
                    cached_at DATETIME NOT NULL
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $done = true;
        } catch (Exception $e) { /* кэш необязателен */ }
    }

    private static function kvGet(string $k): ?array
    {
        self::ensureKvSchema();
        try {
            $st = getDB()->prepare(
                "SELECT result, cached_at FROM partsapi_kv_cache
                  WHERE k = ? AND cached_at > DATE_SUB(NOW(), INTERVAL " . self::CACHE_DAYS . " DAY)"
            );
            $st->execute([$k]);
            $row = $st->fetch();
            if (!$row) return null;
            $data = json_decode($row['result'], true);
            if (!is_array($data) || ($data['v'] ?? 0) !== self::CACHE_VER) return null;
            // Пустой результат держим только EMPTY_TTL (потом пересбор).
            if ((int)($data['count'] ?? 0) === 0
                && (time() - strtotime($row['cached_at'])) > self::EMPTY_TTL) {
                return null;
            }
            return $data;
        } catch (Exception $e) { return null; }
    }

    private static function kvSet(string $k, array $data): void
    {
        self::ensureKvSchema();
        try {
            getDB()->prepare(
                "INSERT INTO partsapi_kv_cache (k, result, cached_at)
                 VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE result = VALUES(result), cached_at = NOW()"
            )->execute([$k, json_encode($data, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) { /* ignore */ }
    }
}
