-- Доставка по городам Таджикистана (способ: такси). Идемпотентно.
-- cost = 0  → цена ещё не задана: на витрине показывается «Уточняется»,
--             к сумме заказа не прибавляется (нет договора с такси).
-- cost > 0  → фиксированная стоимость по городу, прибавляется к сумме.
-- Можно запускать многократно.

CREATE TABLE IF NOT EXISTS `delivery_zones` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `city`          VARCHAR(120)  NOT NULL,
  `cost`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_days` VARCHAR(40)   DEFAULT NULL,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order`    INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Колонка стоимости доставки в заказе (хранится в сомони, как и цены товаров).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_cost');
SET @s := IF(@c = 0,
  'ALTER TABLE `orders` ADD COLUMN `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`',
  'SELECT "shipping_cost exists" AS info');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Засев основных городов (цена 0 = уточняется; лишние можно отключить в админке).
INSERT IGNORE INTO `delivery_zones` (`city`, `cost`, `is_active`, `sort_order`) VALUES
  ('Душанбе',     0.00, 1, 1),
  ('Худжанд',     0.00, 1, 2),
  ('Бохтар',      0.00, 1, 10),
  ('Куляб',       0.00, 1, 11),
  ('Истаравшан',  0.00, 1, 12),
  ('Турсунзаде',  0.00, 1, 13),
  ('Канибадам',   0.00, 1, 14),
  ('Исфара',      0.00, 1, 15),
  ('Пенджикент',  0.00, 1, 16),
  ('Вахдат',      0.00, 1, 17),
  ('Хорог',       0.00, 1, 18),
  ('Гиссар',      0.00, 1, 19),
  ('Яван',        0.00, 1, 20),
  ('Нурек',       0.00, 1, 21),
  ('Дангара',     0.00, 1, 22),
  ('Истиклол',    0.00, 1, 23),
  ('Рашт',        0.00, 1, 24);
