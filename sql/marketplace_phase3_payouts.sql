-- ═══════════════════════════════════════════════════════════════════════════
-- Маркетплейс, Фаза 3 (половина «деньги наружу») — балансы и выплаты продавцам.
--
-- Проблема, которую решаем: Фаза 2 научилась считать комиссию и сумму к выплате
-- по каждому подзаказу, но эти цифры нигде не накапливались. Продавец не видел,
-- сколько ему должны, а у владельца не было реестра «кому сколько выплачено».
-- Расчёты велись бы на бумаге — при живых продавцах это первый источник споров.
--
-- Модель — ЖУРНАЛ (ledger), а не изменяемое поле «баланс»:
--
--   seller_ledger    каждая строка — одно движение денег (+начисление,
--                    −сторно, −выплата). Баланс = SUM(amount).
--   seller_payouts   факт выплаты: сколько, чем, когда, кто провёл.
--
-- Почему журнал, а не колонка `balance`: колонку можно испортить двойным
-- начислением или гонкой, и потом невозможно доказать, откуда взялась цифра.
-- Журнал воспроизводим — баланс всегда пересчитывается из истории.
--
-- Эквайринг здесь НЕ нужен: заказы уже оплачиваются при получении, деньги у
-- площадки, вопрос только в том, чтобы честно рассчитаться с продавцом.
--
-- Только ДОБАВЛЕНИЯ — существующие таблицы не трогаем.
-- Запуск один раз:  mysql -u USER -p БАЗА < sql/marketplace_phase3_payouts.sql
-- ═══════════════════════════════════════════════════════════════════════════

-- 1) Журнал движений по продавцу.
--
--    type:
--      earning    +  заработано по доставленному подзаказу (payout_amount)
--      reversal   −  сторно, если доставленный подзаказ потом отменили
--      payout     −  выплата продавцу (привязана к seller_payouts)
--      adjustment ±  ручная правка владельцем (штраф, компенсация, спор)
--
--    amount ХРАНИМ СО ЗНАКОМ: баланс — это буквально SUM(amount). Никаких
--    «а тут прибавить, а тут отнять» по типу записи в коде.
--
--    UNIQUE (order_seller_id, type) — защита от двойного начисления. Продавец
--    может щёлкнуть статусом «доставлен» дважды или вернуть заказ в «отправлен»
--    и снова в «доставлен»; вставка просто не пройдёт, а не удвоит долг.
--    У payout/adjustment order_seller_id = NULL, а MySQL разрешает сколько
--    угодно NULL в UNIQUE-индексе — их это ограничение не касается.
CREATE TABLE IF NOT EXISTS `seller_ledger` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `seller_id`       INT UNSIGNED  NOT NULL,
  `order_seller_id` INT UNSIGNED  DEFAULT NULL,
  `payout_id`       INT UNSIGNED  DEFAULT NULL,
  `type`            ENUM('earning','reversal','payout','adjustment') NOT NULL,
  `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `note`            VARCHAR(255)  DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_os_type` (`order_seller_id`, `type`),
  KEY `idx_seller`  (`seller_id`),
  KEY `idx_payout`  (`payout_id`),
  KEY `idx_type`    (`type`),
  CONSTRAINT `fk_sl_seller` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Реестр выплат: что владелец фактически отдал продавцу.
--    `method` — как заплатили. Способы намеренно широкие: в Таджикистане это
--    чаще перевод на карту или наличные, а не банковский платёж.
--    `reference` — номер перевода/чека, чтобы выплату можно было найти в банке.
--    `period_from`/`period_to` — за какой период рассчитались (для сверки).
CREATE TABLE IF NOT EXISTS `seller_payouts` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `seller_id`   INT UNSIGNED  NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `method`      ENUM('card','cash','bank','mobile','other') NOT NULL DEFAULT 'card',
  `reference`   VARCHAR(120)  DEFAULT NULL,
  `period_from` DATE          DEFAULT NULL,
  `period_to`   DATE          DEFAULT NULL,
  `note`        VARCHAR(255)  DEFAULT NULL,
  `created_by`  INT UNSIGNED  DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seller` (`seller_id`),
  KEY `idx_date`   (`created_at`),
  CONSTRAINT `fk_sp_seller` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
