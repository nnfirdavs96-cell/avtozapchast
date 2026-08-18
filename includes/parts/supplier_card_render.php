<?php
/**
 * Общий рендер одной карточки поставщика AutoEuro. Используют и страница поиска
 * (includes/parts/supplier_cards.php), и AJAX-дозагрузка (api/supplier_search.php)
 * — чтобы разметка карточек совпадала.
 */

if (!function_exists('supplierHybridImage')) {
    /** Фото-гибрид: наше фото, если такой артикул есть в каталоге, иначе заглушка. */
    function supplierHybridImage(PDO $db, string $code): string
    {
        static $stmt = null;
        if ($stmt === null) {
            $stmt = $db->prepare(
                "SELECT images FROM parts
                 WHERE is_active=1
                   AND UPPER(REPLACE(REPLACE(REPLACE(part_number,' ',''),'-',''),'.','')) = ?
                 LIMIT 1"
            );
        }
        $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        if ($norm !== '') {
            $stmt->execute([$norm]);
            $row = $stmt->fetch();
            if ($row && !empty($row['images'])) return productImageUrl($row['images']);
        }
        return APP_URL . '/assets/img/product/placeholder.jpg';
    }
}

if (!function_exists('supplierCardHtml')) {
    /**
     * HTML одной карточки. $c: brand, code, name, image, lazy(bool), best(opt|null), options[].
     * lazy=true — цена подгружается на клиенте (api/vin_price.php); lazy=false — цена готова.
     */
    function supplierCardHtml(array $c): string
    {
        $lazy     = !empty($c['lazy']);
        $best     = $c['best'] ?? null;
        $optsJson = htmlspecialchars(json_encode($c['options'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $title    = ($c['name'] ?? '') !== '' ? $c['name'] : ($c['code'] ?? '');
        $fmtD     = static fn($d) => preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$d, $m) ? "$m[3].$m[2].$m[1]" : '';
        $eta      = $best ? $fmtD($best['delivery_total'] ?? '') : '';
        ob_start();
        ?>
    <div class="col-lg-3 col-md-4 col-6 mb-4">
      <article class="single_product sup-card<?php echo $lazy ? ' sup-lazy' : ''; ?>">
        <figure>
          <div class="product_thumb sup-thumb">
            <img src="<?php echo sanitize($c['image']); ?>" alt="<?php echo sanitize($title); ?>" loading="lazy">
            <?php if (!$lazy): ?>
            <span class="sup-badge <?php echo $best['in_stock'] ? 'in' : 'pre'; ?>">
              <?php echo $best['in_stock'] ? 'В наличии' : 'Под заказ'; ?>
            </span>
            <?php endif; ?>
          </div>
          <div class="product_content grid_content">
            <div class="product_content_inner">
              <p class="manufacture_product"><?php echo sanitize($c['brand']); ?></p>
              <h4 class="product_name"><?php echo sanitize(truncate($title, 55)); ?></h4>
              <p class="sup-art"><?php echo sanitize($c['code']); ?></p>
              <?php if ($lazy): ?>
              <div class="sup-price sup-price-load">цена <span class="sup-dots">…</span></div>
              <?php else: ?>
              <div class="sup-price">от <strong><?php echo $best['price']; ?></strong></div>
              <?php if ($eta !== ''): ?>
              <p class="sup-eta"><i class="fa fa-truck"></i> в Худжанде ≈ <?php echo $eta; ?></p>
              <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="action_links">
              <button type="button" class="sup-buy-btn"<?php echo $lazy ? ' data-lazy="1" disabled' : ''; ?>
                      data-oem="<?php echo sanitize($c['code']); ?>"
                      data-brand="<?php echo sanitize($c['brand']); ?>"
                      data-name="<?php echo sanitize($c['name']); ?>"
                      data-offers="<?php echo $optsJson; ?>">
                <i class="fa fa-shopping-cart"></i> <?php echo $lazy ? 'Загрузка…' : 'Купить'; ?>
              </button>
            </div>
          </div>
        </figure>
      </article>
    </div>
        <?php
        return ob_get_clean();
    }
}
