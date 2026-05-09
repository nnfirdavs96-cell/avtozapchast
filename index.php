<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Главная — Профессиональные автозапчасти';

// Fetch featured data
$db = getDB();

// Categories (top-level)
$catStmt = $db->query("SELECT * FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order LIMIT 6");
$featCategories = $catStmt->fetchAll();

// Brands
$brandStmt = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name LIMIT 8");
$featBrands = $brandStmt->fetchAll();

// Featured parts (recently added)
$partsStmt = $db->query(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1
     ORDER BY p.created_at DESC
     LIMIT 8"
);
$featParts = $partsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── Hero ─────────────────────────────────────────────────── */
.hero {
  position: relative;
  min-height: 580px;
  display: flex;
  align-items: center;
  overflow: hidden;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border);
}
.hero-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 60% 80% at 70% 50%, rgba(255,107,53,0.07) 0%, transparent 70%),
    repeating-linear-gradient(90deg, transparent 0px, transparent 79px, rgba(255,255,255,0.015) 79px, rgba(255,255,255,0.015) 80px),
    repeating-linear-gradient(0deg, transparent 0px, transparent 79px, rgba(255,255,255,0.015) 79px, rgba(255,255,255,0.015) 80px);
  pointer-events: none;
}
.hero-content {
  position: relative;
  z-index: 1;
  max-width: 1440px;
  margin: 0 auto;
  padding: 80px 24px;
  width: 100%;
}
.hero-eyebrow {
  font-family: var(--font-mono);
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: var(--accent);
  margin-bottom: 16px;
  opacity: 0;
  animation: fadeInUp 0.6s 0.1s ease forwards;
}
.hero-title {
  font-family: var(--font-display);
  font-size: clamp(3rem, 7vw, 6.5rem);
  line-height: 0.92;
  letter-spacing: 4px;
  color: var(--text-primary);
  margin-bottom: 24px;
  max-width: 820px;
  opacity: 0;
  animation: fadeInUp 0.6s 0.2s ease forwards;
}
.hero-title span { color: var(--accent); }
.hero-sub {
  color: var(--text-secondary);
  font-size: 1rem;
  max-width: 480px;
  margin-bottom: 40px;
  line-height: 1.7;
  opacity: 0;
  animation: fadeInUp 0.6s 0.35s ease forwards;
}
.hero-search-wrap {
  display: flex;
  gap: 12px;
  max-width: 620px;
  opacity: 0;
  animation: fadeInUp 0.6s 0.45s ease forwards;
}
.hero-search-input {
  flex: 1;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 20px;
  color: var(--text-primary);
  font-family: var(--font-mono);
  font-size: 1rem;
  outline: none;
  transition: border-color 0.2s;
}
.hero-search-input:focus { border-color: var(--accent); }
.hero-search-input::placeholder { color: var(--text-muted); font-size: 0.875rem; }
.hero-search-btn {
  padding: 16px 28px;
  background: var(--accent);
  border: none;
  border-radius: var(--radius);
  color: #fff;
  font-family: var(--font-display);
  font-size: 1.1rem;
  letter-spacing: 1px;
  cursor: pointer;
  transition: background 0.2s;
  white-space: nowrap;
}
.hero-search-btn:hover { background: var(--accent-dark); }
.hero-stats {
  display: flex;
  gap: 40px;
  margin-top: 48px;
  opacity: 0;
  animation: fadeInUp 0.6s 0.55s ease forwards;
}
.hero-stat-num {
  font-family: var(--font-display);
  font-size: 1.8rem;
  color: var(--accent);
  letter-spacing: 2px;
}
.hero-stat-label {
  font-family: var(--font-mono);
  font-size: 0.65rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.hero-graphic {
  position: absolute;
  right: 80px;
  top: 50%;
  transform: translateY(-50%);
  opacity: 0.04;
  font-family: var(--font-display);
  font-size: 22vw;
  line-height: 1;
  letter-spacing: -10px;
  color: var(--text-primary);
  pointer-events: none;
  user-select: none;
}
@media (max-width: 900px) { .hero-graphic { display: none; } .hero-stats { gap: 24px; flex-wrap: wrap; } }

/* ── Category grid ─────────────────────────────────────── */
.cat-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}
@media (max-width: 1100px) { .cat-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 560px)  { .cat-grid { grid-template-columns: repeat(2, 1fr); } }
.cat-card {
  background: var(--bg-card);
  padding: 28px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  transition: background 0.2s;
  position: relative;
  overflow: hidden;
}
.cat-card::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 2px;
  background: var(--accent);
  transform: scaleX(0);
  transition: transform 0.2s;
}
.cat-card:hover { background: var(--bg-hover); }
.cat-card:hover::after { transform: scaleX(1); }
.cat-icon {
  width: 48px;
  height: 48px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-secondary);
  color: var(--accent);
  transition: border-color 0.2s, background 0.2s;
}
.cat-card:hover .cat-icon { border-color: var(--accent); background: var(--accent-glow); }
.cat-name {
  font-family: var(--font-display);
  font-size: 1rem;
  letter-spacing: 1px;
  color: var(--text-primary);
  text-align: center;
}

