<?php

/** Экранирование для безопасного вывода. Короткое имя — потому что используется всюду. */
function e(?string $text): string {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/** Дирамы в читаемые сомони. Деньги внутри системы всегда целые. */
function somoni(int $diram): string {
    return number_format($diram / 100, 2, '.', ' ');
}

/** Цена для покупателя из закупочной. */
function cena_prodazhi(int $zakup_diram): int {
    return (int) round($zakup_diram * NACENKA);
}

/** Доставка: бесплатно от определённой суммы. */
function cena_dostavki(int $summa_diram): int {
    return $summa_diram >= BESPLATNO_OT_DIRAM ? 0 : DOSTAVKA_DIRAM;
}

/** Правильное окончание: 1 товар, 2 товара, 5 товаров. */
function sklonenie(int $n, string $odin, string $dva, string $mnogo): string {
    $n = abs($n) % 100;
    if ($n >= 11 && $n <= 19) return $mnogo;
    $n = $n % 10;
    if ($n === 1) return $odin;
    if ($n >= 2 && $n <= 4) return $dva;
    return $mnogo;
}

/** Найти товар по id. Возвращает null, если не нашёлся. */
function nayti_tovar(array $tovary, int $id): ?array {
    foreach ($tovary as $t) {
        if ($t['id'] === $id) return $t;
    }
    return null;
}
