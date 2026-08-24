<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$zapros = trim($_GET['q'] ?? '');
$naydeno = [];

if (mb_strlen($zapros) >= 2) {
    $tochno = $zapros; $nachalo = $zapros . '%'; $vezde = '%' . $zapros . '%';
    $naydeno = zapros('
        SELECT id, nazvanie, artikul, brend, cena, ostatok,
               CASE
                   WHEN artikul = ?     THEN 1
                   WHEN artikul LIKE ?  THEN 2
                   WHEN nazvanie LIKE ? THEN 3
                   WHEN nazvanie LIKE ? THEN 4
                   ELSE 5
               END AS rang
        FROM tovary
        WHERE aktivnyi = 1 AND (nazvanie LIKE ? OR artikul LIKE ? OR brend LIKE ?)
        ORDER BY rang, ostatok DESC, nazvanie
        LIMIT 20
    ', [$tochno, $nachalo, $nachalo, $vezde, $vezde, $vezde, $vezde]);
}

$zagolovok = 'Поиск — ' . SAIT_NAZVANIE;
require __DIR__ . '/includes/header.php';
?>
<h1>Поиск по каталогу</h1>

<form class="poisk-bolshoy" method="GET" autocomplete="off">
    <input type="search" id="poisk" name="q" value="<?= e($zapros) ?>"
           placeholder="Название, артикул или бренд">
    <button type="submit">Найти</button>
    <div class="podskazki" hidden></div>
</form>

<?php if ($zapros !== ''): ?>
    <p class="schetchik">
        По запросу «<?= e($zapros) ?>» найдено <?= count($naydeno) ?>
        <?= sklonenie(count($naydeno), 'товар', 'товара', 'товаров') ?>
    </p>

    <?php if ($naydeno): ?>
        <table class="rezultaty">
            <thead><tr><th>Ранг</th><th>Товар</th><th>Артикул</th><th>Бренд</th><th>Цена</th><th>Наличие</th></tr></thead>
            <tbody>
            <?php foreach ($naydeno as $t): ?>
                <tr>
                    <td class="rang"><?= $t['rang'] ?></td>
                    <td><?= e($t['nazvanie']) ?></td>
                    <td class="mono"><?= e($t['artikul']) ?></td>
                    <td><?= e($t['brend']) ?></td>
                    <td class="mono"><?= somoni((int) $t['cena']) ?></td>
                    <td><?= $t['ostatok'] > 0 ? $t['ostatok'] . ' шт.' : 'под заказ' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="pusto"><h3>Ничего не нашлось</h3><p>Попробуйте другой запрос.</p></div>
    <?php endif; ?>
<?php endif; ?>

<script src="assets/poisk.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
