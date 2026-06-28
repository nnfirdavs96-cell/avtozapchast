<?php
require_once dirname(__DIR__) . '/config/config.php';

// Корзина доступна и гостю (login-wall снят): подбирает и оформляет без входа.
$user = getCurrentUser();   // null для гостя
$db   = getDB();
$csrf = generateCsrfToken();

// Handle update cart (POST action=update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности. Попробуйте снова.');
        redirect(APP_URL . '/buyer/cart.php');
    }
    $quantities = $_POST['quantity'] ?? [];
    if (is_array($quantities)) {
        foreach ($quantities as $partId => $qty) {
            cartSetQty($db, (int)$partId, max(1, min(99, (int)$qty)));
        }
    }
    flashMessage('success', 'Корзина обновлена.');
    redirect(APP_URL . '/buyer/cart.php');
}

// Load cart items (гость → сессия, авторизованный → БД)
$cartItems = cartDetailedItems($db);

$cartSubtotal = 0.0;
foreach ($cartItems as $item) {
    $cartSubtotal += (float)$item['price'] * (int)$item['quantity'];
}

$pageTitle = t('your_cart');
require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= sanitize($csrf) ?>">

<?= breadcrumb([
    ['label' => t('home'),      'url' => APP_URL . '/index.php'],
    ['label' => t('shop'),      'url' => APP_URL . '/catalog/index.php'],
    ['label' => t('your_cart')],
]) ?>

<!--shopping cart area start -->
<div class="cart_page_bg">
    <div class="container">
        <?= isLoggedIn() ? renderBuyerAccountNav('cart') : '' ?>
        <div class="shopping_cart_area">
            <?php if (empty($cartItems)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="table_desc">
                        <p style="text-align:center;padding:60px 20px;color:#888;"><?= t('cart_empty') ?></p>
                        <div class="cart_submit" style="text-align:center;">
                            <a href="<?= APP_URL ?>/catalog/index.php" class="button"><?= t('continue_shopping') ?></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <form method="post" action="" id="cart-update-form">
                <input type="hidden" name="_action" value="update">
                <input type="hidden" name="_csrf"   value="<?= sanitize($csrf) ?>">

                <div class="row">
                    <div class="col-12">
                        <div class="table_desc">
                            <div class="cart_page">
                                <table>
                                    <thead>
                                        <tr>
                                            <th class="product_thumb"><?= t('image') ?></th>
                                            <th class="product_name"><?= t('product') ?></th>
                                            <th class="product-price"><?= t('price') ?></th>
                                            <th class="product_quantity"><?= t('quantity') ?></th>
                                            <th class="product_total"><?= t('total') ?></th>
                                            <th class="product_remove"><?= t('remove') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $item):
                                            $imgUrl   = productImageUrl($item['images']);
                                            $rowTotal = (float)$item['price'] * (int)$item['quantity'];
                                        ?>
                                        <tr data-part-id="<?= (int)$item['part_id'] ?>">
                                            <td class="product_thumb">
                                                <a href="<?= partUrl((int)$item['part_id'], $item['name'] ?? '') ?>">
                                                    <img src="<?= sanitize($imgUrl) ?>"
                                                         alt="<?= sanitize($item['name']) ?>">
                                                </a>
                                            </td>
                                            <td class="product_name">
                                                <a href="<?= partUrl((int)$item['part_id'], $item['name'] ?? '') ?>">
                                                    <?= sanitize(truncate($item['name'], 50)) ?>
                                                </a>
                                                <p><?= sanitize($item['brand_name']) ?> &middot; <?= t('part_number') ?>: <?= sanitize($item['part_number']) ?></p>
                                            </td>
                                            <td class="product-price">
                                                <span class="amount"><?= formatPrice($item['price']) ?></span>
                                            </td>
                                            <td class="product_quantity">
                                                <label><?= t('quantity') ?></label>
                                                <input type="number"
                                                       name="quantity[<?= (int)$item['part_id'] ?>]"
                                                       value="<?= (int)$item['quantity'] ?>"
                                                       min="1" max="99"
                                                       onchange="updateRowTotal(this.closest('tr'));">
                                            </td>
                                            <td class="product_total">
                                                <span class="row-total" data-price="<?= (float)$item['price'] ?>">
                                                    <?= formatPrice($rowTotal) ?>
                                                </span>
                                            </td>
                                            <td class="product_remove">
                                                <a href="javascript:void(0)"
                                                   onclick="removeCartItem(<?= (int)$item['part_id'] ?>, this.closest('tr'))"
                                                   title="<?= t('remove') ?>">
                                                    <i class="fa fa-trash-o"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="cart_submit">
                                <button type="submit"><?= t('update_cart') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!--coupon code area start-->
                <div class="coupon_area">
                    <div class="row">
                        <div class="col-lg-6 col-md-6">
                            <div class="coupon_code left">
                                <h3><?= t('coupon') ?></h3>
                                <div class="coupon_inner">
                                    <p><?= t('coupon_hint') ?></p>
                                    <input placeholder="<?= t('coupon_code') ?>" type="text" name="coupon_code">
                                    <button type="button"><?= t('apply_coupon') ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <div class="coupon_code right">
                                <h3><?= t('cart_totals') ?></h3>
                                <div class="coupon_inner">
                                    <div class="cart_subtotal">
                                        <p><?= t('subtotal') ?></p>
                                        <p class="cart_amount" id="cart-subtotal-display"><?= formatPrice($cartSubtotal) ?></p>
                                    </div>
                                    <div class="cart_subtotal">
                                        <p><?= t('shipping') ?></p>
                                        <p class="cart_amount"><span><?= t('free') ?></span></p>
                                    </div>
                                    <div class="cart_subtotal">
                                        <p><?= t('total') ?></p>
                                        <p class="cart_amount" id="cart-total-display"><?= formatPrice($cartSubtotal) ?></p>
                                    </div>
                                    <div class="checkout_btn">
                                        <a href="<?= APP_URL ?>/buyer/checkout.php"><?= t('proceed_checkout') ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--coupon code area end-->

            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<!--shopping cart area end -->

