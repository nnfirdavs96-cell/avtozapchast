-- Переименование AvtoDoc → AutoDoc в настройках сайта
UPDATE settings SET value = REPLACE(value, 'AvtoDoc', 'AutoDoc') WHERE value LIKE '%AvtoDoc%';
UPDATE settings SET value = REPLACE(value, 'AVTODOC', 'AUTODOC') WHERE value LIKE '%AVTODOC%';