/* ── Brands row ─────────────────────────────────────────── */
.brands-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.brand-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text-secondary);
  font-family: var(--font-mono);
  font-size: 0.78rem;
  text-decoration: none;
  transition: all 0.2s;
  white-space: nowrap;
}
.brand-chip:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-glow); }
.brand-flag {
  font-size: 0.65rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
}

/* ── Why us ─────────────────────────────────────────────── */
.why-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1px;
  background: var(--border);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}
@media (max-width: 900px) { .why-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .why-grid { grid-template-columns: 1fr; } }
.why-item {
  background: var(--bg-card);
  padding: 32px 24px;
  position: relative;
}
.why-item::before {
  content: attr(data-num);
  position: absolute;
  top: 16px;
  right: 16px;
  font-family: var(--font-display);
  font-size: 3rem;
  color: var(--text-primary);
  opacity: 0.04;
  line-height: 1;
}
.why-icon {
  width: 40px;
  height: 40px;
  background: var(--accent-glow);
  border: 1px solid var(--accent);
  border-radius: var(--radius);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--accent);
  margin-bottom: 16px;
}
.why-title {
  font-family: var(--font-display);
  font-size: 1.15rem;
  letter-spacing: 1px;
  margin-bottom: 8px;
}
.why-desc { color: var(--text-muted); font-size: 0.825rem; line-height: 1.6; }

/* ── Parts grid ─────────────────────────────────────────── */
.parts-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
@media (max-width: 1100px) { .parts-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .parts-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .parts-grid { grid-template-columns: 1fr; } }
</style>

<!-- ── HERO ──────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-graphic">АЗ</div>
  <div class="hero-content">
    <div class="hero-eyebrow">// Профессиональный склад автозапчастей</div>
    <h1 class="hero-title">НАЙДИ ЗАПЧАСТЬ<br>ЗА <span>СЕКУНДЫ</span></h1>
    <p class="hero-sub">
      Оригинальные и аналоговые запчасти от ведущих мировых производителей. Быстрый подбор по номеру детали или названию.
    </p>
    <form class="hero-search-wrap" action="<?= APP_URL ?>/search/index.php" method="get">
      <input type="text" name="q" class="hero-search-input" placeholder="Введите номер детали — напр. BKR6EK, 0280218116...">
      <button type="submit" class="hero-search-btn">НАЙТИ</button>
    </form>
    <div class="hero-stats">
      <div>
        <div class="hero-stat-num">50 000+</div>
        <div class="hero-stat-label">Позиций в наличии</div>
      </div>
      <div>
        <div class="hero-stat-num">100+</div>
        <div class="hero-stat-label">Брендов</div>
      </div>
      <div>
        <div class="hero-stat-num">24 ч</div>
        <div class="hero-stat-label">Доставка по Москве</div>
      </div>
      <div>
        <div class="hero-stat-num">12 лет</div>
        <div class="hero-stat-label">На рынке</div>
      </div>
    </div>
  </div>
</section>

