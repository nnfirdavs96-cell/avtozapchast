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

// Load user profile for prefilling
$profileStmt = $db->prepare(
    "SELECT first_name, last_name, phone, email, address, city, zip_code, country
     FROM users WHERE id = ? LIMIT 1"
);
$profileStmt->execute([$user['id']]);
$profile = $profileStmt->fetch() ?: [];

// Fallback to session user data for email
$prefillEmail   = $profile['email']      ?? ($user['email'] ?? '');
$prefillPhone   = $profile['phone']      ?? ($user['phone'] ?? '');
$prefillFname   = $profile['first_name'] ?? '';
$prefillLname   = $profile['last_name']  ?? '';
$prefillAddr    = $profile['address']    ?? '';
$prefillCity    = $profile['city']       ?? '';
$prefillZip     = $profile['zip_code']   ?? '';
$prefillCountry = $profile['country']    ?? '';

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
            // Insert order
            $ordStmt = $db->prepare(
                "INSERT INTO orders (user_id, status, total_amount, shipping_address, notes, payment_method, created_at, updated_at)
                 VALUES (?, 'pending', ?, ?, ?, ?, NOW(), NOW())"
            );
            $ordStmt->execute([
                $user['id'],
                $cartTotal,
                $shippingData,
                $orderNotes ?: null,
                $payMethod,
            ]);
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
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb([
    ['label' => t('home'),     'url' => APP_URL . '/index.php'],
    ['label' => t('shop'),     'url' => APP_URL . '/catalog/index.php'],
    ['label' => t('your_cart'),'url' => APP_URL . '/buyer/cart.php'],
    ['label' => t('checkout')],
]) ?>

