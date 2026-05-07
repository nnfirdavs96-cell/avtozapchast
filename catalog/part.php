<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/product_card.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flashMessage('danger', 'Товар не найден');
    redirect(APP_URL . '/catalog/index.php');
}

$stmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, b.country AS brand_country, c.name AS category_name, c.slug AS category_slug
     FROM parts p
     LEFT JOIN brands b ON b.id=p.brand_id
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.id=? AND p.is_active=1"
);
$stmt->execute([$id]);
$part = $stmt->fetch();
if (!$part) {
    flashMessage('danger', 'Товар не найден');
    redirect(APP_URL . '/catalog/index.php');
}

// Submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    if (!isLoggedIn()) {
        flashMessage('danger', t('login_to_review'));
        redirect(APP_URL . '/auth/login.php');
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Неверный CSRF-токен');
    } else {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $title  = trim((string)($_POST['title'] ?? ''));
        $body   = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            flashMessage('warning', 'Текст отзыва обязателен');
        } else {
            $ins = $db->prepare("INSERT INTO reviews (part_id,user_id,rating,title,body,status) VALUES (?,?,?,?,?,'pending')");
            $ins->execute([$id, $_SESSION['user_id'], $rating, $title ?: null, $body]);
            flashMessage('success', t('review_pending'));
        }
        redirect(APP_URL . '/catalog/part.php?id=' . $id . '#reviews');
    }
}

$images = getPartImages($id);
$rating = getPartRating($id);

$reviewsStmt = $db->prepare(
    "SELECT r.*, u.username FROM reviews r JOIN users u ON u.id=r.user_id
     WHERE r.part_id=? AND r.status='approved' ORDER BY r.created_at DESC"
);
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll();

$compatStmt = $db->prepare(
    "SELECT cm.name AS model, cm.year_from, cm.year_to, mk.name AS make
     FROM part_compatibility pc
     JOIN car_models cm ON cm.id=pc.model_id
     JOIN car_makes  mk ON mk.id=cm.make_id
     WHERE pc.part_id=?
     ORDER BY mk.name, cm.name"
);
$compatStmt->execute([$id]);
$compat = $compatStmt->fetchAll();

$relStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name FROM parts p LEFT JOIN brands b ON b.id=p.brand_id
     WHERE p.is_active=1 AND p.category_id=? AND p.id<>? ORDER BY RAND() LIMIT 4"
);
$relStmt->execute([$part['category_id'], $id]);
$related = $relStmt->fetchAll();

$mainImg = getPartImage($id);

$pageTitle       = $part['name'];
$pageDescription = mb_substr(strip_tags($part['description'] ?? ''), 0, 160);
$ogType          = 'product';
$ogImage         = $mainImg;

// JSON-LD product schema
$cur = currentCurrency();
$jsonLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'Product',
    'name'       => $part['name'],
    'sku'        => $part['part_number'],
    'description'=> strip_tags($part['description'] ?? ''),
    'brand'      => ['@type' => 'Brand', 'name' => $part['brand_name']],
    'image'      => $mainImg,
    'offers'     => [
        '@type'        => 'Offer',
        'priceCurrency'=> $cur['code'],
        'price'        => convertPrice($part['price'], $cur),
        'availability' => $part['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url'          => canonicalUrl(),
    ],
];
if ($rating['count'] > 0) {
    $jsonLd['aggregateRating'] = [
        '@type'      => 'AggregateRating',
        'ratingValue'=> $rating['avg'],
        'reviewCount'=> $rating['count'],
    ];
}

require_once __DIR__ . '/../includes/header.php';
$stockStatus = getStockStatus((int)$part['stock']);
?>

