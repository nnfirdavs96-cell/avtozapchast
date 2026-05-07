<?php
/**
 * Render a single product card.
 * Expects $p with keys: id, part_number, name, price, stock, brand_name (optional).
 */
function renderProductCard(array $p): void {
    $stock  = getStockStatus((int)$p['stock']);
    $rating = getPartRating((int)$p['id']);
    $image  = getPartImage((int)$p['id']);
    $name   = tField('part', (int)$p['id'], 'name', $p['name']);
    $brand  = $p['brand_name'] ?? '';
    ?>
    <div class="product-card fade-up">
      <div class="pc-thumb">
        <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$p['id'] ?>">
          <img src="<?= sanitize($image) ?>" alt="<?= sanitize($name) ?>" loading="lazy">
        </a>
        <div class="pc-badges">
          <?php if ((int)$p['stock'] > 0): ?>
            <span class="badge badge-new"><?= t('in_stock') ?></span>
          <?php endif; ?>
        </div>
        <div class="pc-actions">
          <button type="button" data-wishlist="<?= (int)$p['id'] ?>" class="<?= isInWishlist((int)$p['id']) ? 'active' : '' ?>" title="<?= t('add_to_wishlist') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
          </button>
          <button type="button" data-compare="<?= (int)$p['id'] ?>" class="<?= isInCompare((int)$p['id']) ? 'active' : '' ?>" title="<?= t('add_to_compare') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
          </button>
          <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$p['id'] ?>" title="<?= t('description') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </a>
        </div>
      </div>
      <div class="pc-body">
        <div class="pc-brand"><?= sanitize($brand) ?> · <?= sanitize($p['part_number']) ?></div>
        <div class="pc-name"><a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$p['id'] ?>"><?= sanitize($name) ?></a></div>
        <?php if ($rating['count'] > 0): ?>
          <div class="pc-rating">
            <span class="stars"><?= ratingStars($rating['avg']) ?></span>
            <span class="count">(<?= $rating['count'] ?>)</span>
          </div>
        <?php endif; ?>
        <div class="pc-price-row">
          <span class="pc-price"><?= money($p['price']) ?></span>
          <span class="badge badge-<?= $stock['class'] ?>"><?= sanitize($stock['label']) ?></span>
        </div>
      </div>
      <div class="pc-foot">
        <?php if ((int)$p['stock'] > 0): ?>
          <button class="btn btn-primary btn-sm btn-block" data-add-cart="<?= (int)$p['id'] ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            <?= t('add_to_cart') ?>
          </button>
        <?php else: ?>
          <button class="btn btn-outline btn-sm btn-block" disabled><?= t('out_of_stock') ?></button>
        <?php endif; ?>
      </div>
    </div>
    <?php
}
