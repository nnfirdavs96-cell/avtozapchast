<?php
/**
 * Адаптер PartsAPI.ru — оборачивает существующий рабочий класс CatalogApi в единый
 * интерфейс CatalogProvider. Поведение сайта при провайдере 'partsapi' не меняется
 * ни на байт: все вызовы делегируются как есть. Это «нулевой риск» Этапа 1.
 */
require_once __DIR__ . '/Provider.php';
require_once __DIR__ . '/../catalog_api.php';

class PartsApiAdapter implements CatalogProvider
{
    public function id(): string    { return 'partsapi'; }
    public function title(): string { return 'PartsAPI.ru'; }

    public function enabled(): bool { return CatalogApi::enabled(); }
    public function hasKey(): bool  { return CatalogApi::hasKey(); }

    public function searchByVin(string $vin, bool $useCache = true): array
    {
        return CatalogApi::searchByVin($vin, $useCache);
    }

    public function searchByVinCat(string $vin, int $cat, bool $useCache = true): array
    {
        return CatalogApi::searchByVinCat($vin, $cat, $useCache);
    }

    public function oemNodes(): array
    {
        return CatalogApi::oemNodes();
    }

    public function crossesWithWarehouse(string $article, string $brand = ''): array
    {
        return CatalogApi::crossesWithWarehouse($article, $brand);
    }

    public function testConnection(string $vin = ''): array
    {
        return CatalogApi::testConnection($vin);
    }

    public function clearCache(): void
    {
        CatalogApi::clearCache();
    }
}
