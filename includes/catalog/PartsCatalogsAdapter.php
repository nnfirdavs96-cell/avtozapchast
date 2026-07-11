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
 *   catalog_pc_key       — API-ключ
 *   catalog_pc_base      — база API (по умолчанию https://api.parts-catalogs.com/; для
 *                          Tradesoft может быть хост с /api/ — задаётся тут)
 *   catalog_pc_timeout   — таймаут, сек (по умолчанию 20)
 *   catalog_pc_schema    — показывать визуальные схемы ('1'/'0', по умолчанию '1')
 *   catalog_pc_auth      — способ авторизации: header|bearer|query (по умолчанию header)
 *   catalog_pc_key_param — имя query-параметра ключа при auth=query (по умолчанию api_key)
 *
 * Кэш: общая таблица partsapi_kv_cache, префикс ключей 'pc:'. Тарификация PC — по
 * VIN/24ч, поэтому кэшируем агрессивно по VIN (24ч): повторные просмотры бесплатны.
 * Цену/наличие НЕ кэшируем в payload — обогащаем складом на чтении (сток live).
 */
require_once __DIR__ . '/Provider.php';
require_once __DIR__ . '/../catalog_api.php';

class PartsCatalogsAdapter implements CatalogProvider
{
    private const CACHE_VER = 4;  // v4: канонизация номеров выносок/позиций (01→1) — сброс кэша
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

    /**
     * Способ передачи ключа. Tradesoft/Parts-Catalogs встречается в двух вариантах:
     *   header  — заголовок `Authorization: <ключ>` (сырой; англ. клиент pc-client-slim)
     *   bearer  — заголовок `Authorization: Bearer <ключ>`
     *   query   — параметр запроса `?api_key=<ключ>` (рус. дока Tradesoft)
     * Выбирается в админке (`catalog_pc_auth`), по умолчанию header. Имя query-параметра
     * настраивается (`catalog_pc_key_param`, по умолчанию api_key; бывает auth_key).
     */
    private function authStyle(): string
    {
        $s = trim(getSetting('catalog_pc_auth', 'header'));
        return in_array($s, ['header', 'bearer', 'query'], true) ? $s : 'header';
    }

    private function keyParam(): string
    {
        $p = trim(getSetting('catalog_pc_key_param', 'api_key'));
        return $p !== '' ? $p : 'api_key';
    }

    /**
     * Язык ответов API (названия узлов/деталей).
     * ВНИМАНИЕ: официальный клиент Parts-Catalogs язык НЕ передаёт — он определяется
     * настройкой АККАУНТА/КЛЮЧА на стороне Tradesoft. Мы всё же отправляем язык
     * тремя стандартными способами (заголовки Accept-Language/Language + query lang):
     * если конкретный ключ их принимает — ответ будет на нужном языке; если нет —
     * язык нужно переключить в аккаунте Tradesoft (у менеджера). Настройка
     * `catalog_pc_lang` (ru/en) выбирается в админке.
     */
    private function lang(): string
    {
        $l = strtolower(trim(getSetting('catalog_pc_lang', 'ru')));
        return preg_match('/^[a-z]{2}$/', $l) ? $l : 'ru';
    }

    /** Заголовки запроса с учётом способа авторизации + язык (best-effort). */
    private function authHdr(): array
    {
        $lang = $this->lang();
        $h = ['Accept: application/json', 'Accept-Language: ' . $lang, 'Language: ' . $lang];
        $style = $this->authStyle();
        if ($style === 'header')      $h[] = 'Authorization: ' . $this->key();
        elseif ($style === 'bearer')  $h[] = 'Authorization: Bearer ' . $this->key();
        // style === 'query' → ключ уходит в query-параметр (см. get()), заголовок не нужен
        return $h;
    }

    /**
     * GET к PC. Возвращает [decoded(array|null), status(int), error(string), raw(string)].
     */
    private function get(string $path, array $query = []): array
    {
        if ($this->authStyle() === 'query' && $this->key() !== '') {
            $query[$this->keyParam()] = $this->key();
        }
        // Язык также как query-параметр (best-effort; лишний параметр API игнорирует).
        if (!isset($query['lang'])) $query['lang'] = $this->lang();
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
        return $this->carInfoFull($vin);
    }

