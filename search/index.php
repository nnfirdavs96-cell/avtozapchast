<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/product_card.php';

$q = trim((string)($_GET['q'] ?? ''));
$category = $_GET['category'] ?? '';

$parts = [];
$total = 0;
if ($q !== '') {
    $db = getDB();
    $params = [];
    $where  = ['p.is_active=1'];
    $like = '%' . $q . '%';
    $where[] = '(p.name LIKE ? OR p.part_number LIKE ? OR p.description LIKE ?)';
    array_push($params, $like, $like, $like);
    if ($category) {
        $where[] = 'c.slug = ?';
        $params[] = $category;
    }
    $whereSql = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM parts p LEFT JOIN categories c ON c.id=p.category_id WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT p.*, b.name AS brand_name FROM parts p
                          LEFT JOIN brands b ON b.id=p.brand_id
                          LEFT JOIN categories c ON c.id=p.category_id
                          WHERE {$whereSql}
                          ORDER BY p.created_at DESC
                          LIMIT 48");
    $stmt->execute($params);
    $parts = $stmt->fetchAll();
}

$pageTitle = $q !== '' ? "Поиск: {$q}" : t('search_btn');

require_once __DIR__ . '/../includes/header.php';
?>

<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<div class="page-head">
  <div class="container">
    <h1><?= t('search_btn') ?>: <?= sanitize($q) ?></h1>
    <nav class="breadcrumb">
      <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
      <span class="current"><?= t('search_btn') ?></span>
    </nav>
  </div>
</div>

<section class="section">
  <div class="container">

    <form method="get" class="header-search mb-32" style="max-width:760px">
      <select name="category">
        <option value=""><?= t('all_categories') ?></option>
        <?php foreach (getCategories() as $c): if ($c['parent_id']!==null) continue; ?>
          <option value="<?= sanitize($c['slug']) ?>" <?= $category===$c['slug']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="<?= t('search_placeholder') ?>">
      <button type="submit"><?= t('search_btn') ?></button>
    </form>

    <?php if ($q === ''): ?>
      <div class="empty-state">
        <h3>Введите запрос</h3>
        <p>Можно искать по номеру детали, названию или описанию.</p>
      </div>
    <?php elseif (empty($parts)): ?>
      <div class="empty-state">
        <h3><?= t('no_products_found') ?></h3>
        <p>По запросу <strong><?= sanitize($q) ?></strong> ничего не найдено.</p>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary"><?= t('catalog') ?></a>
      </div>
    <?php else: ?>
      <p class="text-muted mb-24">Найдено <strong><?= $total ?></strong> результатов</p>
      <div class="products-grid">
        <?php foreach ($parts as $p) renderProductCard($p); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
