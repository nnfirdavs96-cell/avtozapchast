-- Гранулярные права на конкретного пользователя.
-- Суперадмин раздаёт admin/manager доступ к разделам
-- (Товары, Наценки, Блог, Слайдер, Склад API и т.д.).
--
-- Запуск: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_permissions.sql
--
-- ОТСУТСТВИЕ строки для пользователя = БЕЗ ограничений (как сейчас).
-- Появляется строка только когда суперадмин явно настроил пользователя.

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id`    INT UNSIGNED NOT NULL,
  `sections`   TEXT         NOT NULL COMMENT 'JSON-массив разрешённых ключей разделов',
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_permissions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'user_permissions ready' AS status;
