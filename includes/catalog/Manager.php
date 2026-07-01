<?php
/**
 * Фабрика + фасад провайдеров каталога. Единая точка, через которую сайт получает
 * активный провайдер. Выбор — по настройке `catalog_provider` (по умолчанию
 * 'partsapi', чтобы существующие установки работали как прежде).
 *
 * Использование:
 *   $prov = Catalog::provider();
 *   $prov->searchByVinCat($vin, $cat);
 *
 * Добавление нового провайдера = одна ветка в make() (для код-адаптеров) либо
 * запись профиля (Этап 2, GenericRestAdapter) — фронт и корзину не трогаем.
 */
require_once __DIR__ . '/Provider.php';
require_once __DIR__ . '/PartsApiAdapter.php';
require_once __DIR__ . '/MockAdapter.php';
require_once __DIR__ . '/CatalogProfiles.php';
require_once __DIR__ . '/GenericRestAdapter.php';

class Catalog
{
    private static ?CatalogProvider $current = null;

    /** Код-адаптеры (особая логика). Профили REST-сервисов добавляются отдельно. */
    private static function codeProviders(): array
    {
        return [
            'partsapi' => 'PartsAPI.ru',
            'partspc'  => 'Parts-Catalogs (OEM + схемы)',
            'laximo'   => 'Laximo (оригинал)',
            'mock'     => 'Демо (без ключа)',
        ];
    }

    /**
     * Список провайдеров для выпадающего списка: код-адаптеры + профили из реестра.
     * Профили (REST-сервисы) подключаются БЕЗ кода — через CatalogProfiles.
     */
    public static function available(): array
    {
        return self::codeProviders() + CatalogProfiles::options();
    }

    /** Активный провайдер (кэшируется в пределах запроса). */
    public static function provider(): CatalogProvider
    {
        if (self::$current === null) {
            self::$current = self::make(trim(getSetting('catalog_provider', 'partsapi')));
        }
        return self::$current;
    }

    /** Сбросить закэшированный провайдер (например, после смены настроек в админке). */
    public static function reset(): void
    {
        self::$current = null;
    }

    /** Слой цен (свой склад → AutoEuro). Каталог и цены независимы. */
    public static function price(): PriceProvider
    {
        require_once __DIR__ . '/PriceAggregator.php';
        return new PriceAggregator();
    }

    /**
     * Построить провайдер по id:
     *   • код-адаптеры (mock/partsapi) — особая логика;
     *   • иначе ищем профиль в реестре → универсальный GenericRestAdapter;
     *   • неизвестный id → безопасный дефолт (PartsAPI).
     */
    public static function make(string $id): CatalogProvider
    {
        if ($id === 'mock')     return new MockAdapter();
        if ($id === 'partsapi') return new PartsApiAdapter();
        if ($id === 'partspc')  { require_once __DIR__ . '/PartsCatalogsAdapter.php'; return new PartsCatalogsAdapter(); }
        if ($id === 'laximo')   { require_once __DIR__ . '/LaximoAdapter.php'; return new LaximoAdapter(); }
        $profile = CatalogProfiles::get($id);
        if ($profile !== null)  return new GenericRestAdapter($profile);
        return new PartsApiAdapter();
    }
}