<!-- Checkout Area -->
<div class="checkout_area">
    <div class="container">

        <!-- Validation errors -->
        <?php if (!empty($errors)): ?>
        <div style="background:#ffebee;border:1px solid #ffcdd2;border-radius:4px;padding:14px 20px;margin-bottom:24px;">
            <p style="font-weight:700;color:#c62828;margin:0 0 8px;">Пожалуйста, исправьте следующие ошибки:</p>
            <ul style="margin:0;padding-left:20px;color:#c62828;">
                <?php foreach ($errors as $err): ?>
                <li style="font-size:0.875rem;"><?= sanitize($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post" action="" id="checkout-form">
            <input type="hidden" name="_csrf" value="<?= sanitize($csrf) ?>">

            <div class="row">

                <!-- ── BILLING FORM ──────────────────────────────────────── -->
                <div class="col-lg-7 col-md-12">
                    <div class="checkbox_form">
                        <h3><?= t('billing_details') ?></h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="checkout_form_input">
                                    <label for="first_name"><?= t('first_name') ?> <abbr title="required">*</abbr></label>
                                    <input type="text" id="first_name" name="first_name"
                                           value="<?= sanitize($prefillFname) ?>"
                                           placeholder="<?= t('first_name') ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="checkout_form_input">
                                    <label for="last_name"><?= t('last_name') ?> <abbr title="required">*</abbr></label>
                                    <input type="text" id="last_name" name="last_name"
                                           value="<?= sanitize($prefillLname) ?>"
                                           placeholder="<?= t('last_name') ?>"
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="checkout_form_input">
                                    <label for="email"><?= t('email') ?> <abbr title="required">*</abbr></label>
                                    <input type="email" id="email" name="email"
                                           value="<?= sanitize($prefillEmail) ?>"
                                           placeholder="email@example.com"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="checkout_form_input">
                                    <label for="phone"><?= t('phone') ?> <abbr title="required">*</abbr></label>
                                    <input type="tel" id="phone" name="phone"
                                           value="<?= sanitize($prefillPhone) ?>"
                                           placeholder="+7 (___) ___-__-__"
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="checkout_form_input">
                            <label for="address"><?= t('address') ?> <abbr title="required">*</abbr></label>
                            <input type="text" id="address" name="address"
                                   value="<?= sanitize($prefillAddr) ?>"
                                   placeholder="Улица, дом, квартира"
                                   required>
                        </div>

                        <div class="row">
                            <div class="col-md-5">
                                <div class="checkout_form_input">
                                    <label for="city"><?= t('city') ?> <abbr title="required">*</abbr></label>
                                    <input type="text" id="city" name="city"
                                           value="<?= sanitize($prefillCity) ?>"
                                           placeholder="Москва"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="checkout_form_input">
                                    <label for="zip_code"><?= t('zip_code') ?></label>
                                    <input type="text" id="zip_code" name="zip_code"
                                           value="<?= sanitize($prefillZip) ?>"
                                           placeholder="123456"
                                           maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="checkout_form_input">
                                    <label for="country"><?= t('country') ?></label>
                                    <input type="text" id="country" name="country"
                                           value="<?= sanitize($prefillCountry ?: 'Россия') ?>"
                                           placeholder="Россия">
                                </div>
                            </div>
                        </div>

                        <div class="checkout_form_input">
                            <label for="order_notes"><?= t('order_notes') ?></label>
                            <textarea id="order_notes" name="order_notes"
                                      rows="4"
                                      placeholder="Примечания к заказу: удобное время доставки, особые пожелания и т.д."><?= sanitize($_POST['order_notes'] ?? '') ?></textarea>
                        </div>

                        <!-- ── PAYMENT METHOD ─────────────────────────────── -->
                        <div class="payment_method" style="margin-top:28px;">
                            <h3><?= t('payment_method') ?></h3>
                            <div class="payment_option">

                                <div class="payment_option_inner" style="margin-bottom:12px;">
                                    <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:14px 16px;border:2px solid #<?= ($_POST['payment_method'] ?? 'cash_on_delivery') === 'bank_transfer' ? 'd32f2f' : 'e0e0e0' ?>;border-radius:4px;transition:border 0.2s;"
                                           id="label-bank">
                                        <input type="radio" name="payment_method" value="bank_transfer"
                                               <?= ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'checked' : '' ?>
                                               style="margin-top:2px;accent-color:#d32f2f;"
                                               onchange="document.getElementById('label-bank').style.borderColor='#d32f2f';document.getElementById('label-cod').style.borderColor='#e0e0e0';">
                                        <div>
                                            <strong style="display:block;margin-bottom:2px;">Банковский перевод</strong>
                                            <span style="font-size:0.8rem;color:#888;">Оплата по реквизитам после подтверждения заказа. Реквизиты будут высланы на email.</span>
                                        </div>
                                    </label>
                                </div>

                                <div class="payment_option_inner">
                                    <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:14px 16px;border:2px solid #<?= ($_POST['payment_method'] ?? 'cash_on_delivery') === 'cash_on_delivery' ? 'd32f2f' : 'e0e0e0' ?>;border-radius:4px;transition:border 0.2s;"
                                           id="label-cod">
                                        <input type="radio" name="payment_method" value="cash_on_delivery"
                                               <?= ($_POST['payment_method'] ?? 'cash_on_delivery') === 'cash_on_delivery' ? 'checked' : '' ?>
                                               style="margin-top:2px;accent-color:#d32f2f;"
                                               onchange="document.getElementById('label-cod').style.borderColor='#d32f2f';document.getElementById('label-bank').style.borderColor='#e0e0e0';">
                                        <div>
                                            <strong style="display:block;margin-bottom:2px;">Наличными при получении</strong>
                                            <span style="font-size:0.8rem;color:#888;">Оплата курьеру или при самовывозе. Без переплат и комиссий.</span>
                                        </div>
                                    </label>
                                </div>

                            </div><!-- /.payment_option -->
                        </div><!-- /.payment_method -->

                    </div><!-- /.checkbox_form -->
                </div><!-- /.col -->

                <!-- ── ORDER SUMMARY ─────────────────────────────────────── -->
                <div class="col-lg-5 col-md-12">
                    <div class="order_review" style="background:#f9f9f9;border:1px solid #eee;border-radius:6px;padding:28px 24px;position:sticky;top:80px;">
                        <h3 style="font-size:1.1rem;font-weight:700;color:#222;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid #e0e0e0;">
                            <?= t('order_summary') ?>
                        </h3>

                        <!-- Items list -->
                        <div class="your_order_table table_desc" style="margin-bottom:20px;">
                            <div class="table-responsive">
                                <table class="table" style="margin:0;">
                                    <thead>
                                        <tr>
                                            <th style="font-size:0.78rem;text-transform:uppercase;color:#888;font-weight:600;padding:6px 0;"><?= t('product') ?? 'Товар' ?></th>
                                            <th style="font-size:0.78rem;text-transform:uppercase;color:#888;font-weight:600;padding:6px 0;text-align:right;"><?= t('subtotal') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $item): ?>
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:0.85rem;color:#333;vertical-align:top;">
                                                <?= sanitize(truncate($item['name'], 36)) ?>
                                                <span style="color:#aaa;font-size:0.75rem;">&nbsp;&times;&nbsp;<?= (int)$item['quantity'] ?></span>
                                            </td>
                                            <td style="padding:8px 0;border-bottom:1px solid #eee;font-size:0.85rem;color:#333;text-align:right;white-space:nowrap;font-weight:600;vertical-align:top;">
                                                <?= formatPrice((float)$item['price'] * (int)$item['quantity']) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td style="padding:12px 0 8px;font-size:0.875rem;color:#555;"><?= t('subtotal') ?></td>
                                            <td style="padding:12px 0 8px;text-align:right;font-size:0.875rem;color:#555;"><?= formatPrice($cartTotal) ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;font-size:0.875rem;color:#555;"><?= t('free_delivery') ?? 'Доставка' ?></td>
                                            <td style="padding:8px 0;text-align:right;font-size:0.875rem;color:#388e3c;font-weight:600;"><?= t('free') ?? 'Уточняется' ?></td>
                                        </tr>
                                        <tr style="border-top:2px solid #e0e0e0;">
                                            <td style="padding:14px 0 4px;font-size:1rem;font-weight:700;color:#222;"><?= t('total') ?></td>
                                            <td style="padding:14px 0 4px;text-align:right;font-size:1.1rem;font-weight:700;color:#d32f2f;"><?= formatPrice($cartTotal) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Place order button -->
                        <button type="submit" form="checkout-form"
                                style="width:100%;padding:14px 24px;background:#d32f2f;color:#fff;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer;transition:background 0.2s;"
                                onmouseover="this.style.background='#b71c1c'" onmouseout="this.style.background='#d32f2f'">
                            <?= t('place_order') ?>
                        </button>

                        <p style="font-size:0.75rem;color:#aaa;text-align:center;margin-top:14px;">
                            Нажимая кнопку, вы соглашаетесь с условиями обработки персональных данных.
                        </p>
                    </div><!-- /.order_review -->
                </div><!-- /.col -->

            </div><!-- /.row -->
        </form>

    </div><!-- /.container -->
</div><!-- /.checkout_area -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
