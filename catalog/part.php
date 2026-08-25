<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/parts/grouping.php';
require_once dirname(__DIR__) . '/includes/seller_reviews.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flashMessage('danger', 'Товар не найден.');
    redirect(APP_URL . '/catalog/index.php');
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name, b.country AS brand_country,
            c.name AS category_name, c.slug AS category_slug,
            s.shop_name AS seller_shop, s.slug AS seller_slug
     FROM parts p
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN sellers s ON s.id = p.seller_id
     WHERE p.id = ? AND p.is_active = 1
       AND (p.seller_id IS NULL OR p.moderation_status = 'active')"
);
$stmt->execute([$id]);
$part = $stmt->fetch();

if (!$part) {
    flashMessage('danger', 'Товар не найден или снят с продажи.');
    redirect(APP_URL . '/catalog/index.php');
}

// Canonicalise the legacy /catalog/part.php?id=N address to the pretty
// /product/{id}-{slug} URL with a 301, so links and search engines consolidate on
// one URL. Requests that already arrived via /product/… are served as-is.
$prettyUrl = partUrl($part);
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/catalog/part.php') !== false) {
    header('Location: ' . $prettyUrl, true, 301);
    exit;
}

// Предложения этой карточки — кто ещё продаёт ту же деталь.
// Номер карточки берём у открытого предложения: покупатель мог прийти по ссылке
// на любое из них, и все они должны показывать один и тот же список.
$cardKey   = (int)($part['product_id'] ?: $part['id']);
$cardOffers = partsCardOffers($db, $cardKey);
$bestOfferId = $cardOffers ? (int)$cardOffers[0]['id'] : 0;   // победитель buy-box

// Рейтинги продавцов — покупателю нужно на что-то опереться, выбирая между
// предложениями: цена не единственный критерий.
$offerRatings = sellerRatings($db, array_column($cardOffers, 'seller_id'));

// Варианты — то же самое от ТОГО ЖЕ продавца в другом исполнении (цвет, объём,
// возраст). Не путать с предложениями выше: там конкуренты, здесь линейка одного
// магазина. Ключ семейства намеренно не смотрит на product_id (см. grouping.php).
$familyKey = (int)($part['product_group_id'] ?: $part['id']);
$variants  = partsFamilyVariants($db, $familyKey, isset($part['seller_id']) ? (int)$part['seller_id'] ?: null : null);
if (count($variants) < 2) $variants = [];   // семейство из одного — показывать нечего

// Атрибуты: оси вариантов рисуем переключателем, характеристики — таблицей.
$variantIds  = $variants ? array_column($variants, 'id') : [(int)$part['id']];
$allAttrs    = partsAttributes($db, $variantIds);
$myAxes      = array_filter($allAttrs[(int)$part['id']] ?? [], fn($a) => $a['kind'] === 'axis');
$mySpecs     = array_filter($allAttrs[(int)$part['id']] ?? [], fn($a) => $a['kind'] === 'spec');

// Related products (same category)
// Модерация: без этого условия в «похожих» показывались неодобренные листинги
// продавцов. Группировка — чтобы один товар от трёх продавцов не занял всю
// подборку; заодно исключаем всю карточку целиком, а не только открытое
// предложение, иначе «похожим» оказался бы тот же самый товар от соседа.
$relSrc = partsBuyBoxSource(
    "WHERE p.is_active = 1
       AND (p.seller_id IS NULL OR p.moderation_status = 'active')
       AND COALESCE(p.product_id, p.id) <> ?
       AND p.category_id = ?",
    $db
);
$relStmt = $db->prepare(
    "SELECT p.*, b.name AS brand_name
     FROM $relSrc
     LEFT JOIN brands b ON b.id = p.brand_id
     ORDER BY p.created_at DESC
     LIMIT 5"
);
$relStmt->execute([$cardKey, $part['category_id']]);
$related = $relStmt->fetchAll();

// Parse images
$images = is_string($part['images']) ? json_decode($part['images'], true) : ($part['images'] ?? []);
if (!is_array($images)) $images = [];

