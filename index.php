<?php
require_once __DIR__ . '/config/config.php';

$pageTitle       = t('home');
$pageDescription = t('hero_subtitle');

$db = getDB();

$catStmt = $db->query("SELECT * FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order LIMIT 6");
$featCategories = $catStmt->fetchAll();

$brandStmt = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name LIMIT 8");
$featBrands = $brandStmt->fetchAll();

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

$bestStmt = $db->query(
    "SELECT p.*, b.name AS brand_name FROM parts p LEFT JOIN brands b ON b.id=p.brand_id
     WHERE p.is_active=1 ORDER BY p.stock DESC LIMIT 8"
);
$bestParts = $bestStmt->fetchAll();

$blogStmt = $db->query("SELECT * FROM blog_posts WHERE is_published=1 ORDER BY created_at DESC LIMIT 4");
$blogPosts = $blogStmt->fetchAll();

$makes = getCarMakes();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/product_card.php';

$catIcons = [
    'dvigatel'           => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="10" rx="1"/><path d="M8 7V5M16 7V5M4 12h2M18 12h2M8 12h8"/></svg>',
    'tormoznaya-sistema' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>',
    'podveska'           => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 18l4-12M20 18l-4-12M8 6h8"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>',
    'elektrika'          => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
    'kuzov'              => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 17H3a2 2 0 01-2-2V7a2 2 0 012-2h16a2 2 0 012 2v8a2 2 0 01-2 2h-2"/><path d="M5 17l2-6h10l2 6"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>',
    'transmissiya'       => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="5" cy="12" r="3"/><circle cx="19" cy="12" r="3"/><path d="M8 12h8"/><path d="M5 9V5M19 9V5M5 19v-4M19 19v-4"/></svg>',
];
?>

<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<!-- ── Hero ─────────────────────────────────────────────── -->
<section class="hero">
  <div class="container hero-inner">
    <span class="hero-eyebrow">// <?= t('site_tagline') ?></span>
    <h1><?= sanitize(t('hero_title')) ?> <span>AutoDoc</span></h1>
    <p><?= sanitize(t('hero_subtitle')) ?></p>
    <div class="hero-cta">
      <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary btn-lg">
        <?= t('hero_cta') ?>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
      <a href="<?= APP_URL ?>/search/vin.php" class="btn btn-outline btn-lg" style="color:#fff;border-color:rgba(255,255,255,0.4)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <?= t('find_by_vin') ?>
      </a>
    </div>
  </div>
</section>

