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

class Catalog
{
    private static ?CatalogProvider $current = null;

    /** Список провайдеров для выпадающего списка в админке: id => название. */
    public static function available(): array
    {
        return [
            'partsapi' => 'PartsAPI.ru',
            'mock'     => 'Демо (без ключа)',
            // 'umapi'  => 'UMAPI',   // Этап 2/3 — через профиль GenericRestAdapter
            // 'laximo' => 'Laximo',  // Этап 4 — код-адаптер
        ];
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

    /** Построить провайдер по id. Неизвестный id → безопасный дефолт (PartsAPI). */
    public static function make(string $id): CatalogProvider
    {
        switch ($id) {
            case 'mock':     return new MockAdapter();
            case 'partsapi': return new PartsApiAdapter();
            default:         return new PartsApiAdapter();
        }
    }
}
