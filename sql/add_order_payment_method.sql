-- Добавляет в таблицу orders колонку payment_method. Идемпотентно.
-- Код buyer/checkout.php пишет эту колонку при оформлении заказа;
-- без неё INSERT падал и заказ не оформлялся. Можно запускать многократно.

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_method');
SET @s := IF(@c = 0,
  'ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) NOT NULL DEFAULT ''cash_on_delivery'' AFTER `notes`',
  'SELECT "payment_method exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
