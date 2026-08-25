-- ═══════════════════════════════════════════════════════════════════════════
-- Маркетплейс, Фаза 4 — отзывы о ПРОДАВЦАХ.
--
-- Что уже было и почему не подходит:
--   `product_reviews` — отзыв о ТОВАРЕ (привязан к parts.id);
--   `shop_reviews`    — отзыв о НАШЕМ магазине в целом, один на пользователя.
-- Ни один не отвечает на вопрос «каков этот продавец»: вовремя ли отправляет,
-- соответствует ли товар описанию. Без такой оценки покупателю не на что опереться
-- при выборе между предложениями, а честному продавцу нечем отличаться от небрежного.
--
-- Отзыв привязан к ВЫПОЛНЕННОМУ ПОДЗАКАЗУ, а не просто к продавцу. Это и есть
-- защита от накрутки: оценить можно только то, что тебе реально доставили. Купил
-- у продавца трижды — оставишь три отзыва, по одному на заказ.
--
-- Запуск:  php sql/apply.php sql/marketplace_phase4_reviews.sql
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `seller_reviews` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id`       INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  -- Подзаказ, за который оставлен отзыв. Он же — доказательство покупки.
  `order_seller_id` INT UNSIGNED NOT NULL,
  `rating`          TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `comment`         TEXT         DEFAULT NULL,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reply`           VARCHAR(500) DEFAULT NULL COMMENT 'ответ продавца на отзыв',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Один отзыв на один выполненный заказ: иначе один покупатель мог бы
  -- заминусовать продавца десять раз подряд после единственной покупки.
  UNIQUE KEY `uk_order_seller` (`order_seller_id`),
  KEY `idx_seller_status` (`seller_id`, `status`),
  KEY `idx_user`          (`user_id`),
  CONSTRAINT `fk_srev_seller` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_srev_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
