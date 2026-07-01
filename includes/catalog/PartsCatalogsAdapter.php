<?php
/**
 * Parts-Catalogs.com — оригинальные OEM-каталоги с ВИЗУАЛЬНЫМИ взрыв-схемами
 * (кликабельные номера-выноски). Отдельный код-адаптер (как Laximo), т.к. PC —
 * двухшаговый REST с токеном `criteria`, протянутым между вызовами:
 *   VIN → car/info → {carId, catalogId, criteria} → groups2 (узлы) → parts2 (схема+детали).
 *
 * Источник истины по формату — официальный open-source клиент alex-ello/pc-client-slim.
 *
 * Авторизация: заголовок `Authorization: <ключ>` (СЫРОЙ, без Bearer). Если боевой
 * ключ потребует `Bearer` — правится ОДНОЙ строкой в authHdr().
 *
 * Формат ответа parts2 (визуальная схема):
 *   img            — картинка взрыв-схемы (прямо в <img src>)
 *   imgDescription — подпись
 *   positions[]    — { number, coordinates:[x,y,w,h] } — кликабельные прямоугольники (px)
 *   partGroups[]   — { name, parts:[ { id, number(=OEM), name, positionNumber, url } ] }
 * Связь «точка ↔ деталь» = общий номер выноски (positions[].number == part.positionNumber).
 *
 * Доступы (site_settings):
 *   catalog_pc_key      — API-ключ (значение заголовка Authorization)
 *   catalog_pc_base     — база API (по умолчанию https://api.parts-catalogs.com/)
 *   catalog_pc_timeout  — таймаут, сек (по умолчанию 20)
 *   catalog_pc_schema   — показывать визуальные схемы ('1'/'0', по умолчанию '1')
 *
 * Кэш: общая таблица partsapi_kv_cache, префикс ключей 'pc:'. Тарификация PC — по
 * VIN/24ч, поэтому кэшируем агрессивно по VIN (24ч): повторные просмотры бесплатны.
 * Цену/наличие НЕ кэшируем в payload — обогащаем складом на чтении (сток live).
 */
require_once __DIR__ . '/Provider.php';
require_once __DIR__ . '/../catalog_api.php';

class PartsCatalogsAdapter implements CatalogProvider
{
    private const CACHE_VER = 1;
    private const TTL_CAR   = 86400;    // 24h  VIN→car (criteria живёт с кредитом VIN)
    private const TTL_DATA  = 86400;    // 24h  узлы / детали / схемы
    private const TTL_CATS  = 2592000;  // 30d  список каталогов

    public function id(): string    { return 'partspc'; }
    public function title(): string { return 'Parts-Catalogs (OEM)'; }

    public function enabled(): bool
    {
        return getSetting('catalog_api_enabled', '0') === '1' && $this->hasKey();
    }

    public function hasKey(): bool
    {
        return trim(getSetting('catalog_pc_key', '')) !== '';
    }

    // ── Транспорт + авторизация ──────────────────────────────────────────────

    private function base(): string
    {
        $b = trim(getSetting('catalog_pc_base', 'https://api.parts-catalogs.com/'));
        return rtrim($b !== '' ? $b : 'https://api.parts-catalogs.com/', '/') . '/';
    }

    private function key(): string { return trim(getSetting('catalog_pc_key', '')); }

    private function timeout(): int
    {
        $t = (int)getSetting('catalog_pc_timeout', '20');
        return ($t < 2 || $t > 60) ? 20 : $t;
    }

    /** Заголовок авторизации PC: сырой ключ (без Bearer). Единственная точка правки. */
    private function authHdr(): array
    {
        return ['Authorization: ' . $this->key(), 'Accept: application/json'];
    }

    /**
     * GET к PC. Возвращает [decoded(array|null), status(int), error(string), raw(string)].
     */
    private function get(string $path, array $query = []): array
    {
        $url = $this->base() . ltrim($path, '/');
        if ($query) $url .= '?' . http_build_query($query);
        $r   = httpGet($url, $this->timeout(), $this->authHdr());
        $raw = (string)($r['body'] ?? '');
        $st  = (int)($r['status'] ?? 0);
        $err = (string)($r['error'] ?? '');
        if ($err !== '' || $st >= 400 || $raw === '') return [null, $st, $err, $raw];
        $j = json_decode($raw, true);
        return [is_array($j) ? $j : null, $st, $err, $raw];
    }

    /** Признак исчерпания квоты/лимита (эвристика — уточним по боевому ответу). */
    private function isRateLimit(int $status, string $raw): bool
    {
        if ($status === 429) return true;
        if ($raw !== '' && stripos($raw, 'limit') !== false
            && (stripos($raw, 'exceed') !== false || stripos($raw, 'quota') !== false)) return true;
        return false;
    }