<!-- ── CATEGORIES ─────────────────────────────────────────────── -->
<section class="section container">
  <div class="flex-between mb-24">
    <div>
      <div class="label-mono mb-8">// Каталог</div>
      <h2 class="section-heading">КАТЕГОРИИ</h2>
    </div>
    <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-outline btn-sm">Все категории →</a>
  </div>

  <div class="cat-grid">
    <?php
    $catIcons = [
      'dvigatel'           => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="10" rx="1"/><path d="M8 7V5M16 7V5M4 12h2M18 12h2M8 12h8"/></svg>',
      'tormoznaya-sistema' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>',
      'podveska'           => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 18l4-12M20 18l-4-12M8 6h8"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>',
      'elektrika'          => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
      'kuzov'              => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 17H3a2 2 0 01-2-2V7a2 2 0 012-2h16a2 2 0 012 2v8a2 2 0 01-2 2h-2"/><path d="M5 17l2-6h10l2 6"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>',
      'transmissiya'       => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="5" cy="12" r="3"/><circle cx="19" cy="12" r="3"/><path d="M8 12h8"/><path d="M5 9V5M19 9V5M5 19v-4M19 19v-4"/></svg>',
    ];
    foreach ($featCategories as $cat):
      $icon = $catIcons[$cat['slug']] ?? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
    ?>
    <a href="<?= APP_URL ?>/catalog/index.php?category=<?= sanitize($cat['slug']) ?>" class="cat-card">
      <div class="cat-icon"><?= $icon ?></div>
      <span class="cat-name"><?= sanitize($cat['name']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── BRANDS ─────────────────────────────────────────────────── -->
<section class="section-sm container">
  <div class="label-mono mb-16">// Производители</div>
  <div class="brands-row">
    <?php foreach ($featBrands as $brand): ?>
    <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$brand['id'] ?>" class="brand-chip">
      <strong><?= sanitize($brand['name']) ?></strong>
      <span class="brand-flag"><?= sanitize($brand['country'] ?? '') ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── FEATURED PARTS ──────────────────────────────────────────── -->
<section class="section container">
  <div class="flex-between mb-24">
    <div>
      <div class="label-mono mb-8">// Новые поступления</div>
      <h2 class="section-heading">ПОПУЛЯРНЫЕ ТОВАРЫ</h2>
    </div>
    <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-outline btn-sm">Весь каталог →</a>
  </div>

  <div class="parts-grid">
    <?php foreach ($featParts as $i => $part):
      $stock  = getStockStatus((int)$part['stock']);
    ?>
    <div class="part-card animate-fade-up animate-delay-<?= min($i + 1, 5) ?>">
      <div class="part-card-img">
        <div class="part-card-img-placeholder">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="0.8"><rect x="2" y="7" width="20" height="10" rx="1"/><path d="M8 7V5M16 7V5M4 12h2M18 12h2M8 12h8"/></svg>
        </div>
        <span class="part-number-badge"><?= sanitize($part['part_number']) ?></span>
      </div>
      <div class="part-card-body">
        <div class="part-card-brand"><?= sanitize($part['brand_name']) ?></div>
        <div class="part-card-name"><?= sanitize(truncate($part['name'], 60)) ?></div>
        <div class="part-card-meta">
          <span class="part-card-price"><?= formatPrice($part['price']) ?></span>
          <span class="badge badge-<?= $stock['class'] ?>"><?= $stock['label'] ?></span>
        </div>
      </div>
      <div class="part-card-footer">
        <?php if (isLoggedIn()): ?>
          <button class="btn btn-primary btn-sm btn-block"
                  data-add-cart="<?= (int)$part['id'] ?>">В корзину</button>
        <?php else: ?>
          <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-sm btn-block">Войдите для заказа</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── WHY US ─────────────────────────────────────────────────── -->
<section class="section container">
  <div class="mb-24">
    <div class="label-mono mb-8">// Наши преимущества</div>
    <h2 class="section-heading">ПОЧЕМУ МЫ</h2>
  </div>
  <div class="why-grid">
    <div class="why-item" data-num="01">
      <div class="why-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div class="why-title">ГАРАНТИЯ КАЧЕСТВА</div>
      <p class="why-desc">Только оригинальные запчасти и проверенные аналоги от сертифицированных поставщиков.</p>
    </div>
    <div class="why-item" data-num="02">
      <div class="why-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="why-title">БЫСТРАЯ ДОСТАВКА</div>
      <p class="why-desc">Доставка по Москве за 24 часа, по России — 2-5 рабочих дней. Отслеживание в реальном времени.</p>
    </div>
    <div class="why-item" data-num="03">
      <div class="why-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div class="why-title">ТОЧНЫЙ ПОДБОР</div>
      <p class="why-desc">Поиск по оригинальному номеру детали. Полная база кросс-номеров и совместимостей.</p>
    </div>
    <div class="why-item" data-num="04">
      <div class="why-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      </div>
      <div class="why-title">ТЕХПОДДЕРЖКА</div>
      <p class="why-desc">Опытные специалисты помогут с подбором и ответят на любые технические вопросы.</p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
