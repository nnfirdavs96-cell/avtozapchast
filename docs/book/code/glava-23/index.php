<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$zagolovok = 'Каталог — ' . SAIT_NAZVANIE;
$tovary = [
    ['id' => 1, 'nazvanie' => 'Тормозные колодки Bosch', 'zakup' => 18500],
    ['id' => 2, 'nazvanie' => 'Масляный фильтр Mann',    'zakup' => 3300],
    ['id' => 3, 'nazvanie' => 'Свечи зажигания Denso',   'zakup' => 8900],
    ['id' => 4, 'nazvanie' => 'Тормозные диски Brembo',  'zakup' => 57800],
];

require __DIR__ . '/includes/header.php';
?>

<h1>Каталог запчастей</h1>

<div class="katalog">
    <?php foreach ($tovary as $t): ?>
        <article class="tovar">
            <h3><?= e($t['nazvanie']) ?></h3>
            <p class="cena"><?= somoni(cena_prodazhi($t['zakup'])) ?> сомони</p>
        </article>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
