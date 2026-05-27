<?php
require_once dirname(__DIR__) . '/config/config.php';

if (!isLoggedIn()) {
    redirect(APP_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = getCurrentUser();
$db   = getDB();
$csrf = generateCsrfToken();

// Load cart
$cartStmt = $db->prepare(
    "SELECT c.part_id, c.quantity, p.name, p.price, p.images, p.stock, p.part_number,
            b.name AS brand_name
     FROM cart c
     JOIN parts p ON p.id = c.part_id
     LEFT JOIN brands b ON b.id = p.brand_id
     WHERE c.user_id = ? AND p.is_active = 1
     ORDER BY c.added_at DESC"
);
$cartStmt->execute([$user['id']]);
$cartItems = $cartStmt->fetchAll();

if (empty($cartItems)) {
    flashMessage('warning', 'Ваша корзина пуста. Добавьте товары перед оформлением заказа.');
    redirect(APP_URL . '/buyer/cart.php');
}

$cartTotal = 0.0;
foreach ($cartItems as $item) {
    $cartTotal += (float)$item['price'] * (int)$item['quantity'];
}

// Active delivery cities. Empty if migration not applied yet → falls back to a
// free-text city field and free shipping (keeps checkout working regardless).
$deliveryZones  = [];
$zoneCostByCity = [];
try {
    $deliveryZones = $db->query(
        "SELECT city, cost, delivery_days FROM delivery_zones WHERE is_active = 1 ORDER BY sort_order, city"
    )->fetchAll();
    foreach ($deliveryZones as $z) { $zoneCostByCity[$z['city']] = (float)$z['cost']; }
} catch (Throwable $e) {
    $deliveryZones = [];
}

// Whether the orders table already has the shipping_cost column (migration applied).
$hasShippingCostCol = false;
try {
    $hasShippingCostCol = (bool)$db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_cost'"
    )->fetchColumn();
} catch (Throwable $e) {
    $hasShippingCostCol = false;
}

// Load user profile for prefilling
try {
    $profileStmt = $db->prepare("SELECT email, phone, first_name, last_name, address, city, zip_code, country FROM users WHERE id = ? LIMIT 1");
    $profileStmt->execute([$user['id']]);
    $profile = $profileStmt->fetch() ?: [];
} catch (PDOException $e) {
    // Profile address columns may not exist yet (migration not run)
    $profileStmt = $db->prepare("SELECT email, phone FROM users WHERE id = ? LIMIT 1");
    $profileStmt->execute([$user['id']]);
    $profile = $profileStmt->fetch() ?: [];
}

// Prefill from saved profile, fallback to session user data
$prefillEmail   = $profile['email'] ?? ($user['email'] ?? '');
$prefillPhone   = $profile['phone'] ?? ($user['phone'] ?? '');
$prefillFname   = $profile['first_name'] ?? '';
$prefillLname   = $profile['last_name'] ?? '';
$prefillAddr    = $profile['address'] ?? '';
$prefillCity    = $profile['city'] ?? '';
$prefillZip     = $profile['zip_code'] ?? '';
$prefillCountry = $profile['country'] ?? '';

$errors = [];

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности. Попробуйте снова.');
        redirect(APP_URL . '/buyer/checkout.php');
    }

    $firstName   = trim($_POST['first_name']   ?? '');
    $lastName    = trim($_POST['last_name']    ?? '');
    $email       = trim($_POST['email']        ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $city        = trim($_POST['city']         ?? '');
    $zipCode     = trim($_POST['zip_code']     ?? '');
    $country     = trim($_POST['country']      ?? '');
    $orderNotes  = trim($_POST['order_notes']  ?? '');
    $payMethod   = in_array($_POST['payment_method'] ?? '', ['bank_transfer', 'cash_on_delivery'])
                   ? $_POST['payment_method']
                   : 'cash_on_delivery';

    // Validate
    if (empty($firstName)) $errors[] = t('first_name') . ' — обязательное поле.';
    if (empty($lastName))  $errors[] = t('last_name')  . ' — обязательное поле.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Укажите корректный email.';
    if (empty($phone))   $errors[] = t('phone')   . ' — обязательное поле.';
    if (empty($address)) $errors[] = t('address') . ' — обязательное поле.';
    if (empty($city))    $errors[] = t('city')    . ' — обязательное поле.';

    // When delivery cities are configured AND Tajikistan selected, city must be from the list.
    if (!empty($deliveryZones) && $city !== '' && $country === 'Таджикистан' && !isset($zoneCostByCity[$city])) {
        $errors[] = 'Выберите город доставки из списка.';
    }

    // Delivery cost: only for Tajikistan zones; other countries → 0 (уточняется).
    $shippingCost = ($country === 'Таджикистан' && !empty($zoneCostByCity) && isset($zoneCostByCity[$city]))
        ? (float)$zoneCostByCity[$city]
        : 0.0;
    $grandTotal = $cartTotal + $shippingCost;

    if (empty($errors)) {
        // Build shipping address JSON
        $shippingData = json_encode([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'phone'      => $phone,
            'address'    => $address,
            'city'       => $city,
            'zip_code'   => $zipCode,
            'country'    => $country,
        ], JSON_UNESCAPED_UNICODE);

        $db->beginTransaction();
        try {
            // Insert order. total_amount includes delivery; shipping_cost stored
            // separately when the column exists (migration applied).
            if ($hasShippingCostCol) {
                $ordStmt = $db->prepare(
                    "INSERT INTO orders (user_id, status, total_amount, shipping_cost, shipping_address, notes, payment_method, created_at, updated_at)
                     VALUES (?, 'pending', ?, ?, ?, ?, ?, NOW(), NOW())"
                );
                $ordStmt->execute([
                    $user['id'],
                    $grandTotal,
                    $shippingCost,
                    $shippingData,
                    $orderNotes ?: null,
                    $payMethod,
                ]);
            } else {
                $ordStmt = $db->prepare(
                    "INSERT INTO orders (user_id, status, total_amount, shipping_address, notes, payment_method, created_at, updated_at)
                     VALUES (?, 'pending', ?, ?, ?, ?, NOW(), NOW())"
                );
                $ordStmt->execute([
                    $user['id'],
                    $grandTotal,
                    $shippingData,
                    $orderNotes ?: null,
                    $payMethod,
                ]);
            }
            $orderId = (int)$db->lastInsertId();

            // Insert order items
            $itmStmt = $db->prepare(
                "INSERT INTO order_items (order_id, part_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($cartItems as $item) {
                $itmStmt->execute([
                    $orderId,
                    $item['part_id'],
                    $item['quantity'],
                    $item['price'],
                ]);
            }

            // Clear cart
            $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user['id']]);

            $db->commit();

            flashMessage('success', t('order_placed') . " (#$orderId). Мы свяжемся с вами для подтверждения.");
            redirect(APP_URL . '/buyer/orders.php?id=' . $orderId);

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Ошибка оформления заказа. Попробуйте снова.';
        }
    }

    // Re-prefill from POST on error
    $prefillFname   = $firstName;
    $prefillLname   = $lastName;
    $prefillEmail   = $email;
    $prefillPhone   = $phone;
    $prefillAddr    = $address;
    $prefillCity    = $city;
    $prefillZip     = $zipCode;
    $prefillCountry = $country;
}

