<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function e(string $t): string {
    return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}
function somoni(int $diram): string {
    return number_format($diram / 100, 2, '.', ' ');
}

// --- Каталог: так же придут данные из базы ---
$tovary = [
    ['id'=>1,'nazvanie'=>'Тормозные колодки Bosch','artikul'=>'0986424815','cena'=>25000,'ostatok'=>7, 'brend'=>'Bosch'],
    ['id'=>2,'nazvanie'=>'Масляный фильтр Mann',   'artikul'=>'W71280',    'cena'=>4500, 'ostatok'=>23,'brend'=>'Mann'],
    ['id'=>3,'nazvanie'=>'Свечи зажигания Denso',  'artikul'=>'IK20',      'cena'=>12000,'ostatok'=>0, 'brend'=>'Denso'],
    ['id'=>4,'nazvanie'=>'Тормозные диски Brembo', 'artikul'=>'09.9468.11','cena'=>78000,'ostatok'=>2, 'brend'=>'Brembo'],
    ['id'=>5,'nazvanie'=>'Воздушный фильтр Mann',  'artikul'=>'C25114',    'cena'=>6500, 'ostatok'=>14,'brend'=>'Mann'],
    ['id'=>6,'nazvanie'=>'Аккумулятор Bosch S4',   'artikul'=>'0092S40050','cena'=>95000,'ostatok'=>4, 'brend'=>'Bosch'],
    ['id'=>7,'nazvanie'=>'Салонный фильтр Mann',   'artikul'=>'CU2545',    'cena'=>5500, 'ostatok'=>9, 'brend'=>'Mann'],
];

// --- Фильтры (пока задаём прямо здесь, в главе 24 возьмём из формы) ---
$filtr_brend = 'Mann';
$tolko_v_nalichii = true;
$sortirovka = 'cena_vozr';

// --- Обработка ---
$spisok = $tovary;

if ($filtr_brend !== '') {
    $spisok = array_filter($spisok, fn($t) => $t['brend'] === $filtr_brend);
}
if ($tolko_v_nalichii) {
    $spisok = array_filter($spisok, fn($t) => $t['ostatok'] > 0);
}

// после array_filter ключи с дырками — выравниваем
$spisok = array_values($spisok);

match ($sortirovka) {
    'cena_vozr' => usort($spisok, fn($a, $b) => $a['cena'] <=> $b['cena']),
    'cena_ubyv' => usort($spisok, fn($a, $b) => $b['cena'] <=> $a['cena']),
    'nazvanie'  => usort($spisok, fn($a, $b) => strcmp($a['nazvanie'], $b['nazvanie'])),
    default     => null,
};

// --- Сводка по всему каталогу ---
$vse_brendy = array_values(array_unique(array_column($tovary, 'brend')));
sort($vse_brendy);

$vsego_shtuk = array_sum(array_column($tovary, 'ostatok'));
$stoimost_sklada = array_reduce(
    $tovary,
    fn($itog, $t) => $itog + $t['cena'] * $t['ostatok'],
    0
);
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
            Бренды: <?= e(implode(', ', $vse_brendy)) ?> ·
            всего на складе <?= $vsego_shtuk ?> шт. на
            <?= somoni($stoimost_sklada) ?> сомони
        </p>

        <p class="schetchik">
            Фильтр: <strong><?= e($filtr_brend ?: 'все бренды') ?></strong>,
            <?= $tolko_v_nalichii ? 'только в наличии' : 'все' ?> ·
            найдено <?= count($spisok) ?>
        </p>

        <div class="katalog">
            <?php foreach ($spisok as $t): ?>
                <article class="tovar">
                    <h3><?= e($t['nazvanie']) ?></h3>
                    <p class="artikul">Артикул: <?= e($t['artikul']) ?></p>
                    <p class="cena"><?= somoni($t['cena']) ?> <span>сомони</span></p>
                    <p class="nalichie">В наличии: <?= $t['ostatok'] ?> шт.</p>
                    <button>В корзину</button>
                </article>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>
