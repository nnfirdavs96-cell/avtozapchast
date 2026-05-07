<?php
require_once __DIR__ . '/../config/config.php';
requireRole(['buyer','manager','admin','superadmin']);

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$user = getCurrentUser();

$itemsStmt = $db->prepare(
    "SELECT c.quantity, p.id, p.name, p.part_number, p.price, p.stock, b.name AS brand_name
     FROM cart c JOIN parts p ON p.id=c.part_id
     LEFT JOIN brands b ON b.id=p.brand_id
     WHERE c.user_id=? AND p.is_active=1"
);
$itemsStmt->execute([$userId]);
$items = $itemsStmt->fetchAll();

if (empty($items)) {
    flashMessage('warning', 'Корзина пуста. Добавьте товары перед оформлением заказа.');
    redirect(APP_URL . '/buyer/cart.php');
}
$subtotal = 0;
foreach ($items as $it) $subtotal += $it['price'] * $it['quantity'];

$delivery = $db->query("SELECT * FROM delivery_methods WHERE is_active=1 ORDER BY sort_order")->fetchAll();
$payment  = $db->query("SELECT * FROM payment_methods WHERE is_active=1 ORDER BY sort_order")->fetchAll();

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = 'Неверный CSRF-токен';
    } else {
        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        $paymentId  = (int)($_POST['payment_id'] ?? 0);
        $email      = trim((string)($_POST['email'] ?? ''));
        $recipient  = trim((string)($_POST['recipient'] ?? ''));
        $phone      = trim((string)($_POST['phone'] ?? ''));
        $country    = trim((string)($_POST['country'] ?? 'Россия'));
        $city       = trim((string)($_POST['city'] ?? ''));
        $street     = trim((string)($_POST['street'] ?? ''));
        $building   = trim((string)($_POST['building'] ?? ''));
        $apartment  = trim((string)($_POST['apartment'] ?? ''));
        $postal     = trim((string)($_POST['postal_code'] ?? ''));
        $notes      = trim((string)($_POST['notes'] ?? ''));

        if (!$deliveryId || !$paymentId) $err = 'Выберите способ доставки и оплаты';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Неверный e-mail';
        elseif (!$recipient || !$phone || !$city || !$street) $err = 'Заполните адрес доставки';
        else {
            $delMeth = array_values(array_filter($delivery, fn($d)=>$d['id']==$deliveryId))[0] ?? null;
            $payMeth = array_values(array_filter($payment, fn($p)=>$p['id']==$paymentId))[0] ?? null;
            if (!$delMeth || !$payMeth) $err = 'Неверный способ доставки или оплаты';
            else {
                $deliveryCost = (float)$delMeth['cost'];
                $totalAmount  = $subtotal + $deliveryCost;
                $shippingAddress = trim(sprintf(
                    "%s, %s, %s, д. %s%s%s",
                    $country, $city, $street, $building,
                    $apartment ? ", кв. {$apartment}" : '',
                    $postal ? ", {$postal}" : ''
                ));

                $db->beginTransaction();
                try {
                    // Create address
                    $db->prepare(
                        "INSERT INTO addresses (user_id, recipient, phone, country, city, street, building, apartment, postal_code, notes)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    )->execute([$userId, $recipient, $phone, $country, $city, $street, $building ?: null, $apartment ?: null, $postal ?: null, $notes ?: null]);
                    $addressId = (int)$db->lastInsertId();

                    // Create order
                    $cur = currentCurrency();
                    $db->prepare(
                        "INSERT INTO orders
                         (user_id, address_id, status, delivery_method_id, payment_method_id, payment_status,
                          delivery_cost, subtotal, total_amount, shipping_address, notes, currency_code, email)
                         VALUES (?,?, 'pending', ?,?, 'unpaid', ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $userId, $addressId, $deliveryId, $paymentId,
                        $deliveryCost, $subtotal, $totalAmount,
                        $shippingAddress, $notes ?: null, $cur['code'], $email,
                    ]);
                    $orderId = (int)$db->lastInsertId();

                    $itIns = $db->prepare("INSERT INTO order_items (order_id, part_id, quantity, unit_price) VALUES (?,?,?,?)");
                    $stockUpd = $db->prepare("UPDATE parts SET stock = GREATEST(0, stock-?) WHERE id=?");
                    foreach ($items as $it) {
                        $itIns->execute([$orderId, $it['id'], $it['quantity'], $it['price']]);
                        $stockUpd->execute([$it['quantity'], $it['id']]);
                    }
                    $db->prepare("DELETE FROM cart WHERE user_id=?")->execute([$userId]);
                    $db->commit();

                    // Email confirmation to customer
                    $itemsHtml = '<table style="width:100%;border-collapse:collapse;font-size:14px"><tbody>';
                    foreach ($items as $it) {
                        $itemsHtml .= "<tr><td style='padding:8px 0;border-bottom:1px solid #eee'><strong>{$it['name']}</strong><br><span style='color:#888;font-size:12px'>{$it['part_number']} · {$it['quantity']} × " . money($it['price']) . "</span></td><td style='padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:600'>" . money($it['price'] * $it['quantity']) . "</td></tr>";
                    }
                    $itemsHtml .= "<tr><td style='padding:10px 0;color:#888'>Доставка ({$delMeth['name']})</td><td style='padding:10px 0;text-align:right'>" . money($deliveryCost) . "</td></tr>";
                    $itemsHtml .= "<tr><td style='padding:14px 0 4px;font-size:18px;font-weight:700'>ИТОГО</td><td style='padding:14px 0 4px;text-align:right;color:#C70909;font-size:20px;font-weight:700'>" . money($totalAmount) . "</td></tr>";
                    $itemsHtml .= '</tbody></table>';

                    sendEmail($email, "Заказ №{$orderId} оформлен — АвтоЗапчасть",
                        emailLayout("Заказ №{$orderId} оформлен!",
                            "<p>Здравствуйте, {$recipient}!</p>"
                            . "<p>Спасибо за заказ. Мы свяжемся с вами в ближайшее время для подтверждения.</p>"
                            . "<h3 style='margin-top:24px'>Состав заказа:</h3>{$itemsHtml}"
                            . "<p style='margin-top:20px'><strong>Доставка:</strong> {$delMeth['name']} ({$delMeth['eta_days']})<br>"
                            . "<strong>Оплата:</strong> {$payMeth['name']}<br>"
                            . "<strong>Адрес:</strong> {$shippingAddress}</p>"));

                    $adminEmail = getSetting('order_email_admin', 'admin@avtozapchast.ru');
                    sendEmail($adminEmail, "Новый заказ №{$orderId} — {$totalAmount}",
                        emailLayout("Новый заказ №{$orderId}",
                            "<p>Получен новый заказ от <strong>{$recipient}</strong> ({$email}, {$phone}).</p>{$itemsHtml}"));

                    redirect(APP_URL . '/buyer/order_success.php?id=' . $orderId);
                } catch (Throwable $e) {
                    $db->rollBack();
                    $err = 'Ошибка оформления: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = t('checkout');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><div class="container">
  <h1><?= t('checkout') ?></h1>
  <nav class="breadcrumb">
    <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
    <a href="<?= APP_URL ?>/buyer/cart.php"><?= t('cart') ?></a><span class="sep">/</span>
    <span class="current"><?= t('checkout') ?></span>
  </nav>
</div></div>

<section class="section">
  <div class="container">
    <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>
    <form method="post" class="checkout-grid">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

      <div>
        <div class="checkout-card">
          <h3><span class="step-num">1</span> Контактная информация</h3>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label"><?= t('email') ?> *</label>
              <input type="email" name="email" class="form-input" value="<?= sanitize($user['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label"><?= t('phone') ?> *</label>
              <input type="tel" name="phone" class="form-input" value="<?= sanitize($user['phone'] ?? '') ?>" required>
            </div>
          </div>
        </div>

        <div class="checkout-card">
          <h3><span class="step-num">2</span> <?= t('shipping_address') ?></h3>
          <div class="form-group">
            <label class="form-label"><?= t('recipient') ?> *</label>
            <input type="text" name="recipient" class="form-input" value="<?= sanitize($user['username'] ?? '') ?>" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label"><?= t('country') ?> *</label>
              <select name="country" class="form-select">
                <option>Россия</option>
                <option>Таджикистан</option>
                <option>Казахстан</option>
                <option>Беларусь</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label"><?= t('city') ?> *</label>
              <input type="text" name="city" class="form-input" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('street') ?> *</label>
            <input type="text" name="street" class="form-input" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label"><?= t('building') ?></label>
              <input type="text" name="building" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label"><?= t('apartment') ?></label>
              <input type="text" name="apartment" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label"><?= t('postal_code') ?></label>
              <input type="text" name="postal_code" class="form-input">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('order_notes') ?></label>
            <textarea name="notes" class="form-textarea" rows="3"></textarea>
          </div>
        </div>

        <div class="checkout-card">
          <h3><span class="step-num">3</span> <?= t('shipping_method') ?></h3>
          <div class="option-list">
            <?php foreach ($delivery as $i => $d): ?>
              <label class="option-item">
                <input type="radio" name="delivery_id" value="<?= (int)$d['id'] ?>" <?= $i===0?'checked':'' ?> required>
                <div class="option-info">
                  <div class="name"><?= sanitize($d['name']) ?></div>
                  <div class="desc"><?= sanitize($d['description']) ?> · ⏱ <?= sanitize($d['eta_days']) ?></div>
                </div>
                <div class="option-price"><?= $d['cost']>0 ? money($d['cost']) : 'Бесплатно' ?></div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="checkout-card">
          <h3><span class="step-num">4</span> <?= t('payment_method') ?></h3>
          <div class="option-list">
            <?php foreach ($payment as $i => $p): ?>
              <label class="option-item">
                <input type="radio" name="payment_id" value="<?= (int)$p['id'] ?>" <?= $i===0?'checked':'' ?> required>
                <div class="option-info">
                  <div class="name"><?= sanitize($p['name']) ?></div>
                  <div class="desc"><?= sanitize($p['description']) ?></div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <aside>
        <div class="cart-summary" style="position:sticky;top:80px">
          <h3><?= t('order_summary') ?></h3>
          <div style="max-height:280px;overflow-y:auto;margin-bottom:14px">
            <?php foreach ($items as $it): ?>
              <div class="summary-row" style="border-bottom:1px solid var(--border-soft)">
                <span><?= sanitize($it['name']) ?> × <?= (int)$it['quantity'] ?></span>
                <span><?= money($it['price'] * $it['quantity']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="summary-row"><span><?= t('subtotal') ?></span><span><?= money($subtotal) ?></span></div>
          <div class="summary-row"><span><?= t('shipping') ?></span><span><?= money($delivery[0]['cost'] ?? 0) ?></span></div>
          <div class="summary-row total"><span><?= t('total') ?></span><span class="val"><?= money($subtotal + (float)($delivery[0]['cost'] ?? 0)) ?></span></div>
          <button type="submit" class="btn btn-primary btn-lg btn-block mt-16"><?= t('place_order') ?></button>
          <p class="text-muted mt-16" style="font-size:0.78rem">Нажимая "Подтвердить заказ", вы соглашаетесь с
            <a href="<?= APP_URL ?>/pages/terms.php">условиями</a> и
            <a href="<?= APP_URL ?>/pages/privacy.php">политикой конфиденциальности</a>.</p>
        </div>
      </aside>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
