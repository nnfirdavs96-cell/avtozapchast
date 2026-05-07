<?php
require_once __DIR__ . '/../config/config.php';
requireRole(['buyer','manager','admin','superadmin']);

$db = getDB();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update' && !empty($_POST['quantities']) && is_array($_POST['quantities'])) {
        $upd = $db->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND part_id=?");
        foreach ($_POST['quantities'] as $partId => $qty) {
            $partId = (int)$partId; $qty = max(1, min(99, (int)$qty));
            if ($partId) $upd->execute([$qty, $userId, $partId]);
        }
        flashMessage('success', 'Корзина обновлена');
        redirect(APP_URL . '/buyer/cart.php');
    }
    if ($action === 'remove') {
        $partId = (int)($_POST['part_id'] ?? 0);
        $db->prepare("DELETE FROM cart WHERE user_id=? AND part_id=?")->execute([$userId, $partId]);
        flashMessage('success', 'Товар удалён');
        redirect(APP_URL . '/buyer/cart.php');
    }
    if ($action === 'clear') {
        $db->prepare("DELETE FROM cart WHERE user_id=?")->execute([$userId]);
        flashMessage('success', 'Корзина очищена');
        redirect(APP_URL . '/buyer/cart.php');
    }
}

$stmt = $db->prepare(
    "SELECT c.quantity, p.id, p.name, p.part_number, p.price, p.stock, b.name AS brand_name
     FROM cart c JOIN parts p ON p.id=c.part_id
     LEFT JOIN brands b ON b.id=p.brand_id
     WHERE c.user_id=? AND p.is_active=1
     ORDER BY c.added_at DESC"
);
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$subtotal = 0;
foreach ($items as $it) $subtotal += $it['price'] * $it['quantity'];

$pageTitle = t('cart');
require_once __DIR__ . '/../includes/header.php';
?>
<meta name="csrf" content="<?= sanitize($csrfToken) ?>">

<div class="page-head"><div class="container">
  <h1><?= t('cart') ?></h1>
  <nav class="breadcrumb">
    <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
    <span class="current"><?= t('cart') ?></span>
  </nav>
</div></div>

<section class="section">
  <div class="container">

    <?php if (empty($items)): ?>
      <div class="empty-state">
        <div class="icon"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg></div>
        <h3><?= t('cart_empty') ?></h3>
        <p><?= t('cart_empty_sub') ?></p>
        <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary"><?= t('continue_shopping') ?></a>
      </div>
    <?php else: ?>
      <div class="checkout-grid">
        <div>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
            <input type="hidden" name="action" value="update">
            <table class="cart-table">
              <thead>
                <tr><th>Товар</th><th><?= t('price') ?></th><th><?= t('quantity') ?></th><th><?= t('total') ?></th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): $rowSubtotal = $it['price'] * $it['quantity']; ?>
                  <tr>
                    <td>
                      <div class="cart-prod">
                        <img src="<?= sanitize(getPartImage((int)$it['id'])) ?>" alt="">
                        <div class="info">
                          <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$it['id'] ?>"><?= sanitize($it['name']) ?></a>
                          <div class="meta"><?= sanitize($it['brand_name']) ?> · <?= sanitize($it['part_number']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?= money($it['price']) ?></td>
                    <td>
                      <div class="qty-input">
                        <button type="button" data-qty-minus>−</button>
                        <input type="number" name="quantities[<?= (int)$it['id'] ?>]" value="<?= (int)$it['quantity'] ?>" min="1" max="<?= (int)$it['stock'] ?>">
                        <button type="button" data-qty-plus>+</button>
                      </div>
                    </td>
                    <td><strong><?= money($rowSubtotal) ?></strong></td>
                    <td>
                      <button type="button" class="btn btn-link" onclick="document.getElementById('rm-<?= (int)$it['id'] ?>').submit()">✕</button>
                      <form id="rm-<?= (int)$it['id'] ?>" method="post" style="display:none">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="part_id" value="<?= (int)$it['id'] ?>">
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="flex-between mt-16">
              <button type="submit" class="btn btn-outline"><?= t('update_cart') ?></button>
              <button type="button" class="btn btn-link" onclick="if(confirm('<?= t('clear_cart') ?>?')){document.getElementById('clear-cart').submit()}">
                <?= t('clear_cart') ?>
              </button>
              <form id="clear-cart" method="post" style="display:none">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                <input type="hidden" name="action" value="clear">
              </form>
            </div>
          </form>
        </div>

        <aside class="cart-summary">
          <h3><?= t('order_summary') ?></h3>
          <div class="summary-row"><span><?= t('subtotal') ?></span><span><?= money($subtotal) ?></span></div>
          <div class="summary-row"><span><?= t('shipping') ?></span><span class="text-muted">рассчитывается на следующем шаге</span></div>
          <div class="summary-row total"><span><?= t('total') ?></span><span class="val"><?= money($subtotal) ?></span></div>
          <a href="<?= APP_URL ?>/buyer/checkout.php" class="btn btn-primary btn-lg btn-block mt-16"><?= t('checkout') ?></a>
          <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-link btn-block mt-8"><?= t('continue_shopping') ?></a>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
