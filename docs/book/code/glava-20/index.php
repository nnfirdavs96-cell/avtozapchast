<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function e(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Данные (потом придут из базы)
$tovar = 'Тормозные колодки Bosch';
$artikul = '0986424815';
$brend = 'Bosch';

// Деньги — в дирамах, целыми
$cena_postavshika_diram = 18500;
$nacenka_procent = 35;
$dostavka_diram = 1200;

$ostatok = 7;
$kolichestvo = 3;

// Считаем
$cena_diram = (int) round($cena_postavshika_diram * (1 + $nacenka_procent / 100));
$cena_diram += $dostavka_diram;
$itogo_diram = $cena_diram * $kolichestvo;

// Скидка от трёх штук
$skidka_diram = 0;
if ($kolichestvo >= 3) {
    $skidka_diram = (int) round($itogo_diram * 0.10);
    $itogo_diram -= $skidka_diram;
}

// Для показа — переводим в сомони
function somoni(int $diram): string {
    return number_format($diram / 100, 2, '.', ' ');
}

$status = $ostatok > 5 ? 'В наличии' : ($ostatok > 0 ? 'Осталось мало' : 'Под заказ');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= e($tovar) ?></title>
</head>
<body>
    <h1><?= e($tovar) ?></h1>
    <p>Бренд: <?= e($brend) ?> · Артикул: <?= e($artikul) ?></p>
    <p>Наличие: <?= $status ?> (<?= $ostatok ?> шт.)</p>

    <h2>Расчёт</h2>
    <p>Цена за штуку: <?= somoni($cena_diram) ?> сомони</p>
    <p>Количество: <?= $kolichestvo ?> шт.</p>
    <?php if ($skidka_diram > 0): ?>
        <p>Скидка за объём: −<?= somoni($skidka_diram) ?> сомони</p>
    <?php endif; ?>
    <p><strong>К оплате: <?= somoni($itogo_diram) ?> сомони</strong></p>
</body>
</html>
