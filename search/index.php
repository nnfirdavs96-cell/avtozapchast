<?php
require_once dirname(__DIR__) . '/config/config.php';

$q      = trim($_GET['q'] ?? '');
$parts  = [];
$total  = 0;

if (mb_strlen($q) >= 2) {
    $db     = getDB();
    $like   = '%' . $q . '%';
    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM parts p WHERE p.is_active = 1 AND (p.part_number LIKE ? OR p.name LIKE ?)"
    );
    $countStmt->execute([$like, $like]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT p.*, b.name AS brand_name, c.name AS category_name
         FROM parts p
         LEFT JOIN brands b ON b.id = p.brand_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1 AND (p.part_number LIKE ? OR p.name LIKE ?)
         ORDER BY
           CASE WHEN p.part_number = ? THEN 0
                WHEN p.part_number LIKE ? THEN 1
                ELSE 2 END,
           p.part_number
         LIMIT 50"
    );
    $stmt->execute([$like, $like, $q, $q . '%']);
    $parts = $stmt->fetchAll();
}

$pageTitle = $q ? 'Поиск: ' . $q : 'Поиск';
require_once dirname(__DIR__) . '/includes/header.php';

function highlightSearch(string $text, string $q): string {
    if (!$q) return sanitize($text);
    $escaped = sanitize($text);
    $qEsc    = preg_quote(sanitize($q), '/');
    return preg_replace('/(' . $qEsc . ')/iu', '<mark class="highlight">$1</mark>', $escaped);
}
?>

<style>
.search-page { max-width: 1100px; margin: 36px auto; padding: 0 24px; }
.search-form-big {
  display: flex;
  gap: 10px;
  margin-bottom: 36px;
  max-width: 700px;
}
.search-form-big input {
  flex: 1;
  font-size: 1rem;
  padding: 14px 18px;
}
.search-form-big button {
  padding: 14px 28px;
  font-family: var(--font-display);
  font-size: 1.1rem;
  letter-spacing: 1px;
}
</style>

<div class="search-page">
  <div class="label-mono mb-16">// Поиск по каталогу</div>
  <h1 class="section-heading mb-24">ПОИСК ЗАПЧАСТЕЙ</h1>

  <!-- Search form -->
  <form class="search-form-big" method="get" action="">
    <input type="text" name="q" class="form-input" value="<?= sanitize($q) ?>"
           placeholder="Введите номер детали или название..." autofocus>
    <button type="submit" class="btn btn-primary">НАЙТИ</button>
  </form>

  <?php if ($q): ?>
  <!-- Results header -->
  <div class="search-results-header mb-24">
    <h2 style="font-family:var(--font-display);font-size:1.4rem;letter-spacing:1px;">
      РЕЗУЛЬТАТЫ
    </h2>
    <span class="search-count">Найдено: <strong><?= $total ?></strong></span>
    <?php if ($total > 0): ?>
    <span>по запросу</span>
    <span class="search-term"><?= sanitize($q) ?></span>
    <?php endif; ?>
  </div>

  <?php if (empty($parts)): ?>
    <div class="no-data">
      <div class="no-data-icon">🔍</div>
      <p>По запросу «<?= sanitize($q) ?>» ничего не найдено.</p>
      <p style="margin-top:8px;font-size:0.8rem;color:var(--text-muted);">
        Проверьте правильность написания номера детали.
      </p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Номер детали</th>
            <th>Название</th>
            <th>Бренд</th>
            <th>Категория</th>
            <th style="text-align:right;">Цена</th>
            <th>Наличие</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($parts as $part):
            $stock = getStockStatus((int)$part['stock']);
          ?>
          <tr>
            <td>
              <span class="mono"><?= highlightSearch($part['part_number'], $q) ?></span>
            </td>
            <td>
              <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $part['id'] ?>"
                 style="color:var(--text-primary);text-decoration:none;font-size:0.875rem;">
                <?= highlightSearch($part['name'], $q) ?>
              </a>
            </td>
            <td style="font-family:var(--font-mono);font-size:0.75rem;color:var(--text-secondary);">
              <?= sanitize($part['brand_name']) ?>
            </td>
            <td style="font-size:0.8rem;color:var(--text-muted);">
              <?= sanitize($part['category_name']) ?>
            </td>
            <td style="text-align:right;font-family:var(--font-mono);color:var(--accent);white-space:nowrap;">
              <?= formatPrice($part['price']) ?>
            </td>
            <td>
              <span class="badge badge-<?= $stock['class'] ?>"><?= $stock['label'] ?></span>
            </td>
            <td>
              <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $part['id'] ?>"
                 class="btn btn-outline btn-sm">Подробнее</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
