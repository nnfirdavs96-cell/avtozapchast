-- ============================================================
-- Миграция: Система наценки товаров
-- Запуск: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_markup.sql
-- ============================================================

-- 1. Добавить себестоимость и наценку в таблицу товаров
ALTER TABLE `parts`
  ADD COLUMN IF NOT EXISTS `cost_price`     DECIMAL(10,2)  DEFAULT NULL   COMMENT 'Себестоимость (закупочная цена)',
  ADD COLUMN IF NOT EXISTS `markup_percent` DECIMAL(5,2)   DEFAULT NULL   COMMENT 'Наценка % (приоритет: товар > категория > глобальная)';

-- 2. Добавить наценку по умолчанию для категории
ALTER TABLE `categories`
  ADD COLUMN IF NOT EXISTS `markup_percent` DECIMAL(5,2) DEFAULT NULL COMMENT 'Наценка % для всех товаров категории';

-- 3. Глобальная наценка по умолчанию в настройках
INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES ('global_markup', '0');

-- Проверка
SELECT 'parts columns' AS tbl, COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND COLUMN_NAME IN ('cost_price','markup_percent');

SELECT 'categories columns' AS tbl, COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'
    AND COLUMN_NAME = 'markup_percent';

SELECT `key`, `value` FROM site_settings WHERE `key` = 'global_markup';
