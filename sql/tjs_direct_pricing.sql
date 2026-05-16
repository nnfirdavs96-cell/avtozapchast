-- Прямое ценообразование в сомони (СМН/TJS) — без пересчёта из рублей.
-- Сайт ориентирован только на таджикский рынок: цена, введённая в админке,
-- = цена на витрине 1:1 (никакого ×0.115).
--
-- Запуск: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/tjs_direct_pricing.sql

-- Курс TJS = 1.0 → formatPrice() показывает сохранённое число как есть в СМН
UPDATE currencies SET rate = 1.000000, is_active = 1, is_default = 1, symbol = 'СМН'
 WHERE code = 'TJS';

-- На всякий случай — все прочие валюты неактивны
UPDATE currencies SET is_active = 0 WHERE code != 'TJS';

-- Валюта по умолчанию в настройках
INSERT INTO site_settings (`key`, `value`) VALUES ('default_currency', 'TJS')
ON DUPLICATE KEY UPDATE `value` = 'TJS';

-- Проверка
SELECT code, symbol, rate, is_active, is_default FROM currencies ORDER BY is_default DESC;
