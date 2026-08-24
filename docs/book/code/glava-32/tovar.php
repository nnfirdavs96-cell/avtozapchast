<?php
// tovar.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tovary.php';

$id = (int) ($_GET['id'] ?? 0);
$tovar = tovar_po_id($id);

// Товара нет — честная 404, а не пустая страница
if ($tovar === null) {
    http_response_code(404);
    $zagolovok = 'Товар не найден';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="pusto">
        <h1>Товар не найден</h1>
        <p>Возможно, он снят с продажи или в ссылке опечатка.</p>
        <a class="knopka" href="index.php">Вернуться в каталог</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$cena = $tovar['zakup'];

// Похожие товары — той же категории, кроме текущего
$pohozhie = array_slice(array_values(array_filter(
    vse_tovary(),
    fn($t) => $t['kategoriya'] === $tovar['kategoriya'] && $t['id'] !== $tovar['id']
)), 0, 3);

$zagolovok = $tovar['nazvanie'] . ' — ' . SAIT_NAZVANIE;
require __DIR__ . '/includes/header.php';
?>

<nav class="hlebnye-kroshki">
    <a href="index.php">Каталог</a> →
    <a href="index.php?kategoriya=<?= urlencode($tovar['kategoriya']) ?>">
        <?= e($tovar['kategoriya']) ?>
    </a> →
    <span><?= e($tovar['nazvanie']) ?></span>
</nav>

<article class="tovar-podrobno">
    <h1><?= e($tovar['nazvanie']) ?></h1>

    <table class="harakteristiki">
        <tr><th>Артикул</th><td><?= e($tovar['artikul']) ?></td></tr>
        <tr><th>Бренд</th><td><?= e($tovar['brend']) ?></td></tr>
        <tr><th>Категория</th><td><?= e($tovar['kategoriya']) ?></td></tr>
        <tr><th>Наличие</th><td>
            <?= $tovar['ostatok'] > 0
                ? 'В наличии: ' . $tovar['ostatok'] . ' шт.'
                : 'Под заказ, 5 дней' ?>
        </td></tr>
    </table>

    <p class="cena-bolshaya"><?= somoni($cena) ?> <span>сомони</span></p>

    <a class="knopka" href="zakaz.php?id=<?= $tovar['id'] ?>">Заказать</a>
</article>

<?php if ($pohozhie): ?>
    <h2>Похожие товары</h2>
    <div class="katalog">
        <?php foreach ($pohozhie as $p): ?>
            <article class="tovar">
                <h3><a href="tovar.php?id=<?= $p['id'] ?>"><?= e($p['nazvanie']) ?></a></h3>
                <p class="cena"><?= somoni($p['zakup']) ?> <span>сомони</span></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
