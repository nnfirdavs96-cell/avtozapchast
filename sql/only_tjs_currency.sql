-- Оставить только таджикскую валюту (TJS)
-- Run: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/only_tjs_currency.sql

-- Деактивировать все валюты кроме TJS
UPDATE currencies SET is_active = 0 WHERE code != 'TJS';

-- TJS — активна, по умолчанию, символ СМН
UPDATE currencies SET is_active = 1, is_default = 1, symbol = 'СМН' WHERE code = 'TJS';

-- Проверка
SELECT code, name_ru, symbol, is_active, is_default FROM currencies ORDER BY is_default DESC;
