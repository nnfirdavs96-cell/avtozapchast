<?php
/**
 * Цена от поставщика AutoEuro по OEM-номеру — фолбэк, когда детали нет на своём
 * складе. offersByOem() отдаёт НЕСКОЛЬКО вариантов (сперва наличие по цене, затем
 * под заказ по дате) для выбора покупателем; priceByOem() — лучший из них (один).
 * Из наличия берём самое дешёвое; если наличия нет — самое быстрое под заказ.
 * Цена AutoEuro — в РУБЛЯХ, а сайт показывает сомони (курс TJS=1.0), поэтому
 * переводим по настраиваемому курсу `autoeuro_rub_rate` (сомони за 1 рубль) —
 * иначе рублёвая цена показывалась бы как сомони 1:1 (завышение ~9×) — и применяем
 * наценку. Доставки Москва→Худжанд в API AutoEuro НЕТ (цена покрывает только
 * склад→Москва), поэтому её надбавку (сумма/процент) и срок в днях добавляем сами
 * по настройкам autoeuro_khj_* — итог покупателю показывается уже до Худжанда.
 *
 * Требует настроенного AutoEuro (autoeuro_enabled + ключ + delivery_key) и
 * непустого бренда (AutoEuro ищет по паре бренд+код).
 *
 * Случаи, когда AutoEuro не вернул цену (ни ошибка API, ни совпадение по
 * артикулу), логируются в `warehouse_api_log` (action=autoeuro_price_miss) —
 * видно в Суперадмин → Склад → Лог запросов. Ничего не меняет для покупателя
 * (как показывалось «под заказ», так и показывается) — это только диагностика,
 * чтобы понять, из-за чего конкретно не находится цена (детали действительно
 * нет у поставщика, или, например, расхождение в написании бренда).
 *
 * Маппинг брендов: название бренда приходит из каталога Tradesoft и может не
 * совпадать с тем, как этот же бренд называется у AutoEuro (например, «VAG» vs
 * «Volkswagen») — тогда searchItems() молча вернёт пусто. Настройка
 * `autoeuro_brand_map` (строки «ИСХОДНЫЙ = Замена», как OEM-узлы в vin.php)
 * подменяет название перед запросом к AutoEuro. Редактируется в Суперадмин →
 * Склад; правильные написания видно через «Быстрый поиск товара» там же и по
 * логу autoeuro_price_miss.
 */
require_once __DIR__ . '/PriceProvider.php';
require_once __DIR__ . '/../autoeuro.php';

class AutoEuroPriceProvider implements PriceProvider
{
    public function id(): string { return 'autoeuro'; }

    /**
     * Лучшее предложение (одно). Возвращает первый вариант из offersByOem() —
     * это либо самое дешёвое из наличия, либо (если наличия нет) самое быстрое
     * под заказ. Формат — как в контракте PriceProvider: цена в СОМОНИ (уже с
     * курсом рубля, наценкой и надбавкой Москва→Худжанд), delivery = дата
     * прибытия в Худжанд (YYYY-MM-DD).
     */
    public function priceByOem(string $oem, string $brand = ''): ?array
    {
        $options = $this->offersByOem($oem, $brand);
        if (!$options) return null;
        $o = $options[0];
        return [
            'price'    => $o['price'],
            'stock'    => $o['stock'],
            'source'   => 'autoeuro',
            'delivery' => $o['delivery_total'] ?? $o['delivery_ae'] ?? null,
            'name'     => $o['name'] ?? null,
            'part_id'  => null,
            'url'      => null,
        ];
    }

