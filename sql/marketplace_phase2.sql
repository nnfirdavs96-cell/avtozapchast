-- ═══════════════════════════════════════════════════════════════════════════
-- Маркетплейс, Фаза 2 — заказы с разбивкой по продавцам.
--
-- Проблема, которую решаем: заказ был плоским (`orders` + `order_items`), без
-- какой-либо связи с продавцом. Покупатель мог положить в корзину товары трёх
-- продавцов — получался ОДИН заказ с ОДНИМ статусом, и ни один продавец не видел
-- свою часть и не мог ею управлять.
--
-- Модель (как у Ozon/WB): заказ покупателя остаётся один, но внутри делится на
-- ПОДЗАКАЗЫ — по одному на каждого продавца. У подзаказа свой статус, своя сумма
-- и своя комиссия.
--
--   orders            заказ покупателя (оплата, адрес, общий итог)
--     └─ order_sellers   подзаказ продавца (статус, сумма, комиссия, выплата)
--          └─ order_items   позиции этого подзаказа
--
-- Только ДОБАВЛЕНИЯ: старые заказы остаются валидными (seller_id/order_seller_id
-- у них NULL) и продолжают отображаться как раньше.
-- Запуск один раз:  mysql -u USER -p БАЗА < sql/marketplace_phase2.sql
-- ═══════════════════════════════════════════════════════════════════════════

-- 1) Подзаказы: по одному на каждого продавца внутри заказа.
--    seller_id = NULL  → позиции НАШЕГО каталога (parts.seller_id IS NULL).
--    Комиссию фиксируем В МОМЕНТ ЗАКАЗА (commission_percent), чтобы поздняя
--    правка ставки продавца не переписывала историю уже оформленных заказов.
CREATE TABLE IF NOT EXISTS `order_sellers` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`           INT UNSIGNED  NOT NULL,
  `seller_id`          INT UNSIGNED  DEFAULT NULL,
  `status`             ENUM('pending','processing','shipped','delivered','cancelled')
                       NOT NULL DEFAULT 'pending',
  `subtotal`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `commission_percent` DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `commission_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payout_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `note`               VARCHAR(255)  DEFAULT NULL,
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order`  (`order_id`),
  KEY `idx_seller` (`seller_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_os_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Позиции заказа знают своего продавца и свой подзаказ.
--    seller_id дублируется намеренно: он копируется из parts В МОМЕНТ ЗАКАЗА,
--    поэтому смена владельца товара позже не переписывает прошлые заказы.
ALTER TABLE `order_items`
  ADD COLUMN `seller_id`       INT UNSIGNED DEFAULT NULL AFTER `part_id`,
  ADD COLUMN `order_seller_id` INT UNSIGNED DEFAULT NULL AFTER `seller_id`,
  ADD KEY `idx_oi_seller`       (`seller_id`),
  ADD KEY `idx_oi_order_seller` (`order_seller_id`);
