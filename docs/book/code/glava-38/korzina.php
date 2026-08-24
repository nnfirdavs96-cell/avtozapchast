<?php
// korzina.php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/korzina.php';

$soobshenie = null;
$oshibka = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_proverit();

    $deystvie = $_POST['deystvie'] ?? '';
    $tovar_id = (int) ($_POST['tovar_id'] ?? 0);

    switch ($deystvie) {
        case 'dobavit':
            $kol = max(1, (int) ($_POST['kolichestvo'] ?? 1));
            $oshibka = korzina_dobavit($tovar_id, $kol);
            $soobshenie = $oshibka === null ? 'Товар добавлен в корзину' : null;
            break;

        case 'izmenit':
            $oshibka = korzina_izmenit($tovar_id, (int) ($_POST['kolichestvo'] ?? 0));
            break;

        case 'ubrat':
            korzina_ubrat($tovar_id);
            $soobshenie = 'Товар убран из корзины';
            break;

        case 'ochistit':
            korzina_ochistit();
            $soobshenie = 'Корзина очищена';
            break;
    }

    // PRG из главы 24: после POST — перенаправление,
    // иначе F5 повторит действие
    $_SESSION['flash'] = ['soobshenie' => $soobshenie, 'oshibka' => $oshibka];
    header('Location: korzina.php');
    exit;
}

// Сообщение, оставленное перед перенаправлением
$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$k = korzina_podrobno();

$zagolovok = 'Корзина — ' . SAIT_NAZVANIE;
require __DIR__ . '/includes/header.php';
?>

<h1>Корзина</h1>

<?php if (!empty($flash['soobshenie'])): ?>
    <div class="uspeh-uzkiy"><?= e($flash['soobshenie']) ?></div>
<?php endif; ?>

<?php if (!empty($flash['oshibka'])): ?>
    <div class="oshibka-obshaya"><?= e($flash['oshibka']) ?></div>
<?php endif; ?>

<?php foreach ($k['preduprezhdeniya'] as $p): ?>
    <div class="preduprezhdenie"><?= $p ?></div>
<?php endforeach; ?>

<?php if (empty($k['pozicii'])): ?>

    <div class="pusto">
        <h3>Корзина пуста</h3>
        <p>Выберите запчасти в каталоге.</p>
        <a class="knopka" href="index.php">Перейти в каталог</a>
    </div>

<?php else: ?>

    <table class="korzina-tablica">
        <thead>
            <tr>
                <th>Товар</th><th>Цена</th><th>Количество</th><th>Сумма</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($k['pozicii'] as $p): ?>
            <tr>
                <td>
                    <a href="tovar.php?id=<?= $p['id'] ?>"><?= e($p['nazvanie']) ?></a>
                    <div class="artikul"><?= e($p['artikul']) ?></div>
                </td>
                <td class="mono"><?= somoni($p['cena']) ?></td>
                <td>
                    <form method="POST" class="kolichestvo-forma">
                        <?= csrf_pole() ?>
                        <input type="hidden" name="deystvie" value="izmenit">
                        <input type="hidden" name="tovar_id" value="<?= $p['id'] ?>">
                        <input type="number" name="kolichestvo" value="<?= $p['kolichestvo'] ?>"
                               min="0" max="<?= $p['ostatok'] ?>"
                               onchange="this.form.submit()">
                    </form>
                </td>
                <td class="mono"><strong><?= somoni($p['stoimost']) ?></strong></td>
                <td>
                    <form method="POST">
                        <?= csrf_pole() ?>
                        <input type="hidden" name="deystvie" value="ubrat">
                        <input type="hidden" name="tovar_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="knopka-tihaya">×</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Итого, <?= $k['shtuk'] ?>
                    <?= sklonenie($k['shtuk'], 'товар', 'товара', 'товаров') ?></td>
                <td class="mono"><strong><?= somoni($k['summa']) ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="korzina-deystviya">
        <form method="POST">
            <?= csrf_pole() ?>
            <input type="hidden" name="deystvie" value="ochistit">
            <button type="submit" class="knopka-tihaya">Очистить корзину</button>
        </form>
        <a class="knopka" href="checkout.php">Оформить заказ</a>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