$mainImage = !empty($images[0]) ? productImageUrl($images, 0) : APP_URL . '/assets/img/product/product1.jpg';

// Approved reviews + aggregate rating
$reviews   = [];
$myReview  = null;
$canReview = false;
try {
    // Отзывы всей КАРТОЧКИ, а не только открытого предложения: покупатель писал о
    // товаре, а не о том, у кого именно он его взял. Иначе на карточке победителя
    // buy-box оказалось бы два отзыва вместо двадцати.
    $revStmt = $db->prepare(
        "SELECT r.rating, r.comment, r.created_at, u.username
         FROM product_reviews r
         JOIN users u ON u.id = r.user_id
         JOIN parts p ON p.id = r.part_id
         WHERE COALESCE(p.product_id, p.id) = ? AND r.status = 'approved'
         ORDER BY r.created_at DESC"
    );
    $revStmt->bindValue(1, $cardKey, PDO::PARAM_INT);
    $revStmt->execute();
    $reviews = $revStmt->fetchAll();

    // Current user's own review (any status) — so they see its moderation state
    if (isLoggedIn()) {
        $uid = (int)$_SESSION['user_id'];
        $mr = $db->prepare("SELECT rating, comment, status FROM product_reviews WHERE part_id = ? AND user_id = ? LIMIT 1");
        $mr->execute([$id, $uid]);
        $myReview  = $mr->fetch() ?: null;
        $canReview = userPurchasedPart($uid, $id);
    }
} catch (PDOException $e) {
    // Reviews migration not applied yet — product page still works without reviews
    $reviews = [];
}
$reviewCount = count($reviews);
$avgRating   = $reviewCount ? round(array_sum(array_column($reviews, 'rating')) / $reviewCount, 1) : 0;

$stock     = getStockStatus((int)$part['stock']);
$pageTitle = $part['name'] . ' — ' . getSetting('site_name');
$csrf      = generateCsrfToken();

// ── SEO: per-page meta + schema.org Product (JSON-LD) ─────────────────────
$cleanDesc = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)($part['description'] ?? '')))), 0, 500);
$pageDescription = $cleanDesc !== ''
    ? $cleanDesc
    : trim($part['name'] . (!empty($part['brand_name']) ? ', ' . $part['brand_name'] : '') . '. ' . ($part['category_name'] ?? ''));

// Канонический адрес карточки — страница ПОБЕДИТЕЛЯ buy-box. У одного товара
// столько адресов, сколько продавцов, и содержимое у них почти одинаковое: без
// canonical поисковик считал бы это дублями и размазывал вес между ними.
// Победитель — то же предложение, что показано в каталоге, поэтому адрес совпадает
// с тем, по которому покупатель обычно и приходит.
$canonical = $bestOfferId && $bestOfferId !== (int)$part['id']
    ? partUrl($cardOffers[0])
    : $prettyUrl;
$ogType    = 'product';

// Absolute image URL for og:image / schema
$ogImage = $mainImage;
if ($ogImage !== '' && !preg_match('#^https?://#i', $ogImage)) {
    if (str_starts_with($ogImage, '//'))      $ogImage = 'https:' . $ogImage;
    elseif ($ogImage[0] === '/')              $ogImage = rtrim(APP_URL, '/') . $ogImage;
}

