<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/korzina.php';

$tovary = zapros('SELECT id, nazvanie, artikul, cena, ostatok FROM tovary WHERE aktivnyi = 1 ORDER BY id LIMIT 6');
$zagolovok = 'Каталог';
require __DIR__ . '/includes/header.php';
?>
<h1>Каталог запчастей</h1>
<p class="schetchik">В корзине: <?= korzina_schetchik() ?> шт.</p>
<div class="katalog">
<?php foreach ($tovary as $t): ?>
    <article class="tovar">
        <h3><?= e($t['nazvanie']) ?></h3>
        <p class="artikul">Артикул: <?= e($t['artikul']) ?></p>
        <p class="cena"><?= somoni((int) $t['cena']) ?> <span>сомони</span></p>
        <p class="nalichie <?= $t['ostatok'] > 0 ? '' : 'nety' ?>">
            <?= $t['ostatok'] > 0 ? 'В наличии: ' . $t['ostatok'] . ' шт.' : 'Под заказ' ?>
        </p>
        <form method="POST" action="korzina.php">
            <?= csrf_pole() ?>
            <input type="hidden" name="deystvie" value="dobavit">
            <input type="hidden" name="tovar_id" value="<?= (int) $t['id'] ?>">
            <input type="hidden" name="kolichestvo" value="1">
            <button type="submit" <?= $t['ostatok'] > 0 ? '' : 'disabled' ?>>В корзину</button>
        </form>
    </article>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
