<?php
require_once dirname(__DIR__) . '/config/config.php';

// Оформление доступно и гостю (login-wall снят): заказ привяжем к аккаунту по телефону.
$user = getCurrentUser() ?: [];   // [] для гостя
$db   = getDB();
$csrf = generateCsrfToken();

// Load cart (гость → сессия, авторизованный → БД)
$cartItems = cartDetailedItems($db);

if (empty($cartItems)) {
    flashMessage('warning', 'Ваша корзина пуста. Добавьте товары перед оформлением заказа.');
    redirect(APP_URL . '/buyer/cart.php');
}

$cartTotal = 0.0;
foreach ($cartItems as $item) {
    $cartTotal += (float)$item['price'] * (int)$item['quantity'];
}

// Active delivery zones (with country when migration applied).
$deliveryZones   = [];
$zonesByCountry  = [];   // country => [ ['city'=>…,'cost'=>…,'days'=>…], … ]
try {
    $hasZoneCountry = (bool)$db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_zones' AND COLUMN_NAME = 'country'"
    )->fetchColumn();
    $countryExpr = $hasZoneCountry ? "COALESCE(country,'Таджикистан')" : "'Таджикистан'";
    $deliveryZones = $db->query(
        "SELECT city, cost, delivery_days, {$countryExpr} AS country
         FROM delivery_zones WHERE is_active = 1 ORDER BY sort_order, city"
    )->fetchAll();
    foreach ($deliveryZones as $z) {
        $zonesByCountry[$z['country']][] = $z;
    }
} catch (Throwable $e) {
    $deliveryZones = [];
}
// Costs for selected country (used server-side after POST)
$selectedCountryForPost = $_POST['country'] ?? ($prefillCountry ?: 'Таджикистан');
$activeZonesForCountry  = $zonesByCountry[$selectedCountryForPost] ?? [];
$zoneCostByCity = [];
foreach ($activeZonesForCountry as $z) { $zoneCostByCity[$z['city']] = (float)$z['cost']; }

// Whether the orders table already has the shipping_cost / discount_amount columns
// (migrations applied). Inserts include each column only when it exists.
$hasShippingCostCol = false;
$hasDiscountCol     = false;
try {
    $hasShippingCostCol = (bool)$db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_cost'"
    )->fetchColumn();
    $hasDiscountCol = (bool)$db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'discount_amount'"
    )->fetchColumn();
} catch (Throwable $e) {
    $hasShippingCostCol = false;
}

