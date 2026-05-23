-- Переименование сайта: АвтоЗапчасть → AvtoDoc
-- Обновляет сохранённое в БД название (site_settings перекрывает значение по умолчанию из lang).
UPDATE site_settings SET value = 'AvtoDoc' WHERE `key` = 'site_name';
