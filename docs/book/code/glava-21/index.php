<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function e(string $t): string {
    return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}

// --- Данные ---
$tovary = [
    ['nazvanie' => 'Тормозные колодки Bosch', 'zakup' => 18500, 'ostatok' => 7,  'status' => 'hit'],
    ['nazvanie' => 'Масляный фильтр Mann',    'zakup' => 3300,  'ostatok' => 23, 'status' => 'obychny'],
    ['nazvanie' => 'Свечи зажигания Denso',   'zakup' => 8900,  'ostatok' => 0,  'status' => 'obychny'],
    ['nazvanie' => 'Тормозные диски Brembo',  'zakup' => 57800, 'ostatok' => 2,  'status' => 'akciya'],
    ['nazvanie' => 'Воздушный фильтр Mann',   'zakup' => 4800,  'ostatok' => 14, 'status' => 'obychny'],
    ['nazvanie' => 'Аккумулятор Bosch S4',    'zakup' => 70400, 'ostatok' => 4,  'status' => 'hit'],
];

// --- Логика: считаем цены и статусы ---
$nacenka = 1.35;
$gotovye = [];

foreach ($tovary as $t) {
    $cena_diram = (int) round($t['zakup'] * $nacenka);

    // Акционным — минус 15%
    if ($t['status'] === 'akciya') {
        $cena_diram = (int) round($cena_diram * 0.85);
    }

    // Подпись наличия
    if ($t['ostatok'] === 0) {
        $nalichie = 'Под заказ, 5 дней';
        $klass_nalichiya = 'nety';
    } elseif ($t['ostatok'] <= 3) {
        $nalichie = "Осталось {$t['ostatok']} шт.";
        $klass_nalichiya = 'malo';
    } else {
        $nalichie = "В наличии: {$t['ostatok']} шт.";
        $klass_nalichiya = '';
    }

    // Метка — match вместо трёх if
    $metka = match ($t['status']) {
        'hit'    => 'Хит продаж',
        'akciya' => 'Акция −15%',
        default  => '',
    };

    $gotovye[] = [
        'nazvanie'  => $t['nazvanie'],
        'cena'      => number_format($cena_diram / 100, 2, '.', ' '),
        'nalichie'  => $nalichie,
        'klass'     => $klass_nalichiya,
        'metka'     => $metka,
        'mozhno_kupit' => $t['ostatok'] > 0,
    ];
}

$vsego_shtuk = 0;
foreach ($tovary as $t) {
    $vsego_shtuk += $t['ostatok'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Каталог</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header>
        <div class="logo">Автозапчасти Firdavs</div>
    </header>

    <main>
        <p class="schetchik">
            Позиций: <?= count($gotovye) ?> · всего на складе: <?= $vsego_shtuk ?> шт.
        </p>

        <div class="katalog">
            <?php foreach ($gotovye as $t): ?>
                <article class="tovar">
                    <?php if ($t['metka'] !== ''): ?>
                        <span class="metka"><?= e($t['metka']) ?></span>
                    <?php endif; ?>

                    <h3><?= e($t['nazvanie']) ?></h3>
                    <p class="cena"><?= $t['cena'] ?> <span>сомони</span></p>
                    <p class="nalichie <?= $t['klass'] ?>"><?= e($t['nalichie']) ?></p>

                    <?php if ($t['mozhno_kupit']): ?>
                        <button>В корзину</button>
                    <?php else: ?>
                        <button disabled>Нет в наличии</button>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>