$ldPriceCur = getActiveCurrency();
$ldPrice    = number_format(convertPrice((float)$part['price']), 2, '.', '');
$ld = [
    '@context'    => 'https://schema.org/',
    '@type'       => 'Product',
    'name'        => $part['name'],
    'sku'         => $part['part_number'],
    'mpn'         => $part['part_number'],
    'description' => $pageDescription,
];
if ($ogImage !== '') $ld['image'] = $ogImage;
if (!empty($part['brand_name'])) $ld['brand'] = ['@type' => 'Brand', 'name' => $part['brand_name']];
$ld['offers'] = [
    '@type'         => 'Offer',
    'url'           => $canonical,
    'priceCurrency' => $ldPriceCur,
    'price'         => $ldPrice,
    'availability'  => (int)$part['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
];
if ($reviewCount > 0) {
    $ld['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => (string)$avgRating,
        'reviewCount' => (string)$reviewCount,
    ];
}
$headExtra = '<script type="application/ld+json">'
    . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
    . '</script>';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb([
    ['label' => t('home'),  'url' => APP_URL . '/index.php'],
    ['label' => t('shop'),  'url' => APP_URL . '/catalog/index.php'],
    ['label' => sanitize($part['category_name']), 'url' => APP_URL . '/catalog/category.php?slug=' . urlencode($part['category_slug'] ?? '')],
    ['label' => sanitize($part['name'])],
]) ?>

<!--product details area start-->
<div class="product_page_bg">
    <div class="container">
        <!--product details start-->
        <div class="product_details">
            <div class="row">
                <!-- Product Images -->
                <div class="col-lg-5 col-md-6">
                    <div class="product-details-tab">
                        <div id="img-1" class="zoomWrapper single-zoom">
                            <a href="#">
                                <img id="zoom1"
                                     src="<?= sanitize($mainImage) ?>"
                                     data-zoom-image="<?= sanitize($mainImage) ?>"
                                     alt="<?= sanitize($part['name']) ?>">
                            </a>
                        </div>
                        <?php if (count($images) > 1): ?>
                        <div class="single-zoom-thumb">
                            <ul class="s-tab-zoom owl-carousel single-product-active" id="gallery_01">
                                <?php foreach ($images as $i => $img): $imgU = productImageUrl($images, $i); ?>
                                <li>
                                    <a href="#"
                                       class="elevatezoom-gallery <?= $i === 0 ? 'active' : '' ?>"
                                       data-update=""
                                       data-image="<?= sanitize($imgU) ?>"
                                       data-zoom-image="<?= sanitize($imgU) ?>">
                                        <img src="<?= sanitize($imgU) ?>"
                                             alt="<?= sanitize($part['name']) ?> <?= $i + 1 ?>"/>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                        <div class="single-zoom-thumb">
                            <ul class="s-tab-zoom owl-carousel single-product-active" id="gallery_01">
                                <li>
                                    <a href="#" class="elevatezoom-gallery active"
                                       data-update=""
                                       data-image="<?= sanitize($mainImage) ?>"
                                       data-zoom-image="<?= sanitize($mainImage) ?>">
                                        <img src="<?= sanitize($mainImage) ?>"
                                             alt="<?= sanitize($part['name']) ?>"/>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- /product images -->

                <!-- Product Info -->
                <div class="col-lg-7 col-md-6">
                    <div class="product_d_right">
                        <form action="<?= APP_URL ?>/api/cart.php" method="post" id="add-cart-form">
                            <input type="hidden" name="action"  value="add">
                            <input type="hidden" name="part_id" value="<?= (int)$part['id'] ?>">
                            <input type="hidden" name="_csrf"   value="<?= sanitize($csrf) ?>">

                            <h3>
                                <a href="<?= partUrl($part) ?>">
                                    <?= sanitize($part['name']) ?>
                                </a>
                            </h3>

                            <div class="product_meta" style="margin-bottom:10px;">
                                <span>
                                    <?= t('brand') ?>:
                                    <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$part['brand_id'] ?>">
                                        <?= sanitize($part['brand_name']) ?>
                                    </a>
                                </span>
                                <?php if ($part['category_name']): ?>
                                &nbsp;&nbsp;
                                <span>
                                    <?= t('category') ?>:
                                    <a href="<?= APP_URL ?>/catalog/category.php?slug=<?= urlencode($part['category_slug'] ?? '') ?>">
                                        <?= sanitize($part['category_name']) ?>
                                    </a>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($part['seller_shop'])): ?>
                                &nbsp;&nbsp;
                                <span class="product_seller">
                                    Продавец:
                                    <a href="<?= APP_URL ?>/seller_shop.php?slug=<?= urlencode($part['seller_slug'] ?? '') ?>">
                                        <i class="fa fa-briefcase"></i> <?= sanitize($part['seller_shop']) ?>
                                    </a>
                                </span>
                                <?php endif; ?>
                            </div>

                            <?php $pDisc = discountPercent($part); ?>
                            <div class="price_box">
                                <?php if ($pDisc > 0): ?>
                                <span class="old_price"><?= formatPrice($part['old_price']) ?></span>
                                <span class="current_price"><?= formatPrice($part['price']) ?></span>
                                <span class="label_sale" style="margin-left:8px;">-<?= $pDisc ?>%</span>
                                <?php else: ?>
                                <span class="current_price"><?= formatPrice($part['price']) ?></span>
                                <?php endif; ?>
                            </div>

            <?php if ($variants): ?>
                            <!-- Переключатель исполнений: каждый вариант — своя страница
                                 со своей ценой и наличием, поэтому это ссылки, а не JS. -->
                            <div class="bb_variants">
                              <?php foreach ($variants as $v):
                                  $vAxes = array_filter($allAttrs[(int)$v['id']] ?? [], fn($a) => $a['kind'] === 'axis');
                                  // Подпись варианта — значения его осей («Чёрный · 1–3 года»).
                                  // Если осей нет, честнее показать название, чем пустую кнопку.
                                  $label = $vAxes
                                      ? implode(' · ', array_column($vAxes, 'value'))
                                      : mb_substr((string)$v['name'], 0, 28);
                                  $isCur = (int)$v['id'] === (int)$part['id'];
                              ?>
                              <a class="bb_variant<?= $isCur ? ' bb_variant--current' : '' ?>"
                                 href="<?= $isCur ? 'javascript:void(0)' : sanitize(partUrl($v)) ?>"
                                 title="<?= sanitize($v['name']) ?>">
                                <span class="bb_variant_label"><?= sanitize($label) ?></span>
                                <span class="bb_variant_price"><?= formatPrice((float)$v['price']) ?></span>
                                <?php if ((int)$v['stock'] <= 0): ?>
                                <span class="bb_variant_out">под заказ</span>
                                <?php endif; ?>
                              </a>
                              <?php endforeach; ?>
                            </div>
            <?php endif; ?>

            <?php if ($myAxes): ?>
                            <p class="bb_axes">
                              <?php foreach ($myAxes as $ax): ?>
                              <span><?= sanitize($ax['name']) ?>: <b><?= sanitize($ax['value']) ?></b></span>
                              <?php endforeach; ?>
                            </p>
            <?php endif; ?>

            <?php if (count($cardOffers) > 1): ?>
                            <!-- Предложения продавцов (buy-box).
                                 Каждая строка — ссылка на страницу того предложения:
                                 так работает без JS, ссылки остаются рабочими и
                                 индексируются. Открытое сейчас предложение подсвечено. -->
                            <div class="bb_offers">
                              <div class="bb_offers_head">
                                <i class="fa fa-users"></i>
                                <?php $oc = count($cardOffers); ?>
                                Этот товар продают <?= $oc ?> <?= pluralRu($oc, 'продавец', 'продавца', 'продавцов') ?>
                                — <span>выберите, у кого купить</span>
                              </div>
                              <?php foreach ($cardOffers as $o):
                                  $isCur  = (int)$o['id'] === (int)$part['id'];
                                  $isBest = (int)$o['id'] === $bestOfferId;
                                  $oStock = (int)$o['stock'];
                              ?>
                              <a class="bb_offer<?= $isCur ? ' bb_offer--current' : '' ?>"
                                 href="<?= $isCur ? 'javascript:void(0)' : sanitize(partUrl($o)) ?>">
                                <span class="bb_offer_seller">
                                  <?php if (!empty($o['shop_name'])): ?>
                                    <i class="fa fa-briefcase"></i> <?= sanitize($o['shop_name']) ?>
                                  <?php else: ?>
                                    <i class="fa fa-home"></i> Наш магазин
                                  <?php endif; ?>
                                  <?php if ($isBest): ?><em class="bb_best">лучшее предложение</em><?php endif; ?>
                                  <?php if (!empty($o['seller_id'])): ?>
                                  <span class="bb_offer_rating">
                                    <?= sellerRatingHtml($offerRatings[(int)$o['seller_id']] ?? null) ?>
                                  </span>
                                  <?php endif; ?>
                                </span>
                                <span class="bb_offer_stock <?= $oStock > 0 ? 'ok' : 'no' ?>">
                                  <?= $oStock > 0 ? 'в наличии' : 'под заказ' ?>
                                </span>
                                <span class="bb_offer_price"><?= formatPrice((float)$o['price']) ?></span>
                                <span class="bb_offer_pick"><?= $isCur ? 'вы смотрите' : 'выбрать' ?></span>
                              </a>
                              <?php endforeach; ?>
                            </div>
            <?php endif; ?>

                            <div class="product_desc" style="margin-bottom:16px;">
                                <?php if ($part['description']): ?>
                                <p><?= nl2br(sanitize(truncate($part['description'], 300))) ?></p>
                                <?php endif; ?>
                            </div>

            <?php if ($mySpecs): ?>
                            <table class="bb_specs">
                              <?php foreach ($mySpecs as $sp): ?>
                              <tr>
                                <td><?= sanitize($sp['name']) ?></td>
                                <td><?= sanitize($sp['value']) ?></td>
                              </tr>
                              <?php endforeach; ?>
                            </table>
            <?php endif; ?>

                            <!-- Availability -->
                            <div class="product_variant" style="margin-bottom:16px;">
                                <p class="text_available">
                                    <?= t('availability') ?>:
                                    <span class="<?= $stock['class'] === 'success' ? 'in_stock' : 'out_stock' ?>">
                                        <?= $stock['label'] ?>
                                    </span>
                                    <?php if ($part['stock'] > 0): ?>
                                    <small style="color:#888;margin-left:8px;">(<?= (int)$part['stock'] ?> <?= t('pcs') ?>)</small>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <!-- Quantity + Add to Cart -->
                            <?php if (isLoggedIn()): ?>
                                <?php if ($part['stock'] > 0): ?>
                                <div class="product_variant quantity">
                                    <label><?= t('quantity') ?></label>
                                    <input type="number" name="quantity" id="qty-field"
                                           value="1" min="1" max="<?= min((int)$part['stock'], 99) ?>">
                                    <button class="button" type="submit">
                                        <?= t('add_to_cart') ?>
                                    </button>
                                </div>
                                <?php else: ?>
                                <div class="product_variant" style="margin-bottom:16px;">
                                    <p style="color:#c62828;font-weight:600;"><?= t('out_of_stock') ?></p>
                                </div>
                                <?php endif; ?>

                                <div class="product_d_action">
                                    <ul>
                                        <li>
                                            <a href="javascript:void(0)"
                                               onclick="addToWishlist(<?= (int)$part['id'] ?>)"
                                               title="<?= t('add_to_wishlist') ?>">
                                                + <?= t('add_to_wishlist') ?>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="product_variant" style="margin-bottom:16px;">
                                    <a href="<?= APP_URL ?>/auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                       class="button">
                                        <?= t('login') ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- SKU / Part number -->
                            <div class="product_meta">
                                <span><?= t('part_number') ?>: <a href="#"><?= sanitize($part['part_number']) ?></a></span>
                                <?php if ($part['brand_country']): ?>
                                &nbsp;&nbsp;
                                <span><?= t('country') ?? 'Country' ?>: <?= sanitize($part['brand_country']) ?></span>
                                <?php endif; ?>
                            </div>

                        </form>
                    </div>
                </div>
                <!-- /product info -->

            </div>
        </div>
        <!--product details end-->

        <!--product info start-->
        <div class="product_d_info">
            <div class="row">
                <div class="col-12">
                    <div class="product_d_inner">
                        <div class="product_info_button">
                            <ul class="nav" role="tablist" id="nav-tab">
                                <li>
                                    <a class="active" data-bs-toggle="tab" href="#info" role="tab"
                                       aria-controls="info" aria-selected="true">
                                        <?= t('description') ?>
                                    </a>
                                </li>
                                <li>
                                    <a data-bs-toggle="tab" href="#sheet" role="tab"
                                       aria-controls="sheet" aria-selected="false">
                                        <?= t('specifications') ?>
                                    </a>
                                </li>
                                <li>
                                    <a data-bs-toggle="tab" href="#reviews" role="tab"
                                       aria-controls="reviews" aria-selected="false">
                                        <?= t('reviews') ?? 'Reviews' ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-content">
                            <!-- Description tab -->
                            <div class="tab-pane fade show active" id="info" role="tabpanel">
                                <div class="product_info_content">
                                    <?php if ($part['description']): ?>
                                    <p><?= nl2br(sanitize($part['description'])) ?></p>
                                    <?php else: ?>
                                    <p><em><?= t('no_description') ?></em></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Specifications tab -->
                            <div class="tab-pane fade" id="sheet" role="tabpanel">
                                <div class="product_d_table">
                                    <form action="#">
                                        <table>
                                            <tbody>
                                                <tr>
                                                    <td class="first_child"><?= t('part_number') ?></td>
                                                    <td><?= sanitize($part['part_number']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="first_child"><?= t('brand') ?></td>
                                                    <td><?= sanitize($part['brand_name']) ?></td>
                                                </tr>
                                                <?php if ($part['brand_country']): ?>
                                                <tr>
                                                    <td class="first_child"><?= t('country') ?? 'Country' ?></td>
                                                    <td><?= sanitize($part['brand_country']) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="first_child"><?= t('category') ?></td>
                                                    <td><?= sanitize($part['category_name']) ?></td>
                                                </tr>
                                                <?php if (!empty($part['weight'])): ?>
                                                <tr>
                                                    <td class="first_child"><?= t('weight') ?></td>
                                                    <td><?= sanitize($part['weight']) ?> <?= t('unit_kg') ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($part['dimensions'])): ?>
                                                <tr>
                                                    <td class="first_child"><?= t('dimensions') ?></td>
                                                    <td><?= sanitize($part['dimensions']) ?> <?= t('unit_mm') ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="first_child"><?= t('in_stock') ?></td>
                                                    <td><?= $stock['label'] ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </form>
                                </div>
                            </div>

                            <!-- Reviews tab -->
                            <div class="tab-pane fade" id="reviews" role="tabpanel">
                                <div class="reviews_wrapper">
                                    <div class="comment_title">
                                        <h2><?= t('reviews') ?> (<?= $reviewCount ?>)</h2>
                                        <?php if ($reviewCount): ?>
                                        <p style="font-size:1.05rem;">
                                            <?= starsHtml($avgRating) ?>
                                            <strong style="margin-left:6px;"><?= $avgRating ?></strong>
                                            <span style="color:#888;font-size:0.9rem;">— <?= t('based_on_reviews', ['n' => $reviewCount]) ?></span>
                                        </p>
                                        <?php else: ?>
                                        <p><?= t('no_reviews') ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($reviewCount): ?>
                                    <div class="reviews_list" style="margin:24px 0;">
                                        <?php foreach ($reviews as $rv): ?>
                                        <div style="border:1px solid #eee;border-radius:8px;padding:16px 18px;margin-bottom:14px;background:#fff;">
                                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                                                <strong style="font-size:0.95rem;"><?= sanitize($rv['username']) ?></strong>
                                                <span style="font-size:0.8rem;color:#999;"><?= date('d.m.Y', strtotime($rv['created_at'])) ?></span>
                                            </div>
                                            <div style="margin:6px 0 8px;font-size:0.95rem;"><?= starsHtml((float)$rv['rating']) ?></div>
                                            <p style="margin:0;color:#555;line-height:1.6;"><?= nl2br(sanitize($rv['comment'])) ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Submit / edit review -->
                                    <div class="review_form_wrap" style="margin-top:30px;padding-top:24px;border-top:2px solid #f0f0f0;">
                                        <h3 style="font-size:1.1rem;margin-bottom:14px;"><?= t('write_review') ?></h3>

                                        <?php if (!isLoggedIn()): ?>
                                            <p style="color:#777;">
                                                <a href="<?= APP_URL ?>/auth/login.php?redirect=<?= urlencode('/catalog/part.php?id=' . $id) ?>"
                                                   style="color:#d32f2f;font-weight:600;"><?= t('login_to_review') ?></a>
                                            </p>
                                        <?php elseif (!$canReview && !$myReview): ?>
                                            <div class="az-alert" style="background:#f5f5f5;border:1px solid #e0e0e0;color:#666;padding:12px 16px;border-radius:6px;font-size:0.88rem;">
                                                <i class="fa fa-info-circle"></i> <?= sanitize(getSetting('review_msg_purchase_only', t('review_purchase_only'))) ?>
                                            </div>
                                        <?php elseif ($myReview && $myReview['status'] === 'pending'): ?>
                                            <div style="text-align:center;padding:24px 10px;">
                                                <div style="font-size:2.2rem;margin-bottom:10px;">✅</div>
                                                <p style="font-size:1rem;font-weight:600;margin-bottom:6px;color:#333;"><?= sanitize(getSetting('review_msg_submitted', t('review_submitted'))) ?></p>
                                                <p style="font-size:0.85rem;color:#888;line-height:1.6;"><?= sanitize(getSetting('review_msg_pending', t('review_pending'))) ?></p>
                                            </div>
                                        <?php else: ?>
                                            <?php if ($myReview && $myReview['status'] === 'rejected'): ?>
                                                <div class="az-alert az-alert-danger" style="background:#ffebee;border:1px solid #ffcdd2;color:#c62828;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:0.88rem;">
                                                    <i class="fa fa-times-circle"></i> <?= t('review_rejected') ?>
                                                </div>
                                            <?php elseif ($myReview && $myReview['status'] === 'approved'): ?>
                                                <p style="color:#888;font-size:0.86rem;margin-bottom:14px;"><?= t('review_edit_hint') ?></p>
                                            <?php endif; ?>

                                            <form method="POST" action="<?= APP_URL ?>/api/review_submit.php" id="reviewForm">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                                <input type="hidden" name="part_id" value="<?= $id ?>">
                                                <input type="hidden" name="rating" id="ratingInput" value="<?= $myReview ? (int)$myReview['rating'] : 5 ?>">

                                                <div style="margin-bottom:14px;">
                                                    <label style="display:block;font-size:0.88rem;font-weight:600;margin-bottom:6px;"><?= t('your_rating') ?></label>
                                                    <div id="starPicker" style="font-size:1.6rem;cursor:pointer;display:inline-flex;gap:4px;">
                                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                                        <i class="fa fa-star" data-val="<?= $s ?>" style="color:#ccc;transition:color .12s;"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>

                                                <div style="margin-bottom:14px;">
                                                    <label style="display:block;font-size:0.88rem;font-weight:600;margin-bottom:6px;"><?= t('your_review') ?></label>
                                                    <textarea name="comment" rows="4" required minlength="10" maxlength="2000"
                                                              style="width:100%;border:1px solid #ddd;border-radius:6px;padding:10px 12px;font-size:0.92rem;resize:vertical;"
                                                              placeholder="..."><?= $myReview ? sanitize($myReview['comment']) : '' ?></textarea>
                                                </div>

                                                <button type="submit" class="btn btn-md btn-black-default-hover"
                                                        style="background:#d32f2f;color:#fff;border:none;padding:10px 26px;border-radius:6px;font-weight:600;cursor:pointer;">
                                                    <?= t('submit_review') ?>
                                                </button>
                                            </form>

                                            <script>
                                            (function () {
                                                var picker = document.getElementById('starPicker');
                                                var input  = document.getElementById('ratingInput');
                                                if (!picker || !input) return;
                                                var stars = picker.querySelectorAll('i');
                                                function paint(v) {
                                                    stars.forEach(function (st) {
                                                        st.style.color = (parseInt(st.dataset.val, 10) <= v) ? '#f5a623' : '#ccc';
                                                    });
                                                }
                                                paint(parseInt(input.value, 10) || 5);
                                                stars.forEach(function (st) {
                                                    st.addEventListener('mouseenter', function () { paint(parseInt(st.dataset.val, 10)); });
                                                    st.addEventListener('click', function () {
                                                        input.value = st.dataset.val;
                                                        paint(parseInt(st.dataset.val, 10));
                                                    });
                                                });
                                                picker.addEventListener('mouseleave', function () {
                                                    paint(parseInt(input.value, 10) || 5);
                                                });
                                            })();
                                            </script>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--product info end-->

        <!--related products start-->
        <?php if (!empty($related)): ?>
        <section class="product_area related_products">
            <div class="row">
                <div class="col-12">
                    <div class="section_title title_style2">
                        <div class="title_content">
                            <h2><span><?= t('related') ?? 'Related' ?></span></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="product_carousel product_details_column5 owl-carousel">
                    <?php foreach ($related as $rel):
                        $relStock = getStockStatus((int)$rel['stock']);
                        $relImg   = productImageUrl($rel['images']);
                    ?>
                    <div class="col-lg-3">
                        <article class="single_product">
                            <figure>
                                <div class="product_thumb">
                                    <a class="primary_img" href="<?= partUrl($rel) ?>">
                                        <img src="<?= sanitize($relImg) ?>" alt="<?= sanitize($rel['name']) ?>">
                                    </a>
                                    <a class="secondary_img" href="<?= partUrl($rel) ?>">
                                        <img src="<?= sanitize($relImg) ?>" alt="<?= sanitize($rel['name']) ?>">
                                    </a>
                                    <?= productBadges($rel) ?>
                                    <div class="quick_button">
                                        <a href="<?= partUrl($rel) ?>" title="<?= t('quick_view') ?>">
                                            <i class="icon-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="product_content">
                                    <div class="product_content_inner">
                                        <p class="manufacture_product">
                                            <a href="<?= APP_URL ?>/catalog/index.php?brand=<?= (int)$rel['brand_id'] ?>">
                                                <?= sanitize($rel['brand_name']) ?>
                                            </a>
                                        </p>
                                        <h4 class="product_name">
                                            <a href="<?= partUrl($rel) ?>">
                                                <?= sanitize(truncate($rel['name'], 55)) ?>
                                            </a>
                                        </h4>
                                        <?= priceBox($rel) ?>
                                    </div>
                                    <div class="action_links">
                                        <ul>
                                            <?php if (isLoggedIn()): ?>
                                            <li class="add_to_cart">
                                                <a href="javascript:void(0)" onclick="addToCart(<?= (int)$rel['id'] ?>)" title="<?= t('add_to_cart') ?>">
                                                    <?= t('add_to_cart') ?>
                                                </a>
                                            </li>
                                            <li class="wishlist">
                                                <a href="javascript:void(0)" onclick="addToWishlist(<?= (int)$rel['id'] ?>)" title="<?= t('add_to_wishlist') ?>">
                                                    <i class="icon-heart"></i>
                                                </a>
                                            </li>
                                            <?php else: ?>
                                            <li class="add_to_cart">
                                                <a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </figure>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        <!--related products end-->

    </div><!-- /.container -->
</div><!-- /.product_page_bg -->
<!--product details area end-->

<script>
// Add to cart via form interception (for qty sync)
var cartForm = document.getElementById('add-cart-form');
if (cartForm) {
    cartForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var qty = document.getElementById('qty-field') ? parseInt(document.getElementById('qty-field').value) : 1;
        addToCart(<?= (int)$part['id'] ?>, qty);
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
