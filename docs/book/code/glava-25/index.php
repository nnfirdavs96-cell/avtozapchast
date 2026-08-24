<?php
// index.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tovary.php';

// --- Параметры из адресной строки ---
$zapros     = trim($_GET['q'] ?? '');
$brend      = $_GET['brend'] ?? '';
$kategoriya = $_GET['kategoriya'] ?? '';
$sort       = $_GET['sort'] ?? '';
$tolko_est  = isset($_GET['est']);
$stranica   = max(1, (int) ($_GET['page'] ?? 1));

const NA_STRANICE = 6;

// --- Отбор ---
$spisok = vse_tovary();

if ($zapros !== '') {
    $nizhnij = mb_strtolower($zapros);
    $spisok = array_filter($spisok, function ($t) use ($nizhnij) {
        return str_contains(mb_strtolower($t['nazvanie']), $nizhnij)
            || str_contains(mb_strtolower($t['artikul']), $nizhnij);
    });
}

// Значения из списков сверяем с допустимыми — из адреса может прийти что угодно
if (in_array($brend, vse_brendy(), true)) {
    $spisok = array_filter($spisok, fn($t) => $t['brend'] === $brend);
} else {
    $brend = '';
}

if (in_array($kategoriya, vse_kategorii(), true)) {
    $spisok = array_filter($spisok, fn($t) => $t['kategoriya'] === $kategoriya);
} else {
    $kategoriya = '';
}

if ($tolko_est) {
    $spisok = array_filter($spisok, fn($t) => $t['ostatok'] > 0);
}

$spisok = array_values($spisok);

// --- Цены считаем ПОСЛЕ отбора: незачем считать то, что не покажем ---
foreach ($spisok as $i => $t) {
    $spisok[$i]['cena'] = cena_prodazhi($t['zakup']);
}

// --- Сортировка ---
match ($sort) {
    'cena_vozr' => usort($spisok, fn($a, $b) => $a['cena'] <=> $b['cena']),
    'cena_ubyv' => usort($spisok, fn($a, $b) => $b['cena'] <=> $a['cena']),
    'nazvanie'  => usort($spisok, fn($a, $b) => strcmp($a['nazvanie'], $b['nazvanie'])),
    default     => null,
};

// --- Пагинация ---
$vsego = count($spisok);
$stranic = max(1, (int) ceil($vsego / NA_STRANICE));
$stranica = min($stranica, $stranic);
$na_ekrane = array_slice($spisok, ($stranica - 1) * NA_STRANICE, NA_STRANICE);

$zagolovok = 'Каталог — ' . SAIT_NAZVANIE;
require __DIR__ . '/includes/header.php';
?>

<h1>Каталог запчастей</h1>

<form class="filtry" method="GET">
    <div class="ryad">
        <div>
            <label for="q">Поиск</label>
            <input type="search" id="q" name="q" value="<?= e($zapros) ?>"
                   placeholder="название или артикул">
        </div>

        <div>
            <label for="brend">Бренд</label>
            <select id="brend" name="brend">
                <option value="">Все бренды</option>
                <?php foreach (vse_brendy() as $b): ?>
                    <option value="<?= e($b) ?>" <?= $brend === $b ? 'selected' : '' ?>>
                        <?= e($b) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="kategoriya">Категория</label>
            <select id="kategoriya" name="kategoriya">
                <option value="">Все категории</option>
                <?php foreach (vse_kategorii() as $k): ?>
                    <option value="<?= e($k) ?>" <?= $kategoriya === $k ? 'selected' : '' ?>>
                        <?= e($k) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="sort">Сортировка</label>
            <select id="sort" name="sort">
                <option value="">По умолчанию</option>
                <option value="cena_vozr" <?= $sort === 'cena_vozr' ? 'selected' : '' ?>>Сначала дешёвые</option>
                <option value="cena_ubyv" <?= $sort === 'cena_ubyv' ? 'selected' : '' ?>>Сначала дорогие</option>
                <option value="nazvanie"  <?= $sort === 'nazvanie'  ? 'selected' : '' ?>>По названию</option>
            </select>
        </div>

        <label class="galka">
            <input type="checkbox" name="est" value="1" <?= $tolko_est ? 'checked' : '' ?>>
            Только в наличии
        </label>

        <button type="submit">Показать</button>
    </div>
</form>

<p class="schetchik">
    Найдено <?= $vsego ?> <?= sklonenie($vsego, 'товар', 'товара', 'товаров') ?>
    <?php if ($stranic > 1): ?>
        · страница <?= $stranica ?> из <?= $stranic ?>
    <?php endif; ?>
</p>

<?php if ($vsego === 0): ?>

    <div class="pusto">
        <h3>Ничего не нашлось</h3>
        <p>Попробуйте изменить запрос или сбросить фильтры.</p>
        <a class="knopka" href="?">Показать все товары</a>
    </div>

<?php else: ?>

    <div class="katalog">
        <?php foreach ($na_ekrane as $t): ?>
            <article class="tovar">
                <h3>
                    <a href="tovar.php?id=<?= $t['id'] ?>"><?= e($t['nazvanie']) ?></a>
                </h3>
                <p class="artikul">Артикул: <?= e($t['artikul']) ?> · <?= e($t['brend']) ?></p>
                <p class="cena"><?= somoni($t['cena']) ?> <span>сомони</span></p>

                <?php if ($t['ostatok'] > 0): ?>
                    <p class="nalichie">В наличии: <?= $t['ostatok'] ?> шт.</p>
                <?php else: ?>
                    <p class="nalichie nety">Под заказ, 5 дней</p>
                <?php endif; ?>

                <a class="knopka" href="zakaz.php?id=<?= $t['id'] ?>">
                    <?= $t['ostatok'] > 0 ? 'Заказать' : 'Под заказ' ?>
                </a>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($stranic > 1): ?>
        <nav class="stranicy">
            <?php for ($i = 1; $i <= $stranic; $i++): ?>
                <?php if ($i === $stranica): ?>
                    <span class="tekushaya"><?= $i ?></span>
                <?php else: ?>
                    <?php
                    // Сохраняем все фильтры, меняем только номер страницы
                    $parametry = $_GET;
                    $parametry['page'] = $i;
                    ?>
                    <a href="?<?= http_build_query($parametry) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
