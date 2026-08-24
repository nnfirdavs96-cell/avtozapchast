<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$tovary = [
    1 => ['nazvanie' => 'Тормозные колодки Bosch', 'cena' => 25000],
    2 => ['nazvanie' => 'Масляный фильтр Mann',    'cena' => 4500],
    3 => ['nazvanie' => 'Свечи зажигания Denso',   'cena' => 12000],
];

$oshibki = [];
$uspeh = false;
$nomer_zakaza = null;

$dannye = [
    'tovar_id'    => 1,
    'kolichestvo' => 1,
    'imya'        => '',
    'telefon'     => '',
    'dostavka'    => 'samovyvoz',
    'kommentariy' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Очистка ---
    $dannye['tovar_id']    = (int) ($_POST['tovar_id'] ?? 0);
    $dannye['kolichestvo'] = (int) ($_POST['kolichestvo'] ?? 0);
    $dannye['imya']        = trim($_POST['imya'] ?? '');
    $dannye['telefon']     = trim($_POST['telefon'] ?? '');
    $dannye['dostavka']    = $_POST['dostavka'] ?? '';
    $dannye['kommentariy'] = trim($_POST['kommentariy'] ?? '');

    // --- Проверка ---
    if (!isset($tovary[$dannye['tovar_id']])) {
        $oshibki['tovar_id'] = 'Выберите товар';
    }
    if ($dannye['kolichestvo'] < 1 || $dannye['kolichestvo'] > 99) {
        $oshibki['kolichestvo'] = 'Количество от 1 до 99';
    }
    if (mb_strlen($dannye['imya']) < 2) {
        $oshibki['imya'] = 'Введите имя, минимум 2 символа';
    }
    if (!preg_match('/^\+?[0-9\s\-()]{9,20}$/', $dannye['telefon'])) {
        $oshibki['telefon'] = 'Телефон в формате +992 XX XXX XX XX';
    }
    if (!in_array($dannye['dostavka'], ['samovyvoz', 'kurier'], true)) {
        $oshibki['dostavka'] = 'Выберите способ получения';
    }
    if (mb_strlen($dannye['kommentariy']) > 500) {
        $oshibki['kommentariy'] = 'Комментарий слишком длинный';
    }

    // --- Сохранение ---
    if (empty($oshibki)) {
        // Цену берём ИЗ КАТАЛОГА, а не из формы.
        // Придёт из формы — покупатель поставит любую.
        $tovar = $tovary[$dannye['tovar_id']];
        $summa = $tovar['cena'] * $dannye['kolichestvo'];
        $dostavka_diram = $dannye['dostavka'] === 'kurier' ? cena_dostavki($summa) : 0;
        $itogo = $summa + $dostavka_diram;

        // Здесь будет запись в базу (глава 33)
        $nomer_zakaza = random_int(1000, 9999);
        $uspeh = true;
    }
}

$zagolovok = 'Оформление заказа';
require __DIR__ . '/includes/header.php';
?>

<h1>Оформление заказа</h1>

<?php if ($uspeh): ?>

    <div class="uspeh">
        <h2>Заказ №<?= $nomer_zakaza ?> принят</h2>
        <p>Товар: <?= e($tovar['nazvanie']) ?> × <?= $dannye['kolichestvo'] ?></p>
        <p>Сумма товаров: <?= somoni($summa) ?> сомони</p>
        <p>Доставка: <?= $dostavka_diram > 0 ? somoni($dostavka_diram) . ' сомони' : 'бесплатно' ?></p>
        <p><strong>К оплате: <?= somoni($itogo) ?> сомони</strong></p>
        <p>Мы позвоним на <?= e($dannye['telefon']) ?> в течение часа.</p>
    </div>

<?php else: ?>

    <?php if (!empty($oshibki)): ?>
        <div class="oshibka-obshaya">
            Проверьте <?= count($oshibki) ?>
            <?= sklonenie(count($oshibki), 'поле', 'поля', 'полей') ?> ниже
        </div>
    <?php endif; ?>

    <form method="POST" class="forma">

        <div class="pole">
            <label for="tovar_id">Товар</label>
            <select id="tovar_id" name="tovar_id">
                <?php foreach ($tovary as $id => $t): ?>
                    <option value="<?= $id ?>" <?= $dannye['tovar_id'] === $id ? 'selected' : '' ?>>
                        <?= e($t['nazvanie']) ?> — <?= somoni($t['cena']) ?> сомони
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="pole">
            <label for="kolichestvo">Количество</label>
            <input type="number" id="kolichestvo" name="kolichestvo" min="1" max="99"
                   value="<?= e((string) $dannye['kolichestvo']) ?>"
                   class="<?= isset($oshibki['kolichestvo']) ? 'plohoe' : '' ?>">
            <?php if (isset($oshibki['kolichestvo'])): ?>
                <span class="podskazka"><?= e($oshibki['kolichestvo']) ?></span>
            <?php endif; ?>
        </div>

        <div class="pole">
            <label for="imya">Ваше имя</label>
            <input type="text" id="imya" name="imya"
                   value="<?= e($dannye['imya']) ?>"
                   class="<?= isset($oshibki['imya']) ? 'plohoe' : '' ?>">
            <?php if (isset($oshibki['imya'])): ?>
                <span class="podskazka"><?= e($oshibki['imya']) ?></span>
            <?php endif; ?>
        </div>

        <div class="pole">
            <label for="telefon">Телефон</label>
            <input type="tel" id="telefon" name="telefon" placeholder="+992 92 777 77 77"
                   value="<?= e($dannye['telefon']) ?>"
                   class="<?= isset($oshibki['telefon']) ? 'plohoe' : '' ?>">
            <?php if (isset($oshibki['telefon'])): ?>
                <span class="podskazka"><?= e($oshibki['telefon']) ?></span>
            <?php endif; ?>
        </div>

        <div class="pole">
            <label>Способ получения</label>
            <label class="galka">
                <input type="radio" name="dostavka" value="samovyvoz"
                       <?= $dannye['dostavka'] === 'samovyvoz' ? 'checked' : '' ?>>
                Самовывоз, Худжанд — бесплатно
            </label>
            <label class="galka">
                <input type="radio" name="dostavka" value="kurier"
                       <?= $dannye['dostavka'] === 'kurier' ? 'checked' : '' ?>>
                Курьером — 12.00 сомони, от 1000 сомони бесплатно
            </label>
        </div>

        <div class="pole">
            <label for="kommentariy">Комментарий</label>
            <textarea id="kommentariy" name="kommentariy" rows="3"
                      placeholder="Марка, год, объём двигателя"><?= e($dannye['kommentariy']) ?></textarea>
        </div>

        <button type="submit">Оформить заказ</button>
    </form>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
