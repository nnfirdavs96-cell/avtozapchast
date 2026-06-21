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

        $items   = [];
        $seen    = [];   // brand|article dedupe
        $scanned = 0;
        $errors  = 0;
        foreach ($cats as $cat) {
            [$rows, , $err] = self::fetchGroupRaw($vin, $type, $cat);
            $scanned++;
            if ($err) $errors++;
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
                   'errors' => $errors, 'type' => $type, 'from_cache' => false, 'v' => self::CACHE_VER];

        // Не кэшируем транзиентный сбой (все группы упали — лимит ключа/сеть),
        // иначе пустой результат «прилипнет». Пустой по факту (деталей нет) кэшируем
        // ненадолго (EMPTY_TTL), непустой — на 30 дней.
        $transient = ($scanned > 0 && $errors === $scanned);
        if (!$transient) {
            self::cacheSet($vin, $result);
        }
        return $result;
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
        $hits = []; $rawSample = '';
        foreach ($cats as $cat) {
            [$rows, $raw, ] = self::fetchGroupRaw($vin, $type, $cat);
            if ($rawSample === '' && $raw !== '') $rawSample = $raw;
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
        return [
            'ok'      => false,
            'message' => 'Ключ принят, но запчасти не вернулись. Проверьте тариф/лимит ключа или попробуйте другой VIN.',
            'count'   => 0,
            'sample'  => mb_substr($rawSample, 0, 600),
        ];
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

    // ── Warehouse enrichment ─────────────────────────────────────────────────

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
                    $it['url']        = APP_URL . '/catalog/part.php?id=' . (int)$hit['id'];
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
    }
}