    // ── kv-кэш (общая таблица partsapi_kv_cache, префикс pc:) ─────────────────

    private function kvGet(string $k, int $ttl): ?array
    {
        try {
            $db = getDB();
            $db->exec("CREATE TABLE IF NOT EXISTS partsapi_kv_cache (k VARCHAR(96) NOT NULL PRIMARY KEY, result MEDIUMTEXT NOT NULL, cached_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $st = $db->prepare("SELECT result, cached_at FROM partsapi_kv_cache WHERE k = ?");
            $st->execute(['pc:' . $k]);
            $row = $st->fetch();
            if (!$row) return null;
            if ((time() - strtotime($row['cached_at'])) > $ttl) return null;
            $d = json_decode($row['result'], true);
            if (!is_array($d) || ($d['v'] ?? 0) !== self::CACHE_VER) return null;
            return $d;
        } catch (Exception $e) { return null; }
    }

    private function kvSet(string $k, array $data): void
    {
        try {
            $data['v'] = self::CACHE_VER;
            getDB()->prepare("INSERT INTO partsapi_kv_cache (k, result, cached_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE result=VALUES(result), cached_at=NOW()")
                   ->execute(['pc:' . $k, json_encode($data, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) { /* кэш необязателен */ }
    }

    // ── VIN → авто (carId / catalogId / criteria) ────────────────────────────

    /** @return array{carId:string,catalogId:string,criteria:string,brand:string}|null */
    private function vinToCar(string $vin)
    {
        $vin = strtoupper(trim($vin));
        if ($vin === '') return null;
        $ck = 'car:' . $vin;
        $c  = $this->kvGet($ck, self::TTL_CAR);
        if ($c !== null && !empty($c['carId'])) return $c;

        [$j] = $this->get('v1/car/info/', ['q' => $vin]);
        // carInfo возвращает массив авто; берём первое. Терпим и объект-обёртку.
        $row = null;
        if (is_array($j)) {
            $row = (isset($j[0]) && is_array($j[0])) ? $j[0] : (isset($j['carId']) ? $j : null);
        }
        if (!$row || empty($row['carId']) || empty($row['catalogId'])) return null;

        $car = [
            'carId'     => (string)$row['carId'],
            'catalogId' => (string)$row['catalogId'],
            'criteria'  => (string)($row['criteria'] ?? ''),
            'brand'     => (string)($row['brand'] ?? ''),
            'count'     => 1,
        ];
        $this->kvSet($ck, $car);
        return $car;
    }

    // ── Дерево узлов ─────────────────────────────────────────────────────────

    /**
     * Узлы БЕЗ VIN (server-render): общий справочник из настройки, чтобы дерево было
     * кликабельным сразу. Реальные car-specific узлы подгружает фронт по VIN через
     * api/vin_nodes.php → oemNodesForVin().
     */
    public function oemNodes(): array
    {
        $raw   = trim(getSetting('catalog_api_oem_nodes', ''));
        $nodes = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '=') === false) continue;
            [$id, $name] = array_map('trim', explode('=', $line, 2));
            if ($name !== '') $nodes[] = ['cat' => $id, 'name' => $name];
        }
        return $nodes;
    }

    /**
     * Реальные узлы для конкретного авто (по VIN): groups2 → листья hasParts.
     * Возвращает [['cat'=>groupId,'name'=>string], …].
     */
    public function oemNodesForVin(string $vin): array
    {
        if (!$this->enabled()) return [];
        $car = $this->vinToCar($vin);
        if ($car === null) return [];

        $ck     = 'nodes:' . $car['catalogId'] . ':' . $car['carId'];
        $cached = $this->kvGet($ck, self::TTL_DATA);
        if ($cached !== null) return $cached['nodes'] ?? [];

        $nodes = [];
        $this->collectLeaves($car, '', $nodes, 0);
        $this->kvSet($ck, ['count' => count($nodes), 'nodes' => $nodes]);
        return $nodes;
    }

    /** Рекурсивный обход groups2 до листьев (hasParts=true). Ограничение глубины/кол-ва. */
    private function collectLeaves(array $car, string $groupId, array &$out, int $depth): void
    {
        if ($depth > 4 || count($out) >= 120) return;
        [$j] = $this->get('v1/catalogs/' . rawurlencode($car['catalogId']) . '/groups2', array_filter([
            'carId'    => $car['carId'],
            'groupId'  => $groupId,
            'criteria' => $car['criteria'],
        ], fn($v) => $v !== ''));
        if (!is_array($j)) return;
        foreach ($j as $g) {
            if (!is_array($g)) continue;
            $gid  = (string)($g['id'] ?? '');
            $name = trim((string)($g['name'] ?? ''));
            if ($gid === '') continue;
            if (!empty($g['hasParts'])) {
                $out[] = ['cat' => $gid, 'name' => $name !== '' ? $name : $gid];
            } else {
                $this->collectLeaves($car, $gid, $out, $depth + 1);
            }
            if (count($out) >= 120) return;
        }
    }

    // ── Каталог по узлу / полный ─────────────────────────────────────────────

    public function searchByVinCat(string $vin, int $cat, bool $useCache = true): array
    {
        $empty = ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || $cat <= 0) return $empty;
        $car = $this->vinToCar($vin);
        if ($car === null) return $empty;

        $ck = 'parts:' . $car['catalogId'] . ':' . $car['carId'] . ':' . $cat;
        if ($useCache) {
            $c = $this->kvGet($ck, self::TTL_DATA);
            if ($c !== null) {
                $c['items']      = CatalogApi::enrichItemsFromWarehouse($c['items'] ?? []);
                $c['from_cache'] = true;
                return $c;
            }
        }

        $sp = $this->fetchScheme($car, (string)$cat);
        if ($sp['rate_limited']) {
            return ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => true, 'from_cache' => false];
        }
        $result = ['items' => $sp['parts'], 'count' => count($sp['parts']), 'cat' => $cat,
                   'rate_limited' => false, 'from_cache' => false];
        $this->kvSet($ck, $result);                                        // кэшируем СЫРЫЕ детали
        $result['items'] = CatalogApi::enrichItemsFromWarehouse($sp['parts']); // цены/сток — на чтении
        return $result;
    }

    public function searchByVin(string $vin, bool $useCache = true): array
    {
        $empty = ['items' => [], 'count' => 0, 'groups_scanned' => 0, 'errors' => 0,
                  'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled()) return $empty;

        $ck = 'vinall:' . strtoupper(trim($vin));
        if ($useCache) {
            $c = $this->kvGet($ck, self::TTL_DATA);
            if ($c !== null) {
                $c['items']      = CatalogApi::enrichItemsFromWarehouse($c['items'] ?? []);
                $c['from_cache'] = true;
                return $c;
            }
        }
        $car = $this->vinToCar($vin);
        if ($car === null) return $empty;

        $items = []; $seen = []; $scanned = 0; $rl = false;
        foreach ($this->oemNodesForVin($vin) as $n) {
            $sp = $this->fetchScheme($car, (string)$n['cat']); $scanned++;
            if ($sp['rate_limited']) { $rl = true; break; }
            foreach ($sp['parts'] as $it) {
                $key = mb_strtolower($it['brand'] . '|' . $it['part_number']);
                if ($it['part_number'] === '' || isset($seen[$key])) continue;
                $seen[$key] = true; $items[] = $it;
            }
            if (count($items) >= 400) break;
        }
        $result = ['items' => $items, 'count' => count($items), 'groups_scanned' => $scanned,
                   'errors' => 0, 'rate_limited' => $rl, 'from_cache' => false];
        if (!$rl) $this->kvSet($ck, $result);
        $result['items'] = CatalogApi::enrichItemsFromWarehouse($items);
        return $result;
    }

    /**
     * Визуальная схема + детали одного узла — для api/vin_scheme.php.
     * Возвращает ['img','caption','hotspots'=>[{n,x,y,w,h}],'parts'=>item[]+['pos'],
     *             'enabled'=>bool,'rate_limited'=>bool,'from_cache'=>bool].
     */
    public function schemeByVinCat(string $vin, int $cat): array
    {
        $out = ['img' => '', 'caption' => '', 'hotspots' => [], 'parts' => [],
                'enabled' => false, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || getSetting('catalog_pc_schema', '1') !== '1' || $cat <= 0) return $out;
        $out['enabled'] = true;
        $car = $this->vinToCar($vin);
        if ($car === null) return $out;

        $ck = 'scheme:' . $car['catalogId'] . ':' . $car['carId'] . ':' . $cat;
        $c  = $this->kvGet($ck, self::TTL_DATA);
        if ($c !== null) {
            $c['parts']      = CatalogApi::enrichItemsFromWarehouse($c['parts'] ?? []);
            $c['enabled']    = true;
            $c['from_cache'] = true;
            return $c;
        }
        $sp = $this->fetchScheme($car, (string)$cat);
        $sp['enabled'] = true;
        if ($sp['rate_limited']) return $sp;
        $this->kvSet($ck, ['img' => $sp['img'], 'caption' => $sp['caption'],
                           'hotspots' => $sp['hotspots'], 'parts' => $sp['parts'],
                           'count' => count($sp['parts'])]);
        $sp['parts'] = CatalogApi::enrichItemsFromWarehouse($sp['parts']);
        return $sp;
    }

    /**
     * parts2 → нормализованная схема: картинка + хотспоты + детали.
     * Разбор защитный: если positions/coordinates отсутствуют — hotspots пустой,
     * панель деградирует до «картинка + список» (не ломается).
     */
    private function fetchScheme(array $car, string $groupId): array
    {
        $out = ['img' => '', 'caption' => '', 'hotspots' => [], 'parts' => [], 'rate_limited' => false];
        [$j, $st, $err, $raw] = $this->get('v1/catalogs/' . rawurlencode($car['catalogId']) . '/parts2', array_filter([
            'carId'    => $car['carId'],
            'groupId'  => $groupId,
            'criteria' => $car['criteria'],
        ], fn($v) => $v !== ''));
        if ($this->isRateLimit($st, $raw)) { $out['rate_limited'] = true; return $out; }
        if (!is_array($j)) return $out;

        $out['img']     = (string)($j['img'] ?? '');
        $out['caption'] = (string)($j['imgDescription'] ?? '');

        // Хотспоты: positions[] { number, coordinates:[x,y,w,h] }.
        foreach (($j['positions'] ?? []) as $p) {
            if (!is_array($p)) continue;
            $c = $p['coordinates'] ?? null;
            if (!is_array($c) || count($c) < 4) continue;
            $out['hotspots'][] = [
                'n' => (string)($p['number'] ?? ''),
                'x' => (float)$c[0], 'y' => (float)$c[1], 'w' => (float)$c[2], 'h' => (float)$c[3],
            ];
        }

        // Детали: partGroups[].parts[] → нормализованный item + номер выноски 'pos'.
        $brand = (string)($car['brand'] ?? '');
        foreach (($j['partGroups'] ?? []) as $pg) {
            if (!is_array($pg)) continue;
            $gname = trim((string)($pg['name'] ?? ''));
            foreach (($pg['parts'] ?? []) as $part) {
                if (!is_array($part)) continue;
                $num = trim((string)($part['number'] ?? ''));
                if ($num === '') continue;
                $out['parts'][] = [
                    'name'        => trim((string)($part['name'] ?? '')) ?: $num,
                    'group'       => $gname,
                    'brand'       => $brand,
                    'part_number' => $num,
                    'pos'         => (string)($part['positionNumber'] ?? ''),
                    'in_catalog'  => false, 'part_id' => null, 'price' => null, 'stock' => null, 'url' => null,
                ];
            }
        }
        return $out;
    }

    // ── Кроссы: у PC своего метода нет → отдаём сам OEM, обогащённый складом ──

    public function crossesWithWarehouse(string $article, string $brand = ''): array
    {
        $items = CatalogApi::enrichItemsFromWarehouse([[
            'name' => $article, 'group' => '', 'brand' => $brand, 'part_number' => $article,
            'is_original' => true, 'in_catalog' => false, 'part_id' => null,
            'price' => null, 'stock' => null, 'url' => null,
        ]]);
        return ['items' => $items, 'count' => count($items), 'rate_limited' => false, 'from_cache' => false];
    }

    // ── Проверка соединения (кнопка в админке) ───────────────────────────────

    public function testConnection(string $vin = ''): array
    {
        if (!$this->hasKey()) {
            return ['ok' => false, 'count' => 0, 'sample' => '',
                    'message' => 'Parts-Catalogs: укажите API-ключ в настройках.'];
        }
        [$j, $st, $err, $raw] = $this->get('v1/catalogs/');
        $sample = mb_substr((string)$raw, 0, 600);
        if ($err !== '') {
            return ['ok' => false, 'count' => 0, 'sample' => $sample,
                    'message' => 'Parts-Catalogs: ошибка соединения — ' . $err];
        }
        if ($st === 401 || $st === 403) {
            return ['ok' => false, 'count' => 0, 'sample' => $sample,
                    'message' => 'Parts-Catalogs: ключ отклонён (HTTP ' . $st . ') — проверьте ключ '
                               . 'или формат авторизации.'];
        }
        if (is_array($j)) {
            $n   = count($j);
            $msg = 'Parts-Catalogs: соединение работает, получено каталогов: ' . $n . '.';
            if (strlen(trim($vin)) === 17) {
                $car  = $this->vinToCar($vin);
                $msg .= $car ? ' VIN распознан — авто найдено.' : ' Но этот VIN ключом не распознан.';
            }
            return ['ok' => true, 'count' => $n, 'sample' => $sample, 'message' => $msg];
        }
        return ['ok' => false, 'count' => 0, 'sample' => $sample,
                'message' => 'Parts-Catalogs: ответ получен (HTTP ' . $st . '), но не распознан. См. ниже.'];
    }

    public function clearCache(): void
    {
        try { getDB()->prepare("DELETE FROM partsapi_kv_cache WHERE k LIKE 'pc:%'")->execute(); }
        catch (Exception $e) { /* ignore */ }
    }
}
