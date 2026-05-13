-- ============================================================
-- Миграция: Таджикский рынок
-- Запуск: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_tajik_market.sql
-- ============================================================

-- 1. Валюта по умолчанию: Сомони (TJS)
UPDATE currencies SET is_default = 0;
UPDATE currencies SET is_default = 1 WHERE code = 'TJS';

-- Актуальный курс: 1 RUB ≈ 0.115 TJS (май 2026)
UPDATE currencies SET rate = 0.115000 WHERE code = 'TJS';
UPDATE currencies SET rate = 1.000000 WHERE code = 'RUB';
UPDATE currencies SET rate = 0.011000 WHERE code = 'USD';

-- 2. Язык по умолчанию: Таджикский
UPDATE site_settings SET value = 'tg'  WHERE `key` = 'default_lang';
UPDATE site_settings SET value = 'TJS' WHERE `key` = 'default_currency';

-- 3. Адрес и контактные данные
UPDATE site_settings SET value = 'г. Худжанд, 19 мкр, дом 30'  WHERE `key` = 'site_address';
UPDATE site_settings SET value = '+992 92 646-46-46'             WHERE `key` = 'site_phone';
UPDATE site_settings SET value = 'АвтоЗапчасть'                  WHERE `key` = 'site_name';

-- 4. SEO: мета под таджикский рынок
UPDATE site_settings SET value = 'АвтоЗапчасть — Ҳуҷанд. Қисмҳои автомобилӣ бо сифати баланд ва нархи мувофиқ.' WHERE `key` = 'meta_description';
UPDATE site_settings SET value = 'автозапчасти, запчасти, Худжанд, Таджикистан, авто, двигатель' WHERE `key` = 'meta_keywords';

-- Проверка результата
SELECT `key`, value FROM site_settings
WHERE `key` IN ('default_lang','default_currency','site_address','site_name','meta_description');

SELECT code, symbol, rate, is_default FROM currencies ORDER BY is_default DESC;
