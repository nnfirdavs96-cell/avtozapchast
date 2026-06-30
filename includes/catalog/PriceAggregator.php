<?php
/**
 * Слой цен: свой склад → (если нет) AutoEuro. Свой склад приоритетен; AutoEuro
 * подключается, только когда включён тумблер `catalog_price_autoeuro` И настроен
 * AutoEuro. Дорогой внешний вызов кэшируется в catalog_price_cache, чтобы не
 * дёргать поставщика повторно (склад из БД быстрый — его не кэшируем).
 */
require_once __DIR__ . '/PriceProvider.php';
require_once __DIR__ . '/WarehousePriceProvider.php';
require_once __DIR__ . '/AutoEuroPriceProvider.php';

class PriceAggregator implements PriceProvider
{
    private const FOUND_TTL = 21600; // 6 ч для найденной цены
    private const NULL_TTL  = 3600;  // 1 ч для «не найдено» (не молотить AutoEuro)

    public function id(): string { return 'aggregator'; }

    /** Включён ли AutoEuro-фолбэк (для фронта: показывать ли ленивую подгрузку). */
    public static function autoeuroEnabled(): bool
    {
        return getSetting('catalog_price_autoeuro', '0') === '1';
    }

    public function priceByOem(string $oem, string $brand = ''): ?array
    {
        $oem = trim($oem);
        if ($oem === '') return null;

        // 1) Свой склад — приоритет, без кэша (БД быстрая, наличие свежее).
        $w = (new WarehousePriceProvider())->priceByOem($oem, $brand);
        if ($w !== null) return $w;

        // 2) AutoEuro — только если включено; результат кэшируем.
        if (!self::autoeuroEnabled()) return null;

        $ck     = self::norm($oem) . '|' . mb_strtolower(trim($brand));
        $cached = self::cacheGet($ck);
        if ($cached !== null) return $cached['found'] ? $cached['data'] : null;

        $a = (new AutoEuroPriceProvider())->priceByOem($oem, $brand);
        self::cacheSet($ck, $a);
        return $a;
    }

    private static function norm(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }

    // ── Кэш (создаётся на лету) ──────────────────────────────────────────────

    private static function ensureSchema(): void
    {
        static $done = false;
        if ($done) return;
        try {
            getDB()->exec(
                "CREATE TABLE IF NOT EXISTS catalog_price_cache (
                    ck VARCHAR(96) NOT NULL PRIMARY KEY,
                    result MEDIUMTEXT NOT NULL,
                    cached_at DATETIME NOT NULL
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $done = true;
        } catch (Exception $e) { /* кэш необязателен */ }
    }

    private static function cacheGet(string $ck): ?array
    {
        self::ensureSchema();
        try {
            $st = getDB()->prepare("SELECT result, cached_at FROM catalog_price_cache WHERE ck = ?");
            $st->execute([$ck]);
            $row = $st->fetch();
            if (!$row) return null;
            $data = json_decode($row['result'], true);
            if (!is_array($data)) return null;
            $age = time() - strtotime($row['cached_at']);
            $ttl = !empty($data['found']) ? self::FOUND_TTL : self::NULL_TTL;
            if ($age > $ttl) return null;
            return $data;
        } catch (Exception $e) { return null; }
    }

    private static function cacheSet(string $ck, ?array $found): void
    {
        self::ensureSchema();
        try {
            $payload = json_encode(['found' => $found !== null, 'data' => $found], JSON_UNESCAPED_UNICODE);
            getDB()->prepare(
                "INSERT INTO catalog_price_cache (ck, result, cached_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE result = VALUES(result), cached_at = NOW()"
            )->execute([$ck, $payload]);
        } catch (Exception $e) { /* ignore */ }
    }

    public static function clearCache(): void
    {
        try { getDB()->exec("DELETE FROM catalog_price_cache"); } catch (Exception $e) {}
    }
}
