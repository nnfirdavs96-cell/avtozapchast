<?php
/**
 * Страница-хаб «Каталоги» — плитки разделов (как на крупных автомаркетах).
 *
 * Два блока: «Подбор и популярное» (точки входа — VIN, шины, масла, грузовые,
 * весь каталог) и «Категории» (верхнеуровневые категории из базы). Плитки —
 * ссылки на уже существующие страницы, новой логики каталога не вводим.
 */
require_once dirname(__DIR__) . '/config/config.php';

$db = getDB();

// Верхнеуровневые активные категории (для второго блока).
$allCats  = getCategories();
$topCats  = array_values(array_filter($allCats, fn($c) => $c['parent_id'] === null));
usort($topCats, fn($a, $b) => [(int)$a['sort_order'], $a['name']] <=> [(int)$b['sort_order'], $b['name']]);

// Блок «Подбор и популярное» — точки входа. icon — класс FontAwesome.
$featured = [
    ['label' => 'Подбор по VIN',     'icon' => 'fa-search',        'href' => APP_URL . '/pages/vin.php'],
    ['label' => 'Весь каталог',      'icon' => 'fa-th-large',      'href' => APP_URL . '/catalog/index.php'],
    ['label' => 'Шины',              'icon' => 'fa-circle-o-notch', 'href' => APP_URL . '/catalog/category.php?slug=shiny'],
    ['label' => 'Масла и жидкости',  'icon' => 'fa-tint',          'href' => APP_URL . '/catalog/category.php?slug=masla-zhidkosti'],
    ['label' => 'Грузовые',          'icon' => 'fa-truck',         'href' => APP_URL . '/catalog/index.php?vehicle=truck'],
    ['label' => 'Акции и скидки',    'icon' => 'fa-percent',       'href' => APP_URL . '/catalog/index.php?sale=1'],
];

$pageTitle = 'Каталоги запчастей и товаров — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?= breadcrumb([
    ['label' => t('home'),      'url' => APP_URL . '/index.php'],
    ['label' => 'Каталоги',     'url' => ''],
]) ?>

<div class="cat-hub">
  <div class="container">
    <h1 class="cat-hub__title">Каталоги запчастей и товаров</h1>

    <section class="cat-hub__group">
      <h2 class="cat-hub__gtitle">Подбор и популярное</h2>
      <div class="cat-hub__grid">
        <?php foreach ($featured as $t): ?>
        <a class="cat-tile" href="<?= sanitize($t['href']) ?>">
          <span class="cat-tile__ic"><i class="fa <?= sanitize($t['icon']) ?>"></i></span>
          <span class="cat-tile__label"><?= sanitize($t['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if (!empty($topCats)): ?>
    <section class="cat-hub__group">
      <h2 class="cat-hub__gtitle">Категории</h2>
      <div class="cat-hub__grid">
        <?php foreach ($topCats as $c):
            $href = APP_URL . '/catalog/category.php?slug=' . urlencode($c['slug']);
            $img  = !empty($c['image_path']) ? $c['image_path'] : '';
            // image_path может быть как относительным файлом, так и готовым URL.
            if ($img !== '' && !preg_match('#^https?://#', $img) && $img[0] !== '/') {
                $img = APP_URL . '/assets/img/' . ltrim($img, '/');
            }
        ?>
        <a class="cat-tile" href="<?= sanitize($href) ?>">
          <span class="cat-tile__ic">
            <?php if ($img !== ''): ?>
              <img src="<?= sanitize($img) ?>" alt="" loading="lazy">
            <?php else: ?>
              <i class="fa fa-cube"></i>
            <?php endif; ?>
          </span>
          <span class="cat-tile__label"><?= sanitize(tField($c, 'name')) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
