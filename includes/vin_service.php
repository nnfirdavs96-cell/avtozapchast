<?php
/**
 * VIN Decoder Service
 * Supports: NHTSA (free, no key) and custom paid API (configurable from admin panel)
 */
class VinService
{
    // Position 10 → model year
    private static array $yearMap = [
        'A'=>[1980,2010],'B'=>[1981,2011],'C'=>[1982,2012],'D'=>[1983,2013],
        'E'=>[1984,2014],'F'=>[1985,2015],'G'=>[1986,2016],'H'=>[1987,2017],
        'J'=>[1988,2018],'K'=>[1989,2019],'L'=>[1990,2020],'M'=>[1991,2021],
        'N'=>[1992,2022],'P'=>[1993,2023],'R'=>[1994,2024],'S'=>[1995,2025],
        'T'=>[1996,2026],'V'=>[1997,2027],'W'=>[1998,2028],'X'=>[1999,2029],
        'Y'=>[2000,2030],
        '1'=>2001,'2'=>2002,'3'=>2003,'4'=>2004,'5'=>2005,
        '6'=>2006,'7'=>2007,'8'=>2008,'9'=>2009,
    ];

    // WMI (3-char) → [Make, Country]
    private static array $wmiDb = [
        // Russia / CIS
        'XTA' => ['Lada (ВАЗ)',      'Россия'],
        'XTT' => ['TagAZ',            'Россия'],
        'XUF' => ['GAZ (Газ)',        'Россия'],
        'XTH' => ['UAZ (УАЗ)',        'Россия'],
        'XW8' => ['Renault Россия',   'Россия'],
        'X7L' => ['BMW Россия',       'Россия'],
        'X9F' => ['Ford Россия',      'Россия'],
        'XWE' => ['VW Россия',        'Россия'],
        'XW0' => ['Chevrolet Россия', 'Россия'],
        'XV1' => ['KIA Россия',       'Россия'],
        // Japan
        'JT2' => ['Toyota',   'Япония'], 'JT3' => ['Toyota',   'Япония'],
        'JT4' => ['Toyota',   'Япония'], 'JT6' => ['Toyota',   'Япония'],
        'JT8' => ['Toyota',   'Япония'], 'JTE' => ['Toyota',   'Япония'],
        'JTM' => ['Toyota',   'Япония'], 'JTN' => ['Toyota',   'Япония'],
        'JHM' => ['Honda',    'Япония'], 'JH4' => ['Acura',    'Япония'],
        'JN1' => ['Nissan',   'Япония'], 'JN3' => ['Nissan',   'Япония'],
        'JN8' => ['Nissan',   'Япония'], 'JNR' => ['Infiniti', 'Япония'],
        'JM1' => ['Mazda',    'Япония'], 'JM3' => ['Mazda',    'Япония'],
        'JS1' => ['Suzuki',   'Япония'], 'JS2' => ['Suzuki',   'Япония'],
        'JS3' => ['Suzuki',   'Япония'], 'JF1' => ['Subaru',   'Япония'],
        'JF2' => ['Subaru',   'Япония'], 'JAA' => ['Isuzu',    'Япония'],
        'JA4' => ['Mitsubishi','Япония'],'JA3' => ['Mitsubishi','Япония'],
        'JD1' => ['Daihatsu', 'Япония'], 'JD2' => ['Daihatsu', 'Япония'],
        // Germany
        'WBA' => ['BMW',            'Германия'], 'WBS' => ['BMW M',       'Германия'],
        'WBY' => ['BMW i',          'Германия'], 'WBX' => ['BMW X',       'Германия'],
        'WDB' => ['Mercedes-Benz',  'Германия'], 'WDD' => ['Mercedes-Benz','Германия'],
        'WDC' => ['Mercedes-Benz',  'Германия'], 'WDF' => ['Mercedes-Benz','Германия'],
        'WVW' => ['Volkswagen',     'Германия'], 'WV2' => ['VW Commercial','Германия'],
        'WAU' => ['Audi',           'Германия'], 'WA1' => ['Audi SUV',    'Германия'],
        'WP0' => ['Porsche',        'Германия'], 'WP1' => ['Porsche SUV', 'Германия'],
        'W0L' => ['Opel',           'Германия'], 'W0V' => ['Opel',        'Германия'],
        'WF0' => ['Ford Германия',  'Германия'], 'WME' => ['Smart',       'Германия'],
        // Czech / Slovak
        'TMB' => ['Škoda', 'Чехия'], 'TMA' => ['Škoda', 'Чехия'],
        // Spain
        'VSS' => ['SEAT', 'Испания'], 'VS6' => ['SEAT', 'Испания'],
        // France
        'VF1' => ['Renault',  'Франция'], 'VF3' => ['Peugeot', 'Франция'],
        'VF7' => ['Citroën',  'Франция'], 'VF6' => ['Renault',  'Франция'],
        // Sweden
        'YV1' => ['Volvo', 'Швеция'], 'YV4' => ['Volvo SUV', 'Швеция'],
        'YS3' => ['Saab',  'Швеция'],
        // UK
        'SAJ' => ['Jaguar',     'Великобритания'], 'SAL' => ['Land Rover', 'Великобритания'],
        'SCC' => ['Lotus',      'Великобритания'], 'SCF' => ['Aston Martin','Великобритания'],
        'SBM' => ['McLaren',    'Великобритания'],
        // Italy
        'ZFF' => ['Ferrari',     'Италия'], 'ZAR' => ['Alfa Romeo', 'Италия'],
        'ZFA' => ['Fiat',        'Италия'], 'ZHW' => ['Lamborghini','Италия'],
        'ZLA' => ['Lancia',      'Италия'],
        // Korea
        'KMH' => ['Hyundai', 'Южная Корея'], 'KMF' => ['Hyundai', 'Южная Корея'],
        'KNA' => ['Kia',     'Южная Корея'], 'KND' => ['Kia',     'Южная Корея'],
        'KL4' => ['Daewoo',  'Южная Корея'], 'KL1' => ['Daewoo',  'Южная Корея'],
        // USA (common)
        '1HG' => ['Honda США',     'США'], '1G1' => ['Chevrolet', 'США'],
        '1FA' => ['Ford',           'США'], '1FT' => ['Ford Truck','США'],
        '1GT' => ['GMC',            'США'], '2HG' => ['Honda Канада','Канада'],
        // China
        'LFV' => ['VW Китай',    'Китай'], 'LGX' => ['Buick Китай',  'Китай'],
        'LJ1' => ['Suzuki Китай','Китай'], 'LSG' => ['GM China',      'Китай'],
    ];