    /**
     * Полный ответ car/info по VIN: маршрутные поля (carId/catalogId/criteria/brand)
     * + разобранные атрибуты авто ('attrs': модель/серия/двигатель/год/кузов/…).
     * Кэшируется ОДНОЙ записью (car:lang:vin) — и дерево узлов, и карточка авто
     * читают из неё, поэтому лишний запрос к PC (и лишний кредит по VIN) НЕ уходит.
     * @return array{carId:string,catalogId:string,criteria:string,brand:string,attrs:array}|null
     */
    private function carInfoFull(string $vin)
    {
        $vin = strtoupper(trim($vin));
        if ($vin === '') return null;
        $ck = 'car:' . $this->lang() . ':' . $vin;
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
            'attrs'     => $this->parseCarAttrs($row),
        ];
        $this->kvSet($ck, $car);
        return $car;
    }

    /**
     * Разбор атрибутов авто из ответа car/info (title/modelName/brand + parameters[]).
     * Возвращает только НЕПУСТЫЕ поля: make, model, model_line, series, engine, year,
     * body_type, region, steering, transmission.
     */
    private function parseCarAttrs(array $row): array
    {
        $p = [];
        foreach (($row['parameters'] ?? []) as $par) {
            if (!is_array($par)) continue;
            $k = (string)($par['key'] ?? '');
            $v = trim((string)($par['value'] ?? ''));
            if ($k !== '' && $v !== '') $p[$k] = $v;
        }
        $model = trim((string)($row['title'] ?? ($p['car_name'] ?? '')));
        $out = [
            'make'         => trim((string)($row['brand'] ?? '')),
            'model'        => $model,
            'model_line'   => trim((string)($row['modelName'] ?? '')),
            'series'       => $p['spec_series'] ?? '',
            'engine'       => $p['engine'] ?? '',
            'year'         => isset($p['year']) ? (int)$p['year'] : 0,
            'body_type'    => $p['body_type'] ?? '',
            'region'       => $p['sales_region'] ?? '',
            'steering'     => $p['steering'] ?? '',
            'transmission' => $p['trans_type'] ?? '',
        ];
        return array_filter($out, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    }

    /**
     * Публичный: атрибуты авто по VIN для карточки на странице VIN. Переиспользует
     * кэш car/info (см. carInfoFull) — дополнительного запроса к PC не делает.
     */
    public function vinCarInfo(string $vin): array
    {
        $c = $this->carInfoFull($vin);
        return is_array($c) ? ($c['attrs'] ?? []) : [];
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

        $ck     = 'nodes:' . $this->lang() . ':' . $car['catalogId'] . ':' . $car['carId'];
        $cached = $this->kvGet($ck, self::TTL_DATA);
        if ($cached !== null) return $cached['nodes'] ?? [];

        $nodes = [];
        $this->collectLeaves($car, '', $nodes, 0);
        // Кэшируем ТОЛЬКО непустой результат: разовый сбой/лимит (пусто) не должен
        // залипать на 24ч и «прятать» каталог у авто, где узлы на самом деле есть.
        if (!empty($nodes)) $this->kvSet($ck, ['count' => count($nodes), 'nodes' => $nodes]);
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
                // img — миниатюра схемы узла (как карточки у 7zap); может отсутствовать.
                $out[] = ['cat' => $gid, 'name' => $name !== '' ? $name : $gid,
                          'img' => (string)($g['img'] ?? '')];
            } else {
                $this->collectLeaves($car, $gid, $out, $depth + 1);
            }
            if (count($out) >= 120) return;
        }
    }

    // ── Каталог по узлу / полный ─────────────────────────────────────────────

    public function searchByVinCat(string $vin, $cat, bool $useCache = true): array
    {
        $cat   = (string)$cat;
        $empty = ['items' => [], 'count' => 0, 'cat' => $cat, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || $cat === '') return $empty;
        $car = $this->vinToCar($vin);
        if ($car === null) return $empty;

        $ck = 'parts:' . $this->lang() . ':' . $car['catalogId'] . ':' . $car['carId'] . ':' . $cat;
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
    public function schemeByVinCat(string $vin, string $cat): array
    {
        $cat = trim($cat);
        $out = ['img' => '', 'caption' => '', 'hotspots' => [], 'parts' => [],
                'enabled' => false, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || getSetting('catalog_pc_schema', '1') !== '1' || $cat === '') return $out;
        $out['enabled'] = true;
        $car = $this->vinToCar($vin);
        if ($car === null) return $out;

        $ck = 'scheme:' . $this->lang() . ':' . $car['catalogId'] . ':' . $car['carId'] . ':' . $cat;
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
     * Канонизация номера выноски/позиции: trim + убрать ведущие нули ("01"→"1"),
     * чтобы выноска на схеме и карточка детали совпадали независимо от формата
     * (частая причина «деталь не подсвечивается»). Нечисловые — как есть.
     */
    private static function canonPos($s): string
    {
        $s = trim((string)$s);
        if ($s === '' || !ctype_digit($s)) return $s;
        $s = ltrim($s, '0');
        return $s === '' ? '0' : $s;
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
                'n' => self::canonPos($p['number'] ?? ''),
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
                    'pos'         => self::canonPos($part['positionNumber'] ?? ''),
                    'in_catalog'  => false, 'part_id' => null, 'price' => null, 'stock' => null, 'url' => null,
                ];
            }
        }
        return $out;
    }

    // ── Каскад «По параметрам»: Марка → Модель → уточнения → авто ────────────
    //  (те же поля, что подтвердились на VIN-пути; клиент alex-ello/pc-client-slim)

    /** Собрать $car из выбранного по параметрам авто (без VIN). */
    private function carArr(string $carId, string $catalogId, string $criteria, string $brand = ''): array
    {
        return ['carId' => $carId, 'catalogId' => $catalogId, 'criteria' => $criteria, 'brand' => $brand, 'count' => 1];
    }

    /** Марки (каталоги): [{id,name}]. `v1/catalogs/`. Кэш 30д. */
    public function pcBrands(): array
    {
        if (!$this->enabled()) return [];
        $ck = 'brands:' . $this->lang();
        $c  = $this->kvGet($ck, self::TTL_CATS);
        if ($c !== null) return $c['items'] ?? [];
        [$j] = $this->get('v1/catalogs/');
        $out = [];
        foreach ((is_array($j) ? $j : []) as $b) {
            if (!is_array($b)) continue;
            $id = (string)($b['id'] ?? ''); $nm = trim((string)($b['name'] ?? ''));
            if ($id === '') continue;
            $out[] = ['id' => $id, 'name' => $nm !== '' ? $nm : $id];
        }
        $this->kvSet($ck, ['count' => count($out), 'items' => $out]);
        return $out;
    }

    /** Модели марки: [{id,name,img}]. `v1/catalogs/{id}/models`. Кэш 30д. */
    public function pcModels(string $catalogId): array
    {
        if (!$this->enabled() || $catalogId === '') return [];
        $ck = 'models:' . $this->lang() . ':' . $catalogId;
        $c  = $this->kvGet($ck, self::TTL_CATS);
        if ($c !== null) return $c['items'] ?? [];
        [$j] = $this->get('v1/catalogs/' . rawurlencode($catalogId) . '/models');
        $out = [];
        foreach ((is_array($j) ? $j : []) as $m) {
            if (!is_array($m)) continue;
            $id = (string)($m['id'] ?? ''); $nm = trim((string)($m['name'] ?? ''));
            if ($id === '') continue;
            $out[] = ['id' => $id, 'name' => $nm !== '' ? $nm : $id, 'img' => (string)($m['img'] ?? '')];
        }
        $this->kvSet($ck, ['count' => count($out), 'items' => $out]);
        return $out;
    }

    /**
     * Уточняющие параметры (год/кузов/двигатель/рулевое/рынок…): динамический набор.
     * `v1/catalogs/{id}/cars-parameters?modelId&parameter=idx1,idx2`. Возвращает
     * [{key,name,values:[{idx,value}]}]. Итеративно: накопленные idx сужают набор.
     */
    public function pcCarParams(string $catalogId, string $modelId, string $paramCsv = ''): array
    {
        if (!$this->enabled() || $catalogId === '' || $modelId === '') return [];
        $ck = 'cparams:' . $this->lang() . ':' . $catalogId . ':' . $modelId . ':' . md5($paramCsv);
        $c  = $this->kvGet($ck, self::TTL_DATA);
        if ($c !== null) return $c['items'] ?? [];
        [$j] = $this->get('v1/catalogs/' . rawurlencode($catalogId) . '/cars-parameters', array_filter([
            'catalogId' => $catalogId, 'modelId' => $modelId, 'parameter' => $paramCsv,
        ], fn($v) => $v !== ''));
        $out = [];
        foreach ((is_array($j) ? $j : []) as $p) {
            if (!is_array($p)) continue;
            $vals = [];
            foreach (($p['values'] ?? []) as $v) {
                if (!is_array($v)) continue;
                $idx = (string)($v['idx'] ?? ''); if ($idx === '') continue;
                $vals[] = ['idx' => $idx, 'value' => trim((string)($v['value'] ?? $idx))];
            }
            if (!$vals) continue;
            $out[] = ['key' => (string)($p['key'] ?? ''), 'name' => trim((string)($p['name'] ?? '')), 'values' => $vals];
        }
        $this->kvSet($ck, ['count' => count($out), 'items' => $out]);
        return $out;
    }

    /**
     * Конкретные авто (модификации) под выбранные параметры.
     * `v1/catalogs/{id}/cars2?modelId&parameter=idx1,idx2`.
     * Возвращает [{carId,catalogId,criteria,name,modelName,brand}] (эти carId/criteria
     * идут прямо в groups2/parts2 — как из VIN).
     */
    public function pcCars(string $catalogId, string $modelId, string $paramCsv = ''): array
    {
        if (!$this->enabled() || $catalogId === '' || $modelId === '') return [];
        $ck = 'cars:' . $this->lang() . ':' . $catalogId . ':' . $modelId . ':' . md5($paramCsv);
        $c  = $this->kvGet($ck, self::TTL_DATA);
        if ($c !== null) return $c['items'] ?? [];
        [$j] = $this->get('v1/catalogs/' . rawurlencode($catalogId) . '/cars2', array_filter([
            'modelId' => $modelId, 'parameter' => $paramCsv,
        ], fn($v) => $v !== ''));
        $out = [];
        foreach ((is_array($j) ? $j : []) as $c2) {
            if (!is_array($c2)) continue;
            $id = (string)($c2['id'] ?? ''); if ($id === '') continue;
            $out[] = [
                'carId'     => $id,
                'catalogId' => (string)($c2['catalogId'] ?? $catalogId),
                'criteria'  => (string)($c2['criteria'] ?? ''),
                'name'      => trim((string)($c2['name'] ?? '')),
                'modelId'   => (string)($c2['modelId'] ?? $modelId),
                'modelName' => trim((string)($c2['modelName'] ?? '')),
                'brand'     => trim((string)($c2['brand'] ?? '')),
            ];
            if (count($out) >= 200) break;
        }
        $this->kvSet($ck, ['count' => count($out), 'items' => $out]);
        return $out;
    }

    /**
     * Атрибуты авто, выбранного по параметрам (без VIN) — из `cars2` (у car/info
     * контракт только по VIN). Возвращает НЕПУСТЫЕ поля для карточки: make, model,
     * series (модификация), engine, year, body_type, region, transmission.
     */
    public function pcCarAttrs(string $catalogId, string $modelId, string $carId): array
    {
        if (!$this->enabled() || $catalogId === '' || $modelId === '' || $carId === '') return [];
        $ck = 'carattrs:' . $this->lang() . ':' . $catalogId . ':' . $carId;
        $c  = $this->kvGet($ck, self::TTL_DATA);
        if ($c !== null) return $c['attrs'] ?? [];
        [$j] = $this->get('v1/catalogs/' . rawurlencode($catalogId) . '/cars2', ['modelId' => $modelId]);
        $attrs = [];
        foreach ((is_array($j) ? $j : []) as $car) {
            if (is_array($car) && (string)($car['id'] ?? '') === $carId) {
                $attrs = $this->parseCarAttrsFromCar($car);
                break;
            }
        }
        if (!empty($attrs)) $this->kvSet($ck, ['attrs' => $attrs]);
        return $attrs;
    }

    /** Разбор атрибутов из объекта авто cars2 (parameters[] + description). */
    private function parseCarAttrsFromCar(array $car): array
    {
        $p = [];
        foreach (($car['parameters'] ?? []) as $par) {
            if (!is_array($par)) continue;
            $k = (string)($par['key'] ?? ''); $v = trim((string)($par['value'] ?? ''));
            if ($k !== '' && $v !== '') $p[$k] = $v;
        }
        // description: «СЕДАН КУПЕ. Regions: Europe, …» → кузов + регион.
        $desc = trim((string)($car['description'] ?? ''));
        $region = ''; $body = '';
        if ($desc !== '') {
            if (preg_match('/Regions?:\s*(.+)$/ui', $desc, $rm)) {
                $region = trim($rm[1]);
                $body   = trim(preg_replace('/\.?\s*Regions?:.*$/ui', '', $desc));
            } else {
                $body = $desc;
            }
            if (mb_strlen($body) > 40) $body = '';   // длинные описания не тащим в «кузов»
        }
        $out = [
            'make'         => trim((string)($car['brand'] ?? '')),
            'model'        => trim((string)($car['modelName'] ?? '')),
            'series'       => $p['modificationId'] ?? ($p['modification'] ?? ($p['spec_series'] ?? '')),
            'engine'       => $p['engine'] ?? '',
            'year'         => isset($p['year']) ? (int)$p['year'] : 0,
            'body_type'    => $p['body_type'] ?? $body,
            'region'       => $region ?: ($p['sales_region'] ?? ''),
            'transmission' => $p['trans_type'] ?? ($p['transmission'] ?? ''),
        ];
        return array_filter($out, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    }

    /** Узлы для авто, выбранного по параметрам (без VIN) — тот же groups2, что и по VIN. */
    public function oemNodesForCar(string $carId, string $catalogId, string $criteria, string $brand = ''): array
    {
        if (!$this->enabled() || $carId === '' || $catalogId === '') return [];
        $ck     = 'nodes:' . $this->lang() . ':' . $catalogId . ':' . $carId;
        $cached = $this->kvGet($ck, self::TTL_DATA);
        if ($cached !== null) return $cached['nodes'] ?? [];
        $car   = $this->carArr($carId, $catalogId, $criteria, $brand);
        $nodes = [];
        $this->collectLeaves($car, '', $nodes, 0);
        // Кэшируем ТОЛЬКО непустой результат: разовый сбой/лимит (пусто) не должен
        // залипать на 24ч и «прятать» каталог у авто, где узлы на самом деле есть.
        if (!empty($nodes)) $this->kvSet($ck, ['count' => count($nodes), 'nodes' => $nodes]);
        return $nodes;
    }

    /** Схема+детали узла для авто, выбранного по параметрам (без VIN) — тот же parts2. */
    public function schemeByCar(string $carId, string $catalogId, string $criteria, string $cat, string $brand = ''): array
    {
        $cat = trim($cat);
        $out = ['img' => '', 'caption' => '', 'hotspots' => [], 'parts' => [],
                'enabled' => false, 'rate_limited' => false, 'from_cache' => false];
        if (!$this->enabled() || getSetting('catalog_pc_schema', '1') !== '1'
            || $cat === '' || $carId === '' || $catalogId === '') return $out;
        $out['enabled'] = true;
        $ck = 'scheme:' . $this->lang() . ':' . $catalogId . ':' . $carId . ':' . $cat;
        $c  = $this->kvGet($ck, self::TTL_DATA);
        if ($c !== null) {
            $c['parts']      = CatalogApi::enrichItemsFromWarehouse($c['parts'] ?? []);
            $c['enabled']    = true;
            $c['from_cache'] = true;
            return $c;
        }
        $sp = $this->fetchScheme($this->carArr($carId, $catalogId, $criteria, $brand), $cat);
        $sp['enabled'] = true;
        if ($sp['rate_limited']) return $sp;
        $this->kvSet($ck, ['img' => $sp['img'], 'caption' => $sp['caption'],
                           'hotspots' => $sp['hotspots'], 'parts' => $sp['parts'],
                           'count' => count($sp['parts'])]);
        $sp['parts'] = CatalogApi::enrichItemsFromWarehouse($sp['parts']);
        return $sp;
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
