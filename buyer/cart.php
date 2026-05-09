<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('buyer');

$user = getCurrentUser();
$db   = getDB();
$csrf = generateCsrfToken();

// Handle checkout form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности.');
        redirect(APP_URL . '/buyer/cart.php');
    }
    $address = trim($_POST['address'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    if (empty($address)) {
        flashMessage('danger', 'Укажите адрес доставки.');
        redirect(APP_URL . '/buyer/cart.php');
    }
    // Get cart items
    $cartStmt = $db->prepare(
        "SELECT c.*, p.price, p.stock, p.name AS part_name
         FROM cart c JOIN parts p ON p.id = c.part_id
         WHERE c.user_id = ? AND p.is_active = 1"
    );
    $cartStmt->execute([$user['id']]);
    $cartItems = $cartStmt->fetchAll();
    if (empty($cartItems)) {
        flashMessage('warning', 'Ваша корзина пуста.');
        redirect(APP_URL . '/buyer/cart.php');
    }
    $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));
    $db->beginTransaction();
    try {
        $ordStmt = $db->prepare(
            "INSERT INTO orders (user_id, total_amount, shipping_address, notes) VALUES (?, ?, ?, ?)"
        );
        $ordStmt->execute([$user['id'], $total, $address, $notes ?: null]);
        $orderId = (int)$db->lastInsertId();
        $itmStmt = $db->prepare(
            "INSERT INTO order_items (order_id, part_id, quantity, unit_price) VALUES (?, ?, ?, ?)"
        );
        foreach ($cartItems as $item) {
            $itmStmt->execute([$orderId, $item['part_id'], $item['quantity'], $item['price']]);
        }
        // Clear cart
        $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user['id']]);
        $db->commit();
        flashMessage('success', "Заказ #$orderId успешно оформлен! Мы свяжемся с вами для подтверждения.");
        redirect(APP_URL . '/buyer/orders.php?id=' . $orderId);
    } catch (Exception $e) {
        $db->rollBack();
        flashMessage('danger', 'Ошибка оформления заказа. Попробуйте снова.');
        redirect(APP_URL . '/buyer/cart.php');
    }
}

// Load cart
$cartStmt = $db->prepare(
    "SELECT c.id AS cart_id, c.part_id, c.quantity, p.name, p.part_number, p.price, p.stock,
            b.name AS brand_name
     FROM cart c
     JOIN parts p ON p.id = c.part_id
     LEFT JOIN brands b ON b.id = p.brand_id
     WHERE c.user_id = ? AND p.is_active = 1
     ORDER BY c.added_at DESC"
);
$cartStmt->execute([$user['id']]);
$cartItems = $cartStmt->fetchAll();
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));

$pageTitle = 'Корзина';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<style>
.cart-grid {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 24px;
}
@media (max-width: 900px) { .cart-grid { grid-template-columns: 1fr; } }
.qty-control {
  display: flex;
  align-items: center;
  gap: 4px;
}
.qty-btn {
  width: 28px; height: 28px;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s;
}
.qty-btn:hover { border-color: var(--accent); color: var(--accent); }
.qty-num {
  width: 36px;
  text-align: center;
  font-family: var(--font-mono);
  font-size: 0.875rem;
  background: transparent;
  border: none;
  color: var(--text-primary);
}
.order-summary {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px;
  height: fit-content;
  position: sticky;
  top: 80px;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
  font-size: 0.875rem;
  color: var(--text-secondary);
}
.summary-row:last-of-type { border-bottom: none; }
.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-top: 2px solid var(--accent);
  margin-top: 8px;
}
.summary-total-label {
  font-family: var(--font-mono);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--text-muted);
}
.summary-total-val {
  font-family: var(--font-display);
  font-size: 1.8rem;
  color: var(--accent);
  letter-spacing: 2px;
}
</style>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">КОРЗИНА</div>

    <?php if (empty($cartItems)): ?>
    <div class="no-data" style="padding:80px 20px;">
      <div class="no-data-icon">🛒</div>
      <p>Ваша корзина пуста.</p>
      <a href="<?= APP_URL ?>/catalog/index.php" class="btn btn-primary" style="margin-top:16px;">Перейти в каталог</a>
    </div>
    <?php else: ?>
    <div class="cart-grid">
      <!-- Cart items -->
      <div>
        <div class="card">
          <div class="card-header">
            <h3>ТОВАРЫ (<?= count($cartItems) ?>)</h3>
          </div>
          <div class="table-wrap" style="border:none;border-radius:0;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Товар</th>
                  <th style="text-align:center;">Кол-во</th>
                  <th style="text-align:right;">Цена</th>
                  <th style="text-align:right;">Сумма</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cartItems as $item): ?>
                <tr data-cart-row="<?= (int)$item['part_id'] ?>">
                  <td>
                    <div>
                      <div style="font-family:var(--font-mono);font-size:0.65rem;color:var(--accent);margin-bottom:2px;"><?= sanitize($item['part_number']) ?></div>
                      <a href="<?= APP_URL ?>/catalog/part.php?id=<?= $item['part_id'] ?>" style="color:var(--text-primary);text-decoration:none;font-size:0.875rem;">
                        <?= sanitize(truncate($item['name'], 50)) ?>
                      </a>
                      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;"><?= sanitize($item['brand_name']) ?></div>
                    </div>
                  </td>
                  <td style="text-align:center;">
                    <div class="qty-control" style="justify-content:center;">
                      <button class="qty-btn" data-qty-minus title="Уменьшить">−</button>
                      <input type="number" class="qty-num" data-qty-input value="<?= (int)$item['quantity'] ?>" min="1" max="99" readonly>
                      <button class="qty-btn" data-qty-plus title="Увеличить">+</button>
                    </div>
                  </td>
                  <td style="text-align:right;font-family:var(--font-mono);font-size:0.875rem;color:var(--text-secondary);"><?= formatPrice($item['price']) ?></td>
                  <td style="text-align:right;font-family:var(--font-mono);color:var(--accent);" data-row-subtotal>
                    <?= formatPrice($item['price'] * $item['quantity']) ?>
                  </td>
                  <td>
                    <button class="btn btn-danger btn-sm" data-cart-remove="<?= (int)$item['part_id'] ?>" title="Удалить">✕</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Order summary + checkout form -->
      <div>
        <div class="order-summary">
          <div class="label-mono mb-16">// Итого</div>
          <?php foreach ($cartItems as $item): ?>
          <div class="summary-row">
            <span style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= sanitize(truncate($item['name'], 28)) ?>
            </span>
            <span style="font-family:var(--font-mono);font-size:0.78rem;"><?= $item['quantity'] ?> × <?= formatPrice($item['price']) ?></span>
          </div>
          <?php endforeach; ?>
          <div class="summary-total">
            <span class="summary-total-label">Итого</span>
            <span class="summary-total-val" id="cart-total"><?= formatPrice($cartTotal) ?></span>
          </div>

          <!-- Checkout form -->
          <form method="post" action="" style="margin-top:20px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="checkout" value="1">
            <div class="form-group">
              <label class="form-label" for="address">Адрес доставки *</label>
              <textarea id="address" name="address" class="form-textarea" rows="3"
                        placeholder="г. Москва, ул. Пример, д. 1, кв. 10" required></textarea>
            </div>
            <div class="form-group">
              <label class="form-label" for="notes">Примечания</label>
              <textarea id="notes" name="notes" class="form-textarea" rows="2"
                        placeholder="Удобное время доставки, особые требования..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">
              ОФОРМИТЬ ЗАКАЗ
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
