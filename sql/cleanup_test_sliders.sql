-- Очистка тестовых слайдеров (постеры фильмов, "ТЕСТ" и т.п.)
-- Запуск: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/cleanup_test_sliders.sql

-- Вариант 1: деактивировать все текущие слайдеры (мягко — данные останутся)
UPDATE sliders SET is_active = 0;

-- Вариант 2: удалить ВСЕ слайды (раскомментировать если нужно)
-- DELETE FROM sliders;

-- После этого зайдите в /superadmin/index.php или /admin/sliders.php
-- и добавьте корректные слайды с авто-тематикой.