<!-- ── Quick Finders (VIN + by car) ───────────────────────── -->
<div class="container quick-finder">
  <div class="qf-grid">
    <div class="qf-card">
      <h3>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <?= t('find_by_vin') ?>
      </h3>
      <form action="<?= APP_URL ?>/search/vin.php" method="get">
        <div class="form-group">
          <input type="text" name="vin" maxlength="17" minlength="11" required class="form-input" placeholder="<?= t('enter_vin') ?>" style="text-transform:uppercase;letter-spacing:1px">
        </div>
        <button type="submit" class="btn btn-primary"><?= t('find_parts') ?></button>
      </form>
    </div>
    <div class="qf-card">
      <h3>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 01-2-2V7a2 2 0 012-2h16a2 2 0 012 2v8a2 2 0 01-2 2h-2"/><path d="M5 17l2-6h10l2 6"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>
        <?= t('find_by_car') ?>
      </h3>
      <form action="<?= APP_URL ?>/catalog/index.php" method="get">
        <div class="form-row">
          <select name="make_id" id="vin-make" class="form-select" required>
            <option value=""><?= t('select_make') ?></option>
            <?php foreach ($makes as $m): ?>
              <option value="<?= (int)$m['id'] ?>"><?= sanitize($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="model_id" id="vin-model" class="form-select" required>
            <option value=""><?= t('select_model') ?></option>
          </select>
        </div>
        <div class="form-row" style="margin-top:10px">
          <select name="year" class="form-select">
            <option value=""><?= t('select_year') ?></option>
            <?php for ($y = (int)date('Y'); $y >= 1995; $y--): ?>
              <option value="<?= $y ?>"><?= $y ?></option>
            <?php endfor; ?>
          </select>
          <button type="submit" class="btn btn-primary"><?= t('find_parts') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Categories ───────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow"><?= t('catalog') ?></span>
      <h2><?= t('featured_categories') ?> <span>·</span></h2>
      <p>Все категории автозапчастей: от двигателя до кузовных деталей.</p>
    </div>
    <div class="cat-cards">
      <?php foreach ($featCategories as $i => $cat):
        $icon = $catIcons[$cat['slug']] ?? '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
        $count = (int)$db->query("SELECT COUNT(*) FROM parts WHERE category_id=" . (int)$cat['id'] . " AND is_active=1")->fetchColumn();
      ?>
      <a href="<?= APP_URL ?>/catalog/index.php?category=<?= sanitize($cat['slug']) ?>" class="cat-card fade-up delay-<?= ($i % 4) + 1 ?>">
        <div class="cat-card-icon"><?= $icon ?></div>
        <div class="cat-card-name"><?= sanitize(tField('category',(int)$cat['id'],'name',$cat['name'])) ?></div>
        <div class="cat-card-count"><?= $count ?> товаров</div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Promo banners ────────────────────────────────────── -->
<section class="section-sm">
  <div class="container promo-grid">
    <div class="promo">
      <div class="promo-content">
        <small>СКИДКА</small>
        <h3>Тормозные диски Brembo<br>—15% по промокоду BRAKE15</h3>
        <a href="<?= APP_URL ?>/catalog/index.php?category=tormoznaya-sistema">К товарам →</a>
      </div>
    </div>
    <div class="promo bg-2">
      <div class="promo-content">
        <small>ХИТ</small>
        <h3>Свечи NGK с иридием<br>от 620 ₽</h3>
        <a href="<?= APP_URL ?>/catalog/index.php?category=svechi-zazgiganiya">Подробнее →</a>
      </div>
    </div>
    <div class="promo bg-3">
      <div class="promo-content">
        <small>НОВИНКА</small>
        <h3>Сезонная замена ГРМ —<br>комплекты Gates со скидкой</h3>
        <a href="<?= APP_URL ?>/catalog/index.php?category=remni-i-tsepi">Выбрать →</a>
      </div>
    </div>
  </div>
</section>

<!-- ── New arrivals ─────────────────────────────────────── -->
<section class="section bg-soft">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow"><?= t('catalog') ?></span>
      <h2><?= t('new_arrivals') ?></h2>
      <p>Свежие поступления на наш склад — оригинальные запчасти от ведущих брендов.</p>
    </div>
    <div class="products-grid">
      <?php foreach ($featParts as $p) renderProductCard($p); ?>
    </div>
    <div class="text-center mt-32">
      <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-outline btn-lg"><?= t('view_all') ?></a>
    </div>
  </div>
</section>

<!-- ── Why us ───────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Преимущества</span>
      <h2><?= t('why_us') ?></h2>
    </div>
    <div class="feature-grid">
      <div class="feature">
        <div class="feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg></div>
        <div><h4><?= t('free_shipping') ?></h4><p><?= t('free_shipping_d') ?></p></div>
      </div>
      <div class="feature">
        <div class="feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div><h4><?= t('support_247') ?></h4><p><?= t('support_247_d') ?></p></div>
      </div>
      <div class="feature">
        <div class="feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div><h4><?= t('quality_guarantee') ?></h4><p><?= t('quality_guarantee_d') ?></p></div>
      </div>
      <div class="feature">
        <div class="feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 10 9 4 9 8 21 8 21 14 9 14 9 18 3 12"/></svg></div>
        <div><h4><?= t('easy_return') ?></h4><p><?= t('easy_return_d') ?></p></div>
      </div>
    </div>
  </div>
</section>

<!-- ── Best sellers ─────────────────────────────────────── -->
<section class="section bg-soft">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">TOP</span>
      <h2><?= t('best_sellers') ?></h2>
    </div>
    <div class="products-grid">
      <?php foreach ($bestParts as $p) renderProductCard($p); ?>
    </div>
  </div>
</section>

<!-- ── Brands ───────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Производители</span>
      <h2><?= t('our_brands') ?></h2>
    </div>
    <div class="brand-strip">
      <?php foreach ($featBrands as $b): ?>
        <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$b['id'] ?>" class="brand-tile">
          <div class="name"><?= sanitize($b['name']) ?></div>
          <div class="country"><?= sanitize($b['country'] ?? '') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Blog ─────────────────────────────────────────────── -->
<?php if (!empty($blogPosts)): ?>
<section class="section bg-soft">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow"><?= t('blog') ?></span>
      <h2><?= t('latest_news') ?></h2>
    </div>
    <div class="blog-grid">
      <?php foreach ($blogPosts as $post): ?>
        <a href="<?= APP_URL ?>/blog/post.php?slug=<?= sanitize($post['slug']) ?>" class="blog-card">
          <div class="cover" data-letter="<?= sanitize(mb_substr($post['title'],0,1)) ?>"></div>
          <div class="body">
            <div class="meta"><?= date('d.m.Y', strtotime($post['created_at'])) ?></div>
            <h3><?= sanitize($post['title']) ?></h3>
            <p><?= sanitize(truncate($post['excerpt'] ?? '', 110)) ?></p>
            <span class="more"><?= t('read_more') ?> →</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
