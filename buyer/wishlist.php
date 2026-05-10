<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['buyer','admin','manager','superadmin']);
$pageTitle = t('wishlist') . ' — ' . getSetting('site_name');

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$stmt = $db->prepare("SELECT w.part_id, p.name, p.price, p.images, p.part_number, p.stock, b.name AS brand_name
    FROM wishlist w JOIN parts p ON p.id=w.part_id LEFT JOIN brands b ON b.id=p.brand_id
    WHERE w.user_id=? ORDER BY w.added_at DESC");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();
require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?php echo generateCsrfToken(); ?>">
<?php echo breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('wishlist')]]); ?>
<div class="wishlist_area" style="padding:60px 0">
  <div class="container">
    <div class="section_title"><h2><?php echo t('wishlist'); ?></h2></div>
    <?php if (empty($items)): ?>
    <div style="text-align:center;padding:60px"><p style="color:#aaa;margin-bottom:20px"><?php echo t('cart_empty'); ?></p><a href="<?php echo APP_URL; ?>/catalog/index.php" class="button"><?php echo t('continue_shopping'); ?></a></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.06)">
        <thead style="background:#f5f5f5"><tr>
          <th style="padding:14px 20px">Фото</th>
          <th style="padding:14px 20px">Товар</th>
          <th style="padding:14px 20px"><?php echo t('price'); ?></th>
          <th style="padding:14px 20px"><?php echo t('status'); ?></th>
          <th style="padding:14px 20px"><?php echo t('actions'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($items as $item): $stock=getStockStatus((int)$item['stock']); $img=productImageUrl($item['images']); ?>
        <tr>
          <td style="padding:14px 20px"><img src="<?php echo $img; ?>" style="width:60px;height:60px;object-fit:contain;border:1px solid #eee;border-radius:4px" alt=""></td>
          <td style="padding:14px 20px">
            <a href="<?php echo APP_URL; ?>/catalog/part.php?id=<?php echo (int)$item['part_id']; ?>" style="font-weight:600;color:#222;text-decoration:none"><?php echo sanitize(truncate($item['name'],55)); ?></a>
            <p style="color:#aaa;font-size:0.75rem;margin:2px 0"><?php echo sanitize($item['brand_name']); ?> &bull; <?php echo sanitize($item['part_number']); ?></p>
          </td>
          <td style="padding:14px 20px;font-weight:700;color:#d32f2f"><?php echo formatPrice($item['price']); ?></td>
          <td style="padding:14px 20px"><span class="stock-<?php echo $stock['class']; ?>"><?php echo $stock['label']; ?></span></td>
          <td style="padding:14px 20px">
            <button onclick="addToCart(<?php echo (int)$item['part_id']; ?>)" class="button" style="padding:6px 14px;font-size:0.8rem;margin-right:6px"><?php echo t('add_to_cart'); ?></button>
            <a href="<?php echo APP_URL; ?>/api/wishlist.php?action=remove&part_id=<?php echo (int)$item['part_id']; ?>&_csrf=<?php echo generateCsrfToken(); ?>" class="button button_2" style="padding:6px 14px;font-size:0.8rem"><?php echo t('remove'); ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:20px"><a href="<?php echo APP_URL; ?>/catalog/index.php" class="button button_2">&larr; <?php echo t('continue_shopping'); ?></a></div>
    <?php endif; ?>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
