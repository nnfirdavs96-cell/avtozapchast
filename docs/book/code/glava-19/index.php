<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Данные о магазине
$nazvanieMagazina = 'Автозапчасти Firdavs';
$gorod = 'Худжанд';

// Данные о товаре
$tovar = 'Тормозные колодки Bosch';
$artikul = '0986424815';
$cenaPostavshika = 185;
$nacenka = 1.35;
$ostatok = 7;

// Считаем цену — покупатель этого не увидит
$cena = round($cenaPostavshika * $nacenka);

// Определяем статус
if ($ostatok > 5) {
    $status = 'В наличии';
} elseif ($ostatok > 0) {
    $status = 'Осталось мало';
} else {
    $status = 'Под заказ';
}

// Текущая дата — её знает только сервер
$data = date('d.m.Y H:i');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $tovar ?> — <?= $nazvanieMagazina ?></title>
</head>
<body>
    <h1><?= $nazvanieMagazina ?></h1>
    <p><?= $gorod ?></p>

    <h2><?= $tovar ?></h2>
    <p>Артикул: <?= $artikul ?></p>
    <p>Цена: <strong><?= $cena ?></strong> сомони</p>
    <p>Наличие: <?= $status ?> (<?= $ostatok ?> шт.)</p>

    <hr>
    <p>Страница собрана сервером <?= $data ?></p>
</body>
</html>