    // First-char country fallback
    private static array $countryMap = [
        '1'=>'США','2'=>'Канада','3'=>'Мексика','4'=>'США','5'=>'США',
        '6'=>'Австралия','7'=>'Новая Зеландия',
        'J'=>'Япония','K'=>'Южная Корея','L'=>'Китай',
        'S'=>'Великобритания','T'=>'Чехия/Словакия',
        'V'=>'Франция/Испания','W'=>'Германия',
        'X'=>'Россия/СНГ','Y'=>'Швеция','Z'=>'Италия',
    ];

    // ── Public API ────────────────────────────────────────────────────────

    public static function validate(string $vin): bool
    {
        $vin = strtoupper(trim($vin));
        if (strlen($vin) !== 17) return false;
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) return false;
        // Контрольную цифру (9-я позиция) НЕ требуем: её обязаны соблюдать только
        // производители Северной Америки. Японские, корейские и многие европейские
        // VIN (напр. Mitsubishi Z8TXLCW6WCM902224) валидны, но check digit не проходят.
        // verifyCheckDigit() оставлен как вспомогательный метод для справки.
        return true;
    }

    public static function decode(string $vin): array
    {
        $vin = strtoupper(trim($vin));

        $cached = self::getCache($vin);
        if ($cached) {
            $cached['from_cache'] = true;
            return $cached;
        }

        $local  = self::parseLocal($vin);
        $remote = getSetting('vin_search_enabled', '1') === '1' ? self::callApi($vin) : [];

        // Merge: remote overwrites only non-empty local fields
        $result = $local;
        foreach ($remote as $k => $v) {
            if ($v !== '' && $v !== null && $v !== 0) {
                $result[$k] = $v;
            }
        }
        $result['vin']        = $vin;
        $result['from_cache'] = false;
        $result['source']     = empty($remote) ? 'local' : getSetting('vin_api_provider', 'nhtsa');
        $result['cv']         = self::DECODE_VER;

        self::setCache($vin, $result);
        return $result;
    }

    public static function searchCompatibleParts(string $make, string $model, int $year, ?int $categoryId = null): array
    {
        try {
            $db = getDB();

            $cond   = [];
            $params = [];
            if ($make)  { $cond[] = 'cm.make LIKE ?';  $params[] = "%{$make}%"; }
            if ($model) { $cond[] = 'cm.model LIKE ?'; $params[] = "%{$model}%"; }
            if ($year > 0) {
                $cond[]   = '(cm.year_from IS NULL OR cm.year_from <= ?)';
                $cond[]   = '(cm.year_to   IS NULL OR cm.year_to   >= ?)';
                $params[] = $year; $params[] = $year;
            }
            if ($categoryId !== null && $categoryId > 0) {
                $cond[]   = 'p.category_id = ?';
                $params[] = $categoryId;
            }
            if (!$cond) return [];

            $whereSQL = implode(' AND ', $cond);
            $stmt = $db->prepare(
                "SELECT DISTINCT p.*, b.name AS brand_name, c.name AS category_name
                 FROM parts_compatibility pc
                 JOIN car_models cm ON cm.id = pc.car_model_id AND cm.is_active = 1
                 JOIN parts p ON p.id = pc.part_id AND p.is_active = 1
                 LEFT JOIN brands b ON b.id = p.brand_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE {$whereSQL}
                 ORDER BY p.name LIMIT 60"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {}
        return [];
    }

    /**
     * Returns category facets [{id, name, count}] for compatible parts (ignores categoryId filter).
     */
    public static function getCategoryFacets(string $make, string $model, int $year): array
    {
        try {
            $db = getDB();
            $cond = []; $params = [];
            if ($make)  { $cond[] = 'cm.make LIKE ?';  $params[] = "%{$make}%"; }
            if ($model) { $cond[] = 'cm.model LIKE ?'; $params[] = "%{$model}%"; }
            if ($year > 0) {
                $cond[]   = '(cm.year_from IS NULL OR cm.year_from <= ?)';
                $cond[]   = '(cm.year_to   IS NULL OR cm.year_to   >= ?)';
                $params[] = $year; $params[] = $year;
            }
            if (!$cond) return [];
            $whereSQL = implode(' AND ', $cond);
            $stmt = $db->prepare(
                "SELECT c.id, c.name, COUNT(DISTINCT p.id) AS cnt
                 FROM parts_compatibility pc
                 JOIN car_models cm ON cm.id = pc.car_model_id AND cm.is_active = 1
                 JOIN parts p ON p.id = pc.part_id AND p.is_active = 1
                 JOIN categories c ON c.id = p.category_id
                 WHERE {$whereSQL}
                 GROUP BY c.id, c.name
                 ORDER BY cnt DESC, c.name"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) { return []; }
    }

    /**
     * Find analog parts for a given part:
     *  1) explicit mappings from part_analogs
     *  2) auto-detected: same category + at least one shared compatible car
     */
    public static function getAnalogs(int $partId, int $limit = 6): array
    {
        if ($partId <= 0) return [];
        try {
            $db = getDB();
            $rows = [];

            // 1) Explicit (table may not exist on older installs)
            try {
                $s = $db->prepare(
                    "SELECT p.*, b.name AS brand_name, c.name AS category_name,
                            pa.confidence AS analog_confidence, 'explicit' AS analog_source
                     FROM part_analogs pa
                     JOIN parts p ON p.id = pa.analog_part_id AND p.is_active = 1
                     LEFT JOIN brands b ON b.id = p.brand_id
                     LEFT JOIN categories c ON c.id = p.category_id
                     WHERE pa.part_id = ?
                     ORDER BY FIELD(pa.confidence,'exact','high','medium','low'), p.name
                     LIMIT ?"
                );
                $s->bindValue(1, $partId, PDO::PARAM_INT);
                $s->bindValue(2, $limit, PDO::PARAM_INT);
                $s->execute();
                $rows = $s->fetchAll();
            } catch (Exception $e) {}

            if (count($rows) >= $limit) return array_slice($rows, 0, $limit);
            $seen = array_column($rows, 'id');

            // 2) Auto-detected
            $s = $db->prepare(
                "SELECT DISTINCT p2.*, b.name AS brand_name, c.name AS category_name,
                        'high' AS analog_confidence, 'auto' AS analog_source
                 FROM parts p1
                 JOIN parts_compatibility pc1 ON pc1.part_id = p1.id
                 JOIN parts_compatibility pc2 ON pc2.car_model_id = pc1.car_model_id AND pc2.part_id <> p1.id
                 JOIN parts p2 ON p2.id = pc2.part_id
                                AND p2.is_active = 1
                                AND p2.category_id = p1.category_id
                 LEFT JOIN brands b ON b.id = p2.brand_id
                 LEFT JOIN categories c ON c.id = p2.category_id
                 WHERE p1.id = ?
                 ORDER BY p2.price ASC
                 LIMIT ?"
            );
            $s->bindValue(1, $partId, PDO::PARAM_INT);
            $s->bindValue(2, $limit * 2, PDO::PARAM_INT);
            $s->execute();
            foreach ($s->fetchAll() as $r) {
                if (in_array((int)$r['id'], $seen, true)) continue;
                $rows[] = $r;
                $seen[] = (int)$r['id'];
                if (count($rows) >= $limit) break;
            }
            return $rows;
        } catch (Exception $e) { return []; }
    }

    /**
     * Save a user's VIN search to history (deduped: same VIN within 1h = ignored).
     */
    public static function recordSearch(int $userId, string $vin, array $decoded): void
    {
        if ($userId <= 0) return;
        try {
            $db = getDB();
            $s = $db->prepare(
                "SELECT id FROM vin_search_history
                 WHERE user_id = ? AND vin = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 LIMIT 1"
            );
            $s->execute([$userId, $vin]);
            if ($s->fetchColumn()) return;

            $db->prepare(
                "INSERT INTO vin_search_history (user_id, vin, make, model, year)
                 VALUES (?,?,?,?,?)"
            )->execute([
                $userId,
                $vin,
                $decoded['make']  ?? null,
                $decoded['model'] ?? null,
                (int)($decoded['year'] ?? 0) ?: null,
            ]);
        } catch (Exception $e) {}
    }

    public static function getUserHistory(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) return [];
        try {
            $db = getDB();
            $s = $db->prepare(
                "SELECT vin, make, model, year, MAX(created_at) AS created_at
                 FROM vin_search_history
                 WHERE user_id = ?
                 GROUP BY vin, make, model, year
                 ORDER BY created_at DESC
                 LIMIT ?"
            );
            $s->bindValue(1, $userId, PDO::PARAM_INT);
            $s->bindValue(2, $limit,  PDO::PARAM_INT);
            $s->execute();
            return $s->fetchAll();
        } catch (Exception $e) { return []; }
    }

    public static function getStats(): array
    {
        try {
            $db = getDB();
            return [
                'cache_total'  => (int)$db->query("SELECT COUNT(*) FROM vin_cache")->fetchColumn(),
                'models_total' => (int)$db->query("SELECT COUNT(*) FROM car_models WHERE is_active=1")->fetchColumn(),
                'compat_total' => (int)$db->query("SELECT COUNT(*) FROM parts_compatibility")->fetchColumn(),
            ];
        } catch (Exception $e) {
            return ['cache_total'=>0,'models_total'=>0,'compat_total'=>0];
        }
    }

    public static function clearCache(): int
    {
        try {
            $db = getDB();
            $db->exec("DELETE FROM vin_cache");
            return 1;
        } catch (Exception $e) { return 0; }
    }

    // ── Private: local decode ─────────────────────────────────────────────

    private static function parseLocal(string $vin): array
    {
        $wmi3    = substr($vin, 0, 3);
        $wmi2    = substr($vin, 0, 2);
        $yearCh  = strtoupper($vin[9]);

        $wmiInfo = self::$wmiDb[$wmi3] ?? self::$wmiDb[$wmi2] ?? null;
        $make    = $wmiInfo ? $wmiInfo[0] : '';
        $country = $wmiInfo ? $wmiInfo[1] : (self::$countryMap[strtoupper($vin[0])] ?? 'Неизвестно');
        $year    = self::decodeYear($yearCh, (bool)$wmiInfo);

        return [
            'make'         => $make,
            'model'        => '',
            'year'         => $year,
            'country'      => $country,
            'body_type'    => '',
            'engine'       => '',
            'cylinders'    => '',
            'displacement' => '',
            'fuel_type'    => '',
            'drive_type'   => '',
            'manufacturer' => $make,
            'plant_country'=> '',
            'wmi'          => $wmi3,
        ];
    }

    private static function decodeYear(string $char, bool $isKnownModern): int
    {
        // Numeric chars → unambiguous
        $numericMap = ['1'=>2001,'2'=>2002,'3'=>2003,'4'=>2004,'5'=>2005,
                       '6'=>2006,'7'=>2007,'8'=>2008,'9'=>2009];
        if (isset($numericMap[$char])) return $numericMap[$char];

        // Letter chars have two possible years
        $map = self::$yearMap;
        if (!isset($map[$char])) return 0;
        $val = $map[$char];
        if (!is_array($val)) return $val;
        // If second year (2010+) is ≤ current year + 1, prefer it for known makes
        $currentYear = (int)date('Y');
        [$old, $new] = $val;
        return ($isKnownModern || $new <= $currentYear + 1) ? $new : $old;
    }

    // ── Private: check digit ──────────────────────────────────────────────

    private static function verifyCheckDigit(string $vin): bool
    {
        $trans   = ['A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5,'F'=>6,'G'=>7,'H'=>8,
                    'J'=>1,'K'=>2,'L'=>3,'M'=>4,'N'=>5,'P'=>7,'R'=>9,
                    'S'=>2,'T'=>3,'U'=>4,'V'=>5,'W'=>6,'X'=>7,'Y'=>8,'Z'=>9];
        $weights = [8,7,6,5,4,3,2,10,0,9,8,7,6,5,4,3,2];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $c   = $vin[$i];
            $val = is_numeric($c) ? (int)$c : ($trans[$c] ?? 0);
            $sum += $val * $weights[$i];
        }
        $rem      = $sum % 11;
        $expected = $rem === 10 ? 'X' : (string)$rem;
        return $vin[8] === $expected;
    }

    // ── Private: API calls ────────────────────────────────────────────────

    private static function callApi(string $vin): array
    {
        $provider = getSetting('vin_api_provider', 'nhtsa');
        $timeout  = (int)getSetting('vin_api_timeout', '8');
        // 'custom' and 'partsapi' both use the admin-configured URL+key template;
        // 'partsapi' is just a labelled preset of the same mechanism.
        return ($provider === 'custom' || $provider === 'partsapi')
            ? self::callCustomApi($vin, $timeout)
            : self::callNhtsa($vin, $timeout);
    }

    private static function callNhtsa(string $vin, int $timeout = 8): array
    {
        $url = "https://vpic.nhtsa.dot.gov/api/vehicles/decodevin/{$vin}?format=json";
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) return [];
        $json = json_decode($raw, true);
        if (empty($json['Results'])) return [];

        $f = [];
        foreach ($json['Results'] as $item) {
            if (!empty($item['Value']) && $item['Value'] !== 'Not Applicable') {
                $f[$item['Variable']] = $item['Value'];
            }
        }

        $cylinders    = $f['Engine Number of Cylinders'] ?? '';
        $displacement = $f['Displacement (L)'] ?? '';
        $engineStr    = trim(
            ($cylinders ? $cylinders . ' цил.' : '') . ' ' .
            ($displacement ? $displacement . 'L' : '')
        );

        return [
            'make'         => $f['Make'] ?? '',
            'model'        => $f['Model'] ?? '',
            'year'         => (int)($f['Model Year'] ?? 0),
            'body_type'    => $f['Body Class'] ?? '',
            'engine'       => $engineStr,
            'cylinders'    => $cylinders,
            'displacement' => $displacement,
            'fuel_type'    => $f['Fuel Type - Primary'] ?? '',
            'drive_type'   => $f['Drive Type'] ?? '',
            'manufacturer' => $f['Manufacturer Name'] ?? '',
            'plant_country'=> $f['Plant Country'] ?? '',
        ];
    }

    private static function callCustomApi(string $vin, int $timeout = 10): array
    {
        $url = getSetting('vin_api_url', '');
        $key = getSetting('vin_api_key', '');
        if (!$url) return [];

        // {VIN} and {KEY} placeholders (case-insensitive) — supports both
        // query-param keys (…?key={KEY}&vin={VIN}) and header-based auth below.
        $url  = str_ireplace(['{VIN}', '{KEY}'], [rawurlencode($vin), rawurlencode($key)], $url);
        $hdrs = array_filter([
            "Accept: application/json",
            $key ? "Authorization: Bearer {$key}" : '',
            $key ? "X-Api-Key: {$key}" : '',
        ]);
        $ctx  = stream_context_create(['http' => [
            'timeout'       => $timeout,
            'ignore_errors' => true,
            'header'        => implode("\r\n", $hdrs),
        ]]);
        $raw  = @file_get_contents($url, false, $ctx);
        if (!$raw) return [];
        $json = json_decode($raw, true);
        if (!is_array($json)) return [];

        // Unwrap common PartsAPI envelopes: {"data":{"array":{…}}} / {"data":[…]} / [ … ].
        $n = $json;
        if (isset($n['data'])  && is_array($n['data']))  $n = $n['data'];
        if (isset($n['array']) && is_array($n['array'])) $n = $n['array'];
        if (isset($n[0])       && is_array($n[0]))       $n = $n[0];

        // PartsAPI VINdecodeOE returns transliterated/Russian keys (brend, naimenovanie,
        // modely, modifikaciya, data, rynok…). We accept both those and the English
        // names from the docs, so the parser is robust to either response language.
        $pick = function (array ...$keys) use ($n): string {
            foreach ($keys as $group) {
                foreach ($group as $k) {
                    if (isset($n[$k]) && trim((string)$n[$k]) !== '') return trim((string)$n[$k]);
                }
            }
            return '';
        };

        $make    = $pick(['make', 'brand', 'brend']);
        $modelNm = $pick(['model', 'naimenovanie']);
        $chassis = $pick(['modely', 'chassis']);
        $model   = $modelNm;
        if ($chassis !== '' && strcasecmp($chassis, $modelNm) !== 0) {
            $model = $modelNm !== '' ? "{$modelNm} ({$chassis})" : $chassis;
        }

        // Year: first explicit numeric field, else dig a 19xx/20xx out of any date field.
        $year = (int)($n['year'] ?? $n['modelYear'] ?? $n['modelyearfrom'] ?? 0);
        if ($year === 0) {
            $dateBlob = implode(' ', array_map('strval', [
                $n['data'] ?? '', $n['date'] ?? '', $n['data_vypuska'] ?? '',
                $n['modely_vypuskaetsya_s'] ?? '',
            ]));
            if (preg_match('/(19|20)\d{2}/', $dateBlob, $mm)) $year = (int)$mm[0];
        }

        // Engine: prefer an explicit engine, otherwise show the modification string
        // (PartsAPI packs displacement/trim into "modifikaciya").
        $engine = $pick(['engine']);
        $modif  = $pick(['modification', 'modifikaciya']);
        if ($engine === '') $engine = $modif;
        elseif ($modif !== '' && stripos($engine, $modif) === false) $engine = trim("$engine $modif");

        // Keep only non-empty values so they don't overwrite local WMI data on merge.
        return array_filter([
            'make'          => $make,
            'model'         => $model,
            'year'          => $year,
            'body_type'     => $pick(['bodyType', 'body', 'bodystyle', 'kuzova']),
            'engine'        => $engine,
            'fuel_type'     => $pick(['fuelType', 'fuel']),
            'drive_type'    => $pick(['driveType', 'drive']),
            'country'       => $pick(['market', 'rynok']),
            'plant_country' => $pick(['plant', 'kod_zavoda_izgotovitelya']),
        ], fn($v) => $v !== '' && $v !== 0 && $v !== null);
    }

    // ── Private: cache ────────────────────────────────────────────────────

    /** Версия логики декодирования: смена инвалидирует старый vin_cache
     *  (напр. записи, закэшированные до подключения VINdecodeOE — только страна). */
    private const DECODE_VER = 2;

    private static function getCache(string $vin): ?array
    {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT result FROM vin_cache
                 WHERE vin = ? AND cached_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $stmt->execute([$vin]);
            $row = $stmt->fetch();
            if (!$row) return null;
            $data = json_decode($row['result'], true);
            if (!is_array($data) || ($data['cv'] ?? 0) !== self::DECODE_VER) return null;
            return $data;
        } catch (Exception $e) { return null; }
    }

    private static function setCache(string $vin, array $data): void
    {
        try {
            $db = getDB();
            $db->prepare(
                "INSERT INTO vin_cache (vin, result, source) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE result=?, source=?, cached_at=NOW()"
            )->execute([
                $vin, json_encode($data), $data['source'] ?? 'local',
                json_encode($data), $data['source'] ?? 'local',
            ]);
        } catch (Exception $e) {}
    }
}