<script>
var CSRF_TOKEN = '<?= sanitize($csrf) ?>';

function formatPrice(amount) {
    return new Intl.NumberFormat('ru-RU', {maximumFractionDigits: 0}).format(amount) + ' ₽';
}

function updateRowTotal(row) {
    var input   = row.querySelector('input[type="number"]');
    var totalEl = row.querySelector('.row-total');
    var price   = parseFloat(totalEl.dataset.price);
    var qty     = parseInt(input.value) || 1;
    totalEl.textContent = formatPrice(price * qty);
    recalcCartTotal();
}

function recalcCartTotal() {
    var total = 0;
    document.querySelectorAll('.row-total').forEach(function(el) {
        total += parseFloat(el.dataset.price) * parseInt(el.closest('tr').querySelector('input[type="number"]').value);
    });
    var sub = document.getElementById('cart-subtotal-display');
    var tot = document.getElementById('cart-total-display');
    if (sub) sub.textContent = formatPrice(total);
    if (tot) tot.textContent = formatPrice(total);
}

function removeCartItem(partId, row) {
    fetch('<?= APP_URL ?>/api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'remove', part_id: partId, _csrf: CSRF_TOKEN})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            row.remove();
            recalcCartTotal();
            var cnt = document.querySelector('.cart_count');
            if (cnt) cnt.textContent = data.cart_count;
            var cprice = document.querySelector('.cart_price');
            if (cprice) cprice.innerHTML = formatPrice(data.cart_total) + ' <i class="ion-ios-arrow-down"></i>';
            if (document.querySelectorAll('tbody tr').length === 0) {
                location.reload();
            }
        }
    })
    .catch(function() {
        alert('Ошибка. Попробуйте снова.');
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