$pageTitle = t('checkout');
require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= sanitize($csrf) ?>">

<?= breadcrumb([
    ['label' => t('home'),      'url' => APP_URL . '/index.php'],
    ['label' => t('shop'),      'url' => APP_URL . '/catalog/index.php'],
    ['label' => t('your_cart'), 'url' => APP_URL . '/buyer/cart.php'],
    ['label' => t('checkout')],
]) ?>

<!--Checkout page section-->
<div class="checkout_page_bg">
    <div class="container">
        <div class="Checkout_section">

            <?php if (!empty($errors)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="user-actions">
                        <div class="checkout_info">
                            <ul>
                                <?php foreach ($errors as $err): ?>
                                <li><?= sanitize($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="checkout_form">
                <form method="post" action="" id="checkout-form">
                    <input type="hidden" name="_csrf" value="<?= sanitize($csrf) ?>">

                    <div class="row">
                        <!-- ── BILLING DETAILS ── -->
                        <div class="col-lg-6 col-md-6">
                            <div class="checkout_form_left">
                                <h3><?= t('billing_details') ?></h3>
                                <div class="row">
                                    <div class="col-lg-6 mb-20">
                                        <label><?= t('first_name') ?> <span>*</span></label>
                                        <input type="text" name="first_name"
                                               value="<?= sanitize($prefillFname) ?>"
                                               placeholder="<?= t('first_name') ?>"
                                               required>
                                    </div>
                                    <div class="col-lg-6 mb-20">
                                        <label><?= t('last_name') ?> <span>*</span></label>
                                        <input type="text" name="last_name"
                                               value="<?= sanitize($prefillLname) ?>"
                                               placeholder="<?= t('last_name') ?>"
                                               required>
                                    </div>
                                    <div class="col-lg-6 mb-20">
                                        <label><?= t('email') ?> <span>*</span></label>
                                        <input type="email" name="email"
                                               value="<?= sanitize($prefillEmail) ?>"
                                               placeholder="email@example.com"
                                               required>
                                    </div>
                                    <div class="col-lg-6 mb-20">
                                        <label><?= t('phone') ?> <span>*</span></label>
                                        <input type="tel" name="phone" data-phone="tj"
                                               value="<?= sanitize($prefillPhone) ?>"
                                               placeholder="+992 (__) ___-__-__"
                                               required>
                                    </div>
                                    <div class="col-12 mb-20">
                                        <label><?= t('address') ?> <span>*</span></label>
                                        <input type="text" name="address"
                                               value="<?= sanitize($prefillAddr) ?>"
                                               placeholder="<?= t('address') ?>"
                                               required>
                                    </div>
                                    <div class="col-lg-6 mb-20">
                                        <label><?= t('zip_code') ?></label>
                                        <input type="text" name="zip_code"
                                               value="<?= sanitize($prefillZip) ?>"
                                               placeholder="123456"
                                               maxlength="20">
                                    </div>
                                    <div class="col-lg-6 mb-20">
                                        <label><?= t('country') ?></label>
                                        <?php
                                        $countryList = ['Таджикистан', 'Узбекистан', 'Кыргызстан', 'Казахстан', 'Россия', 'Афганистан', 'Туркменистан'];
                                        $selCountry  = $prefillCountry ?: 'Таджикистан';
                                        if (!in_array($selCountry, $countryList, true)) { array_unshift($countryList, $selCountry); }
                                        ?>
                                        <select name="country" id="checkout-country" class="form-control">
                                            <?php foreach ($countryList as $cn): ?>
                                            <option value="<?= sanitize($cn) ?>" <?= $selCountry === $cn ? 'selected' : '' ?>><?= sanitize($cn) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-20">
                                        <label><?= t('city') ?> <span>*</span></label>
                                        <?php $isTajik = ($selCountry === 'Таджикистан'); ?>
                                        <?php if (!empty($deliveryZones)): ?>
                                        <select name="city" id="checkout-city" required class="form-control"
                                                <?= !$isTajik ? 'style="display:none;" disabled' : '' ?>>
                                            <option value="">— Выберите город —</option>
                                            <?php foreach ($deliveryZones as $z):
                                                $cc = convertPrice((float)$z['cost']);
                                                $costLabel = $cc > 0
                                                    ? ' (' . number_format($cc, 2, '.', ',') . ' ' . getCurrencySymbol() . ')'
                                                    : ' (уточняется)';
                                            ?>
                                            <option value="<?= sanitize($z['city']) ?>"
                                                    data-cost="<?= number_format($cc, 2, '.', '') ?>"
                                                    data-days="<?= sanitize($z['delivery_days'] ?? '') ?>"
                                                    <?= $prefillCity === $z['city'] ? 'selected' : '' ?>>
                                                <?= sanitize($z['city'] . $costLabel) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" id="checkout-city-text" name="city"
                                               value="<?= !$isTajik ? sanitize($prefillCity) : '' ?>"
                                               placeholder="Введите название города"
                                               class="form-control"
                                               <?= $isTajik ? 'style="display:none;" disabled' : 'required' ?>>
                                        <?php else: ?>
                                        <input type="text" name="city"
                                               value="<?= sanitize($prefillCity) ?>"
                                               placeholder="<?= t('city') ?>"
                                               required>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12 mb-20">
                                        <div class="order-notes">
                                            <label for="order_notes"><?= t('order_notes') ?></label>
                                            <textarea id="order_notes" name="order_notes"
                                                      placeholder="<?= t('order_notes_placeholder') ?>"><?= sanitize($_POST['order_notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── ORDER SUMMARY ── -->
                        <div class="col-lg-6 col-md-6">
                            <div class="checkout_form_right">
                                <h3><?= t('order_summary') ?></h3>
                                <div class="order_table table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th><?= t('product') ?></th>
                                                <th><?= t('total') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cartItems as $item): ?>
                                            <tr>
                                                <td><?= sanitize(truncate($item['name'], 36)) ?> <strong>&times; <?= (int)$item['quantity'] ?></strong></td>
                                                <td><?= formatPrice((float)$item['price'] * (int)$item['quantity']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th><?= t('subtotal') ?></th>
                                                <td><?= formatPrice($cartTotal) ?></td>
                                            </tr>
                                            <tr>
                                                <th><?= t('shipping') ?></th>
                                                <td id="summary-shipping"><strong><?= !empty($deliveryZones) ? '—' : t('free') ?></strong></td>
                                            </tr>
                                            <tr class="order_total">
                                                <th><?= t('total') ?></th>
                                                <td id="summary-total"><strong><?= formatPrice($cartTotal) ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="payment_method">
                                    <div class="panel-default">
                                        <input id="payment_bank" name="payment_method" type="radio"
                                               value="bank_transfer"
                                               <?= ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'checked' : '' ?>>
                                        <label for="payment_bank"><?= t('bank_transfer') ?></label>
                                    </div>
                                    <div class="panel-default">
                                        <input id="payment_cod" name="payment_method" type="radio"
                                               value="cash_on_delivery"
                                               <?= ($_POST['payment_method'] ?? 'cash_on_delivery') === 'cash_on_delivery' ? 'checked' : '' ?>>
                                        <label for="payment_cod"><?= t('cash_on_delivery') ?></label>
                                    </div>
                                    <div class="order_button">
                                        <button type="submit"><?= t('place_order') ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.row -->
                </form>
            </div>

        </div>
    </div>
</div>
<!--Checkout page section end-->

<?php if (!empty($deliveryZones)): ?>
<script>
(function () {
    var subtotal    = <?= json_encode(round(convertPrice($cartTotal), 2)) ?>;
    var symbol      = <?= json_encode(getCurrencySymbol()) ?>;
    var citySelect  = document.getElementById('checkout-city');
    var cityText    = document.getElementById('checkout-city-text');
    var countrySel  = document.getElementById('checkout-country');
    var shipCell    = document.getElementById('summary-shipping');
    var totalCell   = document.getElementById('summary-total');

    function fmt(n) {
        return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + symbol;
    }

    function updateShipping() {
        var cost = 0, label;
        if (countrySel && countrySel.value !== 'Таджикистан') {
            label = 'Уточняется';
        } else if (citySelect && citySelect.value) {
            var opt = citySelect.options[citySelect.selectedIndex];
            cost = parseFloat(opt.getAttribute('data-cost')) || 0;
            var days = opt.getAttribute('data-days') || '';
            label = cost > 0 ? fmt(cost) : 'Уточняется';
            if (days) label += ' <small style="color:#888;">(' + days + ')</small>';
        } else {
            label = 'Выберите город';
        }
        if (shipCell)  shipCell.innerHTML  = '<strong>' + label + '</strong>';
        if (totalCell) totalCell.innerHTML = '<strong>' + fmt(subtotal + cost) + '</strong>';
    }

    function switchCountry() {
        if (!citySelect || !cityText) return;
        var isTajik = (countrySel.value === 'Таджикистан');
        if (isTajik) {
            citySelect.style.display = '';
            citySelect.disabled = false;
            citySelect.required = true;
            cityText.style.display = 'none';
            cityText.disabled = true;
            cityText.required = false;
            cityText.value = '';
        } else {
            citySelect.style.display = 'none';
            citySelect.disabled = true;
            citySelect.required = false;
            cityText.style.display = '';
            cityText.disabled = false;
            cityText.required = true;
            citySelect.value = '';
        }
        updateShipping();
    }

    if (citySelect)  citySelect.addEventListener('change', updateShipping);
    if (countrySel)  countrySel.addEventListener('change', switchCountry);
    updateShipping();
})();
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