    /**
     * Несколько предложений для показа покупателю с ВЫБОРОМ срока/цены. AutoEuro
     * на один артикул даёт много предложений с разных складов (разная цена и срок).
     * Возвращаем упорядоченный список: сперва наличие (по цене), затем под заказ
     * (по дате). Дубли по (цена+дата) схлопываем, режем до autoeuro_offers_limit.
     *
     * Каждый вариант — уже в СОМОНИ с курсом+наценкой+надбавкой Москва→Худжанд, и с
     * полным сроком до Худжанда. Пусто ([]) — если поставщик ничего не вернул.
     *
     * @return array<int,array{price:float,price_raw_rub:float,stock:int,in_stock:bool,
     *   delivery_ae:?string,delivery_days:int,delivery_total:?string,name:?string}>
     */
    public function offersByOem(string $oem, string $brand = ''): array
    {
        $oem   = trim($oem);
        $brand = trim($brand);
        if ($oem === '' || $brand === '') return [];

        $ae = AutoEuro::fromSettings();
        if (!$ae) return [];
        $deliveryKey = trim(getSetting('autoeuro_delivery_key', ''));
        if ($deliveryKey === '') return [];

        $queryBrand = self::mapBrand($brand);
        // searchItemsSmart: сперва резолвит каноничные бренд+код через search_brands
        // (AutoEuro хранит код в своём формате, напр. с пробелами), затем ищет цену.
        // with_offers=1 обязателен: именно предложения несут цену/наличие/срок.
        $res = $ae->searchItemsSmart($queryBrand, $oem, $deliveryKey, true, true);
        if (!is_array($res) || isset($res['error'])) {
            self::logMiss($brand, $queryBrand, $oem, $res);
            return [];
        }
        $offers = isset($res[0]) ? $res : (array)$res;
        $out    = $this->buildOffers($offers, $oem);
        if (!$out) {
            self::logMiss($brand, $queryBrand, $oem, $res);
        } else {
            // Ленивое обучение словаря названий (без лишних запросов — уже спросили).
            self::rememberName($oem, $brand, $out[0]['name'] ?? '');
        }
        return $out;
    }

    /**
     * Поиск по одному АРТИКУЛУ (без бренда) — для главного поисковика витрины.
     * AutoEuro ищет по паре бренд+код, поэтому сперва search_brands($oem) отдаёт
     * все бренды, у которых есть этот артикул; затем по каждому бренду тянем
     * предложения (search_items) и собираем ОДНУ карточку на бренд (лучшее
     * предложение + список вариантов для модалки). Кроссы отбрасываем — только
     * точный артикул. Карточки сортируем: сперва наличие, потом по цене.
     *
     * @return array<int,array{brand:string,code:string,name:string,best:array,offers:array}>
     */
    public function offersByArticle(string $oem, int $maxBrands = 8): array
    {
        $oem = trim($oem);
        if ($oem === '') return [];

        $ae = AutoEuro::fromSettings();
        if (!$ae) return [];
        $deliveryKey = trim(getSetting('autoeuro_delivery_key', ''));
        if ($deliveryKey === '') return [];

        $brands = $ae->searchBrands($oem);
        if (!is_array($brands) || isset($brands['error'])) return [];
        $list = isset($brands[0]) ? $brands : (array)$brands;

        $want      = self::norm($oem);
        $cards     = [];
        $seenBrand = [];
        // Бюджет запросов: не дёргаем search_items больше, чем нужно — иначе на
        // популярный артикул с десятками брендов страница поиска встанет. Берём
        // не более maxBrands РАЗНЫХ брендов (столько же HTTP-запросов максимум).
        $attempts   = 0;
        $maxAttempts = max(1, $maxBrands);
        foreach ($list as $b) {
            if (!is_array($b)) continue;
            $brandName = trim((string)($b['brand'] ?? ''));
            $code      = trim((string)($b['code'] ?? ''));
            if ($brandName === '' || $code === '') continue;
            if (self::norm($code) !== $want) continue;          // только точный артикул, без кроссов
            $bkey = self::normBrandKey($brandName);
            if (isset($seenBrand[$bkey])) continue;
            $seenBrand[$bkey] = true;

            if ($attempts >= $maxAttempts) break;
            $attempts++;
            // Каноничные бренд+код уже есть из search_brands → зовём search_items напрямую
            // (без crosses), не гоняя search_brands ещё раз через searchItemsSmart.
            $raw = $ae->searchItems($brandName, $code, $deliveryKey, false, true);
            if (!is_array($raw) || isset($raw['error'])) continue;
            $rawOffers = isset($raw[0]) ? $raw : (array)$raw;
            $offers    = $this->buildOffers($rawOffers, $oem);
            if (!$offers) continue;

            $cardName = (string)($offers[0]['name'] ?? ($b['name'] ?? ''));
            $cards[] = [
                'brand'  => $brandName,
                'code'   => $code,
                'name'   => $cardName,
                'best'   => $offers[0],
                'offers' => $offers,
            ];
            // Ленивое обучение словаря: запомнили «артикул+бренд → название».
            self::rememberName($code, $brandName, $cardName);
        }

        usort($cards, function ($a, $b) {
            $ai = $a['best']['in_stock'] ? 0 : 1;
            $bi = $b['best']['in_stock'] ? 0 : 1;
            return $ai === $bi ? ($a['best']['price'] <=> $b['best']['price']) : ($ai <=> $bi);
        });
        return $cards;
    }