<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<div class="page-head">
  <div class="container">
    <nav class="breadcrumb">
      <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
      <a href="<?= APP_URL ?>/catalog/index.php"><?= t('catalog') ?></a><span class="sep">/</span>
      <a href="<?= APP_URL ?>/catalog/index.php?category=<?= sanitize($part['category_slug']) ?>"><?= sanitize($part['category_name']) ?></a><span class="sep">/</span>
      <span class="current"><?= sanitize($part['name']) ?></span>
    </nav>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="product-page">

      <!-- Gallery -->
      <div class="product-gallery">
        <div class="gallery-main"><img src="<?= sanitize($mainImg) ?>" alt="<?= sanitize($part['name']) ?>"></div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img):
              $url = UPLOAD_URL . 'parts/' . ltrim($img['path'], '/'); ?>
              <div class="gallery-thumb <?= $i===0?'active':'' ?>" data-full="<?= sanitize($url) ?>"><img src="<?= sanitize($url) ?>" alt=""></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="product-info">
        <h1><?= sanitize($part['name']) ?></h1>

        <div class="product-meta">
          <span><?= t('sku') ?>: <strong><?= sanitize($part['part_number']) ?></strong></span>
          <span>·</span>
          <span><?= t('brand') ?>: <strong><?= sanitize($part['brand_name']) ?></strong></span>
          <?php if ($rating['count']>0): ?>
            <span>·</span>
            <span class="stars"><?= ratingStars($rating['avg']) ?></span>
            <span class="text-muted">(<?= $rating['count'] ?> отзывов)</span>
          <?php endif; ?>
          <span class="badge badge-<?= $stockStatus['class'] ?>"><?= sanitize($stockStatus['label']) ?> · <?= (int)$part['stock'] ?> шт</span>
        </div>

        <div class="product-price-block">
          <div class="product-price"><?= money($part['price']) ?></div>
          <div class="product-price-note">Цена указана за 1 шт. с учётом НДС</div>
        </div>

        <div class="product-actions">
          <div class="qty-input">
            <button type="button" data-qty-minus>−</button>
            <input type="number" name="quantity" value="1" min="1" max="<?= (int)$part['stock'] ?>">
            <button type="button" data-qty-plus>+</button>
          </div>
          <?php if ($part['stock']>0): ?>
            <button class="btn btn-primary btn-lg" data-add-cart="<?= (int)$part['id'] ?>">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
              <?= t('add_to_cart') ?>
            </button>
          <?php else: ?>
            <button class="btn btn-outline btn-lg" disabled><?= t('out_of_stock') ?></button>
          <?php endif; ?>
          <button class="icon-btn <?= isInWishlist($id) ? 'active' : '' ?>" data-wishlist="<?= $id ?>" title="<?= t('add_to_wishlist') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
          </button>
          <button class="icon-btn <?= isInCompare($id) ? 'active' : '' ?>" data-compare="<?= $id ?>" title="<?= t('add_to_compare') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
          </button>
        </div>

        <?php if (!empty($part['description'])): ?>
          <p style="color:var(--text-soft);line-height:1.7"><?= nl2br(sanitize(truncate($part['description'], 240))) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tabs -->
    <div class="product-tabs">
      <div class="tab-nav">
        <button class="tab-btn active" data-tab="desc"><?= t('description') ?></button>
        <button class="tab-btn" data-tab="spec"><?= t('specifications') ?></button>
        <button class="tab-btn" data-tab="compat"><?= t('compatibility') ?></button>
        <button class="tab-btn" data-tab="reviews"><?= t('reviews') ?> (<?= $rating['count'] ?>)</button>
      </div>

      <div class="tab-content active" id="tab-desc">
        <p style="line-height:1.8"><?= nl2br(sanitize($part['description'] ?? '—')) ?></p>
      </div>

      <div class="tab-content" id="tab-spec">
        <table class="spec-table">
          <tr><th><?= t('sku') ?></th><td><?= sanitize($part['part_number']) ?></td></tr>
          <tr><th><?= t('brand') ?></th><td><?= sanitize($part['brand_name']) ?> (<?= sanitize($part['brand_country'] ?? '—') ?>)</td></tr>
          <tr><th><?= t('category') ?></th><td><?= sanitize($part['category_name']) ?></td></tr>
          <?php if (!empty($part['weight'])): ?>
            <tr><th><?= t('weight') ?></th><td><?= sanitize($part['weight']) ?> кг</td></tr>
          <?php endif; ?>
          <?php if (!empty($part['dimensions'])): ?>
            <tr><th><?= t('dimensions') ?></th><td><?= sanitize($part['dimensions']) ?> мм</td></tr>
          <?php endif; ?>
          <tr><th><?= t('in_stock') ?></th><td><?= (int)$part['stock'] ?> шт</td></tr>
        </table>
      </div>

      <div class="tab-content" id="tab-compat">
        <?php if (empty($compat)): ?>
          <p class="text-muted">Информация о совместимости пока не указана. Уточните у менеджера.</p>
        <?php else: ?>
          <p class="mb-16"><strong><?= t('compatible_with') ?>:</strong></p>
          <div class="grid-2">
            <?php
            $byMake = [];
            foreach ($compat as $c) $byMake[$c['make']][] = $c;
            foreach ($byMake as $makeName => $models): ?>
              <div class="checkout-card" style="margin:0">
                <h4><?= sanitize($makeName) ?></h4>
                <ul style="list-style:disc inside;color:var(--text-soft);font-size:0.9rem">
                  <?php foreach ($models as $m): ?>
                    <li><?= sanitize($m['model']) ?> (<?= (int)$m['year_from'] ?>–<?= (int)$m['year_to'] ?>)</li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="tab-content" id="tab-reviews">
        <a id="reviews"></a>
        <?php if (empty($reviews)): ?>
          <p class="text-muted mb-24"><?= t('no_reviews_yet') ?></p>
        <?php else: ?>
          <?php foreach ($reviews as $r): ?>
            <div class="review-item">
              <div class="review-avatar"><?= sanitize(mb_strtoupper(mb_substr($r['username'], 0, 1))) ?></div>
              <div>
                <div class="review-head">
                  <span class="review-name"><?= sanitize($r['username']) ?></span>
                  <span class="stars"><?= ratingStars((float)$r['rating']) ?></span>
                  <span class="review-date"><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
                </div>
                <?php if (!empty($r['title'])): ?>
                  <div class="review-title"><?= sanitize($r['title']) ?></div>
                <?php endif; ?>
                <div class="review-body"><?= nl2br(sanitize($r['body'])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="checkout-card mt-32">
          <h3><?= t('write_review') ?></h3>
          <?php if (!isLoggedIn()): ?>
            <p><?= t('login_to_review') ?>. <a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a></p>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
              <input type="hidden" name="action" value="review">
              <div class="form-group">
                <label class="form-label"><?= t('rating') ?></label>
                <select name="rating" class="form-select" style="max-width:180px">
                  <?php for ($i=5; $i>=1; $i--): ?>
                    <option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"><?= t('review_title') ?></label>
                <input type="text" name="title" class="form-input" maxlength="180">
              </div>
              <div class="form-group">
                <label class="form-label"><?= t('review_body') ?></label>
                <textarea name="body" class="form-textarea" required></textarea>
              </div>
              <button type="submit" class="btn btn-primary"><?= t('submit_review') ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Related -->
    <?php if (!empty($related)): ?>
    <div class="section-head left mt-32">
      <h2><?= t('related_products') ?></h2>
    </div>
    <div class="products-grid">
      <?php foreach ($related as $rp) renderProductCard($rp); ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