// Load user profile for prefilling (только для авторизованного; гость — пустые поля)
$profile = [];
if (!empty($user['id'])) {
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
    $allowedMethods = ['bank_transfer', 'cash_on_delivery'];
    if (onlinePaymentEnabled()) $allowedMethods[] = 'online_payment';
    $payMethod   = in_array($_POST['payment_method'] ?? '', $allowedMethods, true)
                   ? $_POST['payment_method']
                   : 'cash_on_delivery';

    // Validate
    if (empty($firstName)) $errors[] = t('first_name') . ' — обязательное поле.';
    if (empty($lastName))  $errors[] = t('last_name')  . ' — обязательное поле.';
    // Email is optional (phone-registered buyers may not have one); validate only when provided.
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Укажите корректный email.';
    if (empty($phone))   $errors[] = t('phone')   . ' — обязательное поле.';
    if (empty($address)) $errors[] = t('address') . ' — обязательное поле.';
    if (empty($city))    $errors[] = t('city')    . ' — обязательное поле.';

    // Recalculate per-country costs using the submitted country value.
    $postCountryZones = $zonesByCountry[$country] ?? [];
    $postZoneCosts    = [];
    foreach ($postCountryZones as $z) { $postZoneCosts[$z['city']] = (float)$z['cost']; }

    // If the chosen country has delivery zones, the city must be one of them.
    if (!empty($postCountryZones) && $city !== '' && !isset($postZoneCosts[$city])) {
        $errors[] = 'Выберите город доставки из списка.';
    }

    // Delivery cost: use zone price if found, otherwise 0 (уточняется).
    $shippingCost = isset($postZoneCosts[$city]) ? $postZoneCosts[$city] : 0.0;

    // Online-payment incentive (admin-configured): money discount and/or free delivery.
    $discount = 0.0;
    if ($payMethod === 'online_payment' && onlinePaymentEnabled()) {
        $discount = onlinePaymentDiscount($cartTotal);
        if (onlinePaymentSettings()['free_ship']) $shippingCost = 0.0;
    }
    $grandTotal = max(0.0, $cartTotal + $shippingCost - $discount);

    // Заказ гостя привязываем к аккаунту по телефону (создаём/находим). Авторизованный — свой id.
    $orderUserId = !empty($user['id']) ? (int)$user['id'] : guestOrderUserId($db, $phone, $firstName, $lastName);
    if (empty($errors) && $orderUserId <= 0) {
        $errors[] = 'Не удалось оформить заказ по указанному телефону. Проверьте номер.';
    }

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
            // Insert order. total_amount includes delivery minus any discount;
            // shipping_cost / discount_amount are stored separately when those
            // columns exist (migrations applied).
            $extraCols = '';
            $extraPlace = '';
            $extraVals = [];
            if ($hasShippingCostCol) { $extraCols .= ', shipping_cost';    $extraPlace .= ', ?'; $extraVals[] = $shippingCost; }
            if ($hasDiscountCol)     { $extraCols .= ', discount_amount'; $extraPlace .= ', ?'; $extraVals[] = $discount; }

            $ordStmt = $db->prepare(
                "INSERT INTO orders (user_id, status, total_amount{$extraCols}, shipping_address, notes, payment_method, created_at, updated_at)
                 VALUES (?, 'pending', ?{$extraPlace}, ?, ?, ?, NOW(), NOW())"
            );
            $ordStmt->execute(array_merge(
                [$orderUserId, $grandTotal],
                $extraVals,
                [$shippingData, $orderNotes ?: null, $payMethod]
            ));
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

            // Clear cart (гость → сессия, авторизованный → БД)
            cartClearAny($db);

            $db->commit();

            flashMessage('success', t('order_placed') . " (#$orderId). Мы свяжемся с вами для подтверждения.");
            // Авторизованный видит детали заказа; гость не залогинен — на главную.
            redirect(isLoggedIn() ? APP_URL . '/buyer/orders.php?id=' . $orderId : APP_URL . '/index.php');

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
                                        <label><?= t('email') ?></label>
                                        <input type="email" name="email"
                                               value="<?= sanitize($prefillEmail) ?>"
                                               placeholder="email@example.com">
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
                                            <tr id="summary-discount-row" style="display:none;color:#2e7d32;">
                                                <th><?= t('online_discount') ?></th>
                                                <td id="summary-discount"><strong>−<?= formatPrice(0) ?></strong></td>
                                            </tr>
                                            <tr class="order_total">
                                                <th><?= t('total') ?></th>
                                                <td id="summary-total"><strong><?= formatPrice($cartTotal) ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="payment_method">
                                    <?php if (onlinePaymentEnabled()): $opLabel = onlinePaymentIncentiveLabel(); ?>
                                    <div class="panel-default">
                                        <input id="payment_online" name="payment_method" type="radio"
                                               value="online_payment"
                                               <?= ($_POST['payment_method'] ?? '') === 'online_payment' ? 'checked' : '' ?>>
                                        <label for="payment_online">
                                            <?= t('online_payment') ?>
                                            <?php if ($opLabel !== ''): ?><span style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:10px;background:#e8f5e9;color:#2e7d32;font-size:0.78rem;font-weight:600;"><?= sanitize($opLabel) ?></span><?php endif; ?>
                                        </label>
                                    </div>
                                    <?php endif; ?>
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

<?php $opJs = onlinePaymentSettings(); if (!empty($deliveryZones) || $opJs['enabled']): ?>
<script>
(function () {
    var subtotal = <?= json_encode(round(convertPrice($cartTotal), 2)) ?>;
    var symbol   = <?= json_encode(getCurrencySymbol()) ?>;
    var FREE     = <?= json_encode(t('free')) ?>;
    var hasZones = <?= !empty($deliveryZones) ? 'true' : 'false' ?>;
    var OP = {
        enabled:  <?= $opJs['enabled'] ? 'true' : 'false' ?>,
        type:     <?= json_encode($opJs['type']) ?>,
        pct:      <?= json_encode($opJs['type'] === 'percent' ? (float)$opJs['value'] : 0) ?>,
        fixed:    <?= json_encode($opJs['type'] === 'fixed' ? round(convertPrice((float)$opJs['value']), 2) : 0) ?>,
        freeShip: <?= $opJs['free_ship'] ? 'true' : 'false' ?>
    };
    var zonesByCountry = <?php
        $jsZones = [];
        foreach ($zonesByCountry as $cn => $czones) {
            foreach ($czones as $z) {
                $jsZones[$cn][] = [
                    'city' => $z['city'],
                    'cost' => round(convertPrice((float)$z['cost']), 2),
                    'days' => $z['delivery_days'] ?? '',
                ];
            }
        }
        echo json_encode($jsZones, JSON_UNESCAPED_UNICODE);
    ?>;

    var citySelect = document.getElementById('checkout-city');
    var cityText   = document.getElementById('checkout-city-text');
    var countrySel = document.getElementById('checkout-country');
    var shipCell   = document.getElementById('summary-shipping');
    var totalCell  = document.getElementById('summary-total');
    var discRow    = document.getElementById('summary-discount-row');
    var discCell   = document.getElementById('summary-discount');

    function fmt(n) {
        return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + symbol;
    }
    function selectedMethod() {
        var r = document.querySelector('input[name="payment_method"]:checked');
        return r ? r.value : '';
    }
    function discountFor(m) {
        if (!OP.enabled || m !== 'online_payment') return 0;
        var d = OP.type === 'fixed' ? OP.fixed : subtotal * OP.pct / 100;
        return Math.min(subtotal, Math.max(0, d));
    }
    // Current delivery selection → {cost, label}
    function shipInfo() {
        if (!hasZones) return { cost: 0, label: FREE };
        if (citySelect && !citySelect.disabled && citySelect.value) {
            var opt  = citySelect.options[citySelect.selectedIndex];
            var cost = parseFloat(opt.getAttribute('data-cost')) || 0;
            var days = opt.getAttribute('data-days') || '';
            var label = cost > 0 ? fmt(cost) : 'Уточняется';
            if (days) label += ' <small style="color:#888;">(' + days + ')</small>';
            return { cost: cost, label: label };
        }
        if (cityText && !cityText.disabled) return { cost: 0, label: 'Уточняется' };
        return { cost: 0, label: 'Выберите город' };
    }

    function recalc() {
        var m = selectedMethod();
        var info = shipInfo();
        var cost = info.cost, shipLabel = info.label;
        if (OP.enabled && m === 'online_payment' && OP.freeShip) { cost = 0; shipLabel = FREE; }
        var discount = discountFor(m);
        if (shipCell) shipCell.innerHTML = '<strong>' + shipLabel + '</strong>';
        if (discRow && discCell) {
            if (discount > 0) { discRow.style.display = ''; discCell.innerHTML = '<strong>−' + fmt(discount) + '</strong>'; }
            else discRow.style.display = 'none';
        }
        if (totalCell) totalCell.innerHTML = '<strong>' + fmt(Math.max(0, subtotal + cost - discount)) + '</strong>';
    }

    function rebuildCitySelect(zones, prefill) {
        citySelect.innerHTML = '<option value="">— Выберите город —</option>';
        zones.forEach(function(z) {
            var opt = document.createElement('option');
            opt.value = z.city;
            opt.setAttribute('data-cost', z.cost);
            opt.setAttribute('data-days', z.days);
            var costLabel = z.cost > 0 ? ' (' + z.cost.toFixed(2) + ' ' + symbol + ')' : ' (уточняется)';
            opt.textContent = z.city + costLabel;
            if (prefill && prefill === z.city) opt.selected = true;
            citySelect.appendChild(opt);
        });
    }
    function switchCountry(prefill) {
        if (!citySelect || !cityText || !countrySel) { recalc(); return; }
        var zones = zonesByCountry[countrySel.value] || [];
        if (zones.length > 0) {
            rebuildCitySelect(zones, prefill || null);
            citySelect.style.display = ''; citySelect.disabled = false; citySelect.required = true;
            cityText.style.display = 'none'; cityText.disabled = true; cityText.required = false; cityText.value = '';
        } else {
            citySelect.style.display = 'none'; citySelect.disabled = true; citySelect.required = false;
            cityText.style.display = ''; cityText.disabled = false; cityText.required = true; citySelect.value = '';
        }
        recalc();
    }

    if (citySelect) citySelect.addEventListener('change', recalc);
    if (countrySel) countrySel.addEventListener('change', function () { switchCountry(); });
    document.querySelectorAll('input[name="payment_method"]').forEach(function (r) {
        r.addEventListener('change', recalc);
    });

    if (hasZones) switchCountry(<?= json_encode($prefillCity) ?>);
    else recalc();
})();
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
