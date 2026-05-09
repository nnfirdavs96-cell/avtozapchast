<?php
require_once dirname(__DIR__) . '/config/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flashMessage('danger', 'Товар не найден.');
    redirect(APP_URL . '/catalog/index.php');
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, b.country AS brand_country, c.name AS category_name, c.slug AS category_slug
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.is_active = 1"
);
$stmt->execute([$id]);
$part = $stmt->fetch();
if (!$part) {
    flashMessage('danger', 'Товар не найден или снят с продажи.');
    redirect(APP_URL . '/catalog/index.php');
}

// Related parts (same category, same brand, limit 4)
$relStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     WHERE p.is_active = 1 AND p.id != ?
       AND (p.category_id = ? OR p.brand_id = ?)
     LIMIT 4"
);
$relStmt->execute([$id, $part['category_id'], $part['brand_id']]);
$related = $relStmt->fetchAll();

$stock     = getStockStatus((int)$part['stock']);
$pageTitle = sanitize($part['name']);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
.part-page { max-width: 1200px; margin: 32px auto; padding: 0 24px; }
.part-breadcrumb {
  display: flex;
  gap: 8px;
  align-items: center;
  font-family: var(--font-mono);
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-bottom: 28px;
  flex-wrap: wrap;
}
.part-breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
.part-breadcrumb a:hover { color: var(--accent); }
.part-breadcrumb span { color: var(--text-muted); }
.part-detail-grid {
  display: grid;
  grid-template-columns: 420px 1fr;
  gap: 40px;
  margin-bottom: 56px;
}
@media (max-width: 900px) { .part-detail-grid { grid-template-columns: 1fr; } }
.part-img-box {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  aspect-ratio: 4/3;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
.part-img-bg {
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    45deg,
    var(--bg-secondary),
    var(--bg-secondary) 15px,
    var(--bg-card) 15px,
    var(--bg-card) 30px
  );
  opacity: 0.5;
}
.part-img-icon { position: relative; z-index: 1; color: var(--text-muted); opacity: 0.15; }
.part-number-big {
  position: absolute;
  bottom: 12px;
  left: 12px;
  font-family: var(--font-mono);
  font-size: 0.75rem;
  color: var(--accent);
  background: var(--bg-primary);
  border: 1px solid var(--accent);
  padding: 4px 10px;
  border-radius: 2px;
  z-index: 2;
  letter-spacing: 0.05em;
}
.part-info-section { display: flex; flex-direction: column; gap: 0; }
.part-category-link {
  font-family: var(--font-mono);
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--accent);
  margin-bottom: 8px;
  text-decoration: none;
}
.part-title {
  font-family: var(--font-display);
  font-size: 1.8rem;
  letter-spacing: 2px;
  margin-bottom: 12px;
}
.part-meta-row {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.part-spec-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  margin-bottom: 24px;
}
.part-spec-item {
  background: var(--bg-card);
  padding: 12px 14px;
}
.part-spec-label {
  font-family: var(--font-mono);
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--text-muted);
  margin-bottom: 4px;
}
.part-spec-val {
  font-family: var(--font-mono);
  font-size: 0.875rem;
  color: var(--text-primary);
}
.price-big {
  font-family: var(--font-display);
  font-size: 2.8rem;
  color: var(--accent);
  letter-spacing: 2px;
  margin-bottom: 16px;
}
.add-cart-form { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
.qty-input {
  width: 70px;
  text-align: center;
  font-family: var(--font-mono);
  font-size: 1rem;
}
.part-desc-section {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px;
  margin-bottom: 48px;
}
.part-desc-title {
  font-family: var(--font-display);
  font-size: 1.2rem;
  letter-spacing: 1px;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}
.part-desc-text {
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.8;
}
</style>

<div class="part-page">
  <!-- Breadcrumb -->
  <div class="part-breadcrumb">
    <a href="<?= APP_URL ?>/index.php">Главная</a>
    <span>/</span>
    <a href="<?= APP_URL ?>/catalog/index.php">Каталог</a>
    <span>/</span>
    <a href="<?= APP_URL ?>/catalog/index.php?category=<?= sanitize($part['category_slug']) ?>"><?= sanitize($part['category_name']) ?></a>
    <span>/</span>
    <span style="color:var(--text-secondary);"><?= sanitize($part['part_number']) ?></span>
  </div>

  <!-- Detail grid -->
  <div class="part-detail-grid">
    <!-- Image -->
    <div>
      <div class="part-img-box">
        <div class="part-img-bg"></div>
        <div class="part-img-icon">
          <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="0.5">
            <rect x="2" y="7" width="20" height="10" rx="1"/>
            <path d="M8 7V5M16 7V5M4 12h2M18 12h2M8 12h8"/>
          </svg>
        </div>
        <span class="part-number-big"><?= sanitize($part['part_number']) ?></span>
      </div>
    </div>

    <!-- Info -->
    <div class="part-info-section">
      <a href="<?= APP_URL ?>/catalog/index.php?category=<?= sanitize($part['category_slug']) ?>" class="part-category-link">
        <?= sanitize($part['category_name']) ?>
      </a>
      <h1 class="part-title"><?= sanitize($part['name']) ?></h1>
      <div class="part-meta-row">
        <span class="badge badge-accent"><?= sanitize($part['brand_name']) ?></span>
        <span class="badge badge-<?= $stock['class'] ?>"><?= $stock['label'] ?></span>
        <?php if ($part['stock'] > 0): ?>
          <span style="font-family:var(--font-mono);font-size:0.7rem;color:var(--text-muted);">
            <?= (int)$part['stock'] ?> шт
          </span>
        <?php endif; ?>
      </div>

      <!-- Specs -->
      <div class="part-spec-grid">
        <div class="part-spec-item">
          <div class="part-spec-label">Номер детали</div>
          <div class="part-spec-val" style="color:var(--accent);"><?= sanitize($part['part_number']) ?></div>
        </div>
        <div class="part-spec-item">
          <div class="part-spec-label">Производитель</div>
          <div class="part-spec-val"><?= sanitize($part['brand_name']) ?></div>
        </div>
        <div class="part-spec-item">
          <div class="part-spec-label">Страна</div>
          <div class="part-spec-val"><?= sanitize($part['brand_country'] ?? '—') ?></div>
        </div>
        <div class="part-spec-item">
          <div class="part-spec-label">Категория</div>
          <div class="part-spec-val"><?= sanitize($part['category_name']) ?></div>
        </div>
        <?php if ($part['weight']): ?>
        <div class="part-spec-item">
          <div class="part-spec-label">Вес</div>
          <div class="part-spec-val"><?= sanitize($part['weight']) ?> кг</div>
        </div>
        <?php endif; ?>
        <?php if ($part['dimensions']): ?>
        <div class="part-spec-item">
          <div class="part-spec-label">Размеры</div>
          <div class="part-spec-val"><?= sanitize($part['dimensions']) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Price & cart -->
      <div class="price-big"><?= formatPrice($part['price']) ?></div>

      <?php if (isLoggedIn()): ?>
        <?php if ($part['stock'] > 0): ?>
          <div class="add-cart-form">
            <input type="number" id="qty-input" class="form-input qty-input" value="1" min="1" max="<?= min((int)$part['stock'], 99) ?>">
            <button class="btn btn-primary btn-lg"
                    data-add-cart="<?= (int)$part['id'] ?>"
                    id="add-cart-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
              В корзину
            </button>
          </div>
        <?php else: ?>
          <div class="alert alert-warning">Товар временно отсутствует</div>
        <?php endif; ?>
      <?php else: ?>
        <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-primary btn-lg">Войдите для заказа</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Description -->
  <?php if ($part['description']): ?>
  <div class="part-desc-section">
    <div class="part-desc-title">ОПИСАНИЕ</div>
    <div class="part-desc-text"><?= nl2br(sanitize($part['description'])) ?></div>
  </div>
  <?php endif; ?>

  <!-- Related parts -->
  <?php if (!empty($related)): ?>
  <div>
    <div class="label-mono mb-16">// Похожие товары</div>
    <div class="grid-4">
      <?php foreach ($related as $rel):
        $relStock = getStockStatus((int)$rel['stock']);
      ?>
      <div class="part-card">
        <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $rel['id'] ?>" style="text-decoration:none;">
          <div class="part-card-img">
            <div class="part-card-img-placeholder">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="0.8"><rect x="2" y="7" width="20" height="10" rx="1"/></svg>
            </div>
            <span class="part-number-badge"><?= sanitize($rel['part_number']) ?></span>
          </div>
        </a>
        <div class="part-card-body">
          <div class="part-card-brand"><?= sanitize($rel['brand_name']) ?></div>
          <div class="part-card-name">
            <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $rel['id'] ?>" style="color:inherit;text-decoration:none;">
              <?= sanitize(truncate($rel['name'], 55)) ?>
            </a>
          </div>
          <div class="part-card-meta">
            <span class="part-card-price"><?= formatPrice($rel['price']) ?></span>
            <span class="badge badge-<?= $relStock['class'] ?>"><?= $relStock['label'] ?></span>
          </div>
        </div>
        <div class="part-card-footer">
          <?php if (isLoggedIn()): ?>
            <button class="btn btn-primary btn-sm btn-block" data-add-cart="<?= $rel['id'] ?>">В корзину</button>
          <?php else: ?>
            <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-sm btn-block">Войти</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
// Sync quantity input with add-to-cart button
document.getElementById('qty-input')?.addEventListener('change', function () {
  const btn = document.getElementById('add-cart-btn');
  if (btn) btn.dataset.qty = this.value;
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