    /**
     * Разбор сырых предложений AutoEuro в упорядоченный список вариантов
     * (в СОМОНИ, с курсом+наценкой+надбавкой и полным сроком до Худжанда).
     * Сперва наличие (по цене), затем под заказ (по дате). Дубли (цена+срок)
     * схлопываются, режется до autoeuro_offers_limit. Пусто → [].
     */
    private function buildOffers(array $offers, string $oem): array
    {
        // Отдельной платы за доставку в API нет (проверено get_warehouses) — цена уже
        // привязана к складу+пункту (доставка склад→Москва зашита в цену).
        // delivery_time — дата прибытия В МОСКВУ.
        $want     = self::norm($oem);
        $inStock  = [];
        $preorder = [];
        foreach ($offers as $o) {
            if (!is_array($o)) continue;
            $priceRub = (float)($o['price'] ?? 0);
            if ($priceRub <= 0) continue;
            // Только точное совпадение кода: searchItems может вернуть и кроссы.
            if (self::norm((string)($o['code'] ?? '')) !== $want) continue;
            $row = [
                'price_rub'   => $priceRub,
                'stock'       => (int)($o['stock'] ?? $o['amount'] ?? 0),
                'delivery_ae' => self::normDate($o['delivery_time'] ?? null),
                'name'        => $o['name'] ?? null,
            ];
            if ($row['stock'] > 0) $inStock[] = $row; else $preorder[] = $row;
        }
        // В наличии → дешевле выше. Под заказ → раньше дата, при равенстве дешевле.
        usort($inStock, fn($a, $b) => $a['price_rub'] <=> $b['price_rub']);
        usort($preorder, function ($a, $b) {
            $da = (string)($a['delivery_ae'] ?? '9999-99-99');
            $db = (string)($b['delivery_ae'] ?? '9999-99-99');
            return $da === $db ? ($a['price_rub'] <=> $b['price_rub']) : strcmp($da, $db);
        });
        $ordered = array_merge($inStock, $preorder);
        if (!$ordered) return [];

        $khjDays = self::khjDays();
        $limit   = (int)getSetting('autoeuro_offers_limit', '3');
        if ($limit < 1) $limit = 1;

        $out  = [];
        $seen = [];
        foreach ($ordered as $row) {
            $price = self::applyKhjSurcharge(self::toSomoni($row['price_rub']));
            $total = self::addDays($row['delivery_ae'], $khjDays);
            // Дубли: одинаковая цена + одинаковый срок до Худжанда — незачем показывать.
            $key = $price . '|' . ($total ?? '');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = [
                'price'          => $price,
                'price_raw_rub'  => $row['price_rub'],
                'stock'          => $row['stock'],
                'in_stock'       => $row['stock'] > 0,
                'delivery_ae'    => $row['delivery_ae'],
                'delivery_days'  => $khjDays,
                'delivery_total' => $total,
                'name'           => $row['name'],
            ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    // ── Ценообразование и логистика Москва→Худжанд ───────────────────────────

    /**
     * Рубли AutoEuro → сомони. Цена AutoEuro в РУБЛЯХ; сайт показывает сомони
     * (курс TJS=1.0), поэтому переводим по autoeuro_rub_rate (сомони за 1 рубль)
     * и применяем наценку: отдельную autoeuro_markup, если задана, иначе общую
     * global_markup (у деталей поставщика нет своей карточки для наценки).
     */
    private static function toSomoni(float $priceRub): float
    {
        $rubRate = (float)str_replace(',', '.', getSetting('autoeuro_rub_rate', '0.11'));
        if ($rubRate <= 0) $rubRate = 1.0;
        $aeMk   = trim((string)getSetting('autoeuro_markup', ''));
        $markup = $aeMk !== '' ? (float)str_replace(',', '.', $aeMk) : (float)getSetting('global_markup', '0');
        return $priceRub * $rubRate * (1 + $markup / 100);
    }

    /**
     * Надбавка за доставку Москва→Худжанд (в API AutoEuro её нет — это наша
     * логистика). Тумблер autoeuro_khj_enabled; режим autoeuro_khj_mode:
     * 'percent' — процент от цены, иначе фикс. сумма в сомони (autoeuro_khj_value).
     * Итог округляем до 2 знаков. Выключено/ноль — цена без изменений.
     */
    public static function applyKhjSurcharge(float $somoni): float
    {
        if (getSetting('autoeuro_khj_enabled', '0') !== '1') return round($somoni, 2);
        $val = (float)str_replace(',', '.', getSetting('autoeuro_khj_value', '0'));
        if ($val <= 0) return round($somoni, 2);
        $somoni = getSetting('autoeuro_khj_mode', 'sum') === 'percent'
            ? $somoni * (1 + $val / 100)
            : $somoni + $val;
        return round($somoni, 2);
    }

    /** Наши дни доставки Москва→Худжанд (0, если надбавка выключена). */
    public static function khjDays(): int
    {
        if (getSetting('autoeuro_khj_enabled', '0') !== '1') return 0;
        return max(0, (int)getSetting('autoeuro_khj_days', '0'));
    }

    /** Прибавляет N дней к дате YYYY-MM-DD (дата прибытия в Худжанд). null → null. */
    private static function addDays(?string $date, int $days): ?string
    {
        if ($date === null || $date === '') return null;
        $dt = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
        if (!$dt) return null;
        if ($days > 0) $dt->modify('+' . $days . ' days');
        return $dt->format('Y-m-d');
    }

    /** Нормализует дату из AutoEuro в YYYY-MM-DD или null (иногда приходит с временем). */
    private static function normDate($v): ?string
    {
        if (!is_string($v) || $v === '') return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : null;
    }

    /**
     * Подменяет бренд по таблице `autoeuro_brand_map` (строки «ИСХОДНЫЙ = Замена»),
     * если для него задана замена. Сравнение без учёта регистра и лишних пробелов.
     */
    private static function mapBrand(string $brand): string
    {
        $raw = trim(getSetting('autoeuro_brand_map', ''));
        if ($raw === '') return $brand;
        $key = self::normBrandKey($brand);
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) continue;
            [$from, $to] = array_map('trim', explode('=', $line, 2));
            if ($from !== '' && $to !== '' && self::normBrandKey($from) === $key) {
                return $to;
            }
        }
        return $brand;
    }

    /** Пишет случай «цена не найдена» в лог (см. докблок класса). Сбой логирования не влияет на показ цены. */
    private static function logMiss(string $origBrand, string $queryBrand, string $oem, $rawResponse): void
    {
        try {
            $db = getDB();
            $db->exec("CREATE TABLE IF NOT EXISTS warehouse_api_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                action VARCHAR(80) NOT NULL DEFAULT '',
                request_url VARCHAR(500) NOT NULL DEFAULT '',
                response_code SMALLINT NOT NULL DEFAULT 0,
                response_body TEXT DEFAULT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $url = 'brand=' . $origBrand
                 . ($queryBrand !== $origBrand ? ' (→ ' . $queryBrand . ')' : '')
                 . '&code=' . $oem;
            $db->prepare("INSERT INTO warehouse_api_log (action, request_url, response_code, response_body, success, created_at) VALUES (?,?,0,?,0,NOW())")
               ->execute(['autoeuro_price_miss', mb_substr($url, 0, 500), mb_substr(json_encode($rawResponse, JSON_UNESCAPED_UNICODE), 0, 2000)]);
        } catch (Exception $e) { /* лог необязателен */ }
    }

    // ── Словарь названий «артикул → название» ────────────────────────────────
    // Наполняется БЕЗ нагрузки на AutoEuro: только именами, что и так пришли при
    // реальных поисках по артикулу (ленивое обучение) + засев из Tradesoft-схем.
    // Массовых запросов к AutoEuro нет → бан не грозит. Хранится только НАЗВАНИЕ
    // (артикул+бренд), цена НЕ хранится — она всегда живая из AutoEuro.

    /** Создаёт таблицу словаря при первом обращении (idempotent). */
    private static function ensureDictTable(PDO $db): void
    {
        static $done = false;
        if ($done) return;
        $db->exec("CREATE TABLE IF NOT EXISTS ae_part_dictionary (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            oem_key VARCHAR(64) NOT NULL,
            oem VARCHAR(64) NOT NULL,
            brand VARCHAR(120) NOT NULL DEFAULT '',
            name VARCHAR(255) NOT NULL DEFAULT '',
            source VARCHAR(20) NOT NULL DEFAULT 'search',
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_oem_brand (oem_key, brand),
            KEY idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    }

    /** Сохраняет «артикул(+бренд) → название» в словарь (upsert). Сбой не критичен. */
    public static function rememberName(string $oem, string $brand, ?string $name, string $source = 'search'): void
    {
        $oem   = trim($oem);
        $name  = trim((string)$name);
        $brand = trim($brand);
        if ($oem === '' || $name === '') return;
        $key = self::norm($oem);
        if ($key === '') return;
        try {
            $db = getDB();
            self::ensureDictTable($db);
            $db->prepare("INSERT INTO ae_part_dictionary (oem_key, oem, brand, name, source, hits)
                VALUES (?,?,?,?,?,1)
                ON DUPLICATE KEY UPDATE name=VALUES(name), oem=VALUES(oem),
                    hits=hits+1, updated_at=NOW()")
               ->execute([$key, $oem, $brand, mb_substr($name, 0, 255), $source]);
        } catch (Exception $e) { /* словарь необязателен */ }
    }

    /**
     * Поиск по словарю НАЗВАНИЙ (наша база, без AutoEuro). Возвращает позиции,
     * подходящие по названию или бренду — по ним цену подгрузим отдельно, живьём.
     * @return array<int,array{oem:string,brand:string,name:string}>
     */
    public static function dictionarySearch(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];

        // Совпадение ПО СЛОВАМ, а не по точной фразе: AutoEuro отдаёт названия в
        // своём порядке («Колодки Тормозные Дисковые»), поэтому запрос «тормозные
        // колодки» должен найти его по обоим словам в любом порядке. Каждое слово
        // (от 2 символов, максимум 5 слов) обязано встретиться в названии ИЛИ
        // бренде. Одно слово — как раньше, по подстроке.
        $words = array_filter(
            array_map('trim', preg_split('/\s+/u', $q) ?: []),
            static fn($w) => mb_strlen($w) >= 2
        );
        if (!$words) $words = [$q];
        $words = array_slice(array_values($words), 0, 5);

        $conds  = [];
        $params = [];
        foreach ($words as $w) {
            $conds[]  = '(name LIKE ? OR brand LIKE ?)';
            $like     = '%' . $w . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $where = implode(' AND ', $conds);

        try {
            $db = getDB();
            // Дедуп по артикулу: одно и то же название с разных брендов не плодим.
            $st = $db->prepare(
                "SELECT oem, MIN(brand) AS brand, name
                   FROM ae_part_dictionary
                  WHERE $where
               GROUP BY oem_key, name
               ORDER BY MAX(hits) DESC, MAX(updated_at) DESC
                  LIMIT " . (int)$limit
            );
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    private static function norm(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }

    private static function normBrandKey(string $s): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $s)));
    }
}
