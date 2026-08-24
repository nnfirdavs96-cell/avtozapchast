<?php
declare(strict_types=1);
require __DIR__ . '/log.php';

@unlink(LOG_FAYL);

// Обычная жизнь магазина: заказ прошёл
log_info('Заказ оформлен', ['zakaz_id' => 1043, 'summa' => 132500, 'pokupatel' => 17]);

sleep(2);

// Что-то подозрительное, но работать можно
log_vnimanie('Поставщик отвечает медленно', ['ms' => 4210, 'artikul' => 'W71480']);

sleep(1);

// Настоящая беда
try {
    throw new RuntimeException('Не удалось подключиться к базе');
} catch (Throwable $e) {
    log_oshibka('Ошибка при оформлении заказа', [
        'soobshchenie' => $e->getMessage(),
        'fayl'         => basename($e->getFile()) . ':' . $e->getLine(),
        'pokupatel'    => 17,
    ]);
}

sleep(3);

// Попытка входа с чужим паролем — это тоже событие
log_vnimanie('Неверный пароль при входе', ['email' => 'ali@example.tj', 'ip' => '89.108.***.***']);

sleep(1);
log_info('Курс обновлён', ['bylo' => 1.18, 'stalo' => 1.21]);

echo file_get_contents(LOG_FAYL);
