<?php
/**
 * Тип техники у товара: легковой / грузовой / коммерческий / мото / спецтехника.
 *
 * Запрос клиента — «обязательно грузовые авто». Делаем это отдельной классификацией
 * товара (колонка parts.vehicle_type), чтобы продавец помечал, для какой техники
 * запчасть, а покупатель фильтровал — в т.ч. открывал раздел «Грузовые».
 *
 * Колонка, а не атрибут: это сквозная классификация (нужна и в каталоге, и в
 * поиске), по ней удобнее и быстрее фильтровать, чем через part_attributes.
 *
 * Запуск (повторный безопасен):
 *   php sql/migrate_vehicle_type.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Только из командной строки.\n"); }

require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

echo "Тип техники товара (легковой/грузовой/...)\n";
echo "База: " . DB_NAME . "\n";
echo str_repeat('-', 60) . "\n";

dbAddColumnIfMissing($db, 'parts', 'vehicle_type',
    "`vehicle_type` VARCHAR(16) DEFAULT NULL COMMENT 'car,truck,commercial,moto,special' AFTER `category_id`");
echo "  [OK] parts.vehicle_type\n";

dbAddIndexIfMissing($db, 'parts', 'idx_vehicle_type',
    "KEY `idx_vehicle_type` (`vehicle_type`)");
echo "  [OK] индекс idx_vehicle_type\n";

echo str_repeat('-', 60) . "\n";
echo "Готово. Продавцы указывают тип техники у товара, покупатели фильтруют.\n";
echo "Раздел грузовых: /catalog/index.php?vehicle=truck\n";
