<?php
require_once dirname(__DIR__) . '/config/config.php';

if (!isLoggedIn()) {
    redirect(APP_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = getCurrentUser();
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
        $upd = $db->prepare(
            "UPDATE cart SET quantity = ? WHERE user_id = ? AND part_id = ?"
        );
        foreach ($quantities as $partId => $qty) {
            $partId = (int)$partId;
            $qty    = max(1, min(99, (int)$qty));
            if ($partId > 0) {
                $upd->execute([$qty, $user['id'], $partId]);
            }
        }
    }
    flashMessage('success', 'Корзина обновлена.');
    redirect(APP_URL . '/buyer/cart.php');
}

// Load cart items
$cartStmt = $db->prepare(
    "SELECT c.id AS cart_id, c.part_id, c.quantity,
            p.name, p.part_number, p.price, p.stock, p.images,
            b.name AS brand_name
     FROM cart c
     JOIN parts p ON p.id = c.part_id
     LEFT JOIN brands b ON b.id = p.brand_id
     WHERE c.user_id = ? AND p.is_active = 1
     ORDER BY c.added_at DESC"
);
$cartStmt->execute([$user['id']]);
$cartItems = $cartStmt->fetchAll();

$cartSubtotal = 0.0;
foreach ($cartItems as $item) {
    $cartSubtotal += (float)$item['price'] * (int)$item['quantity'];
}

$pageTitle = t('your_cart');
require_once dirname(__DIR__) . '/includes/header.php';
?>
<meta name="csrf" content="<?= generateCsrfToken() ?>">

<?= breadcrumb([
    ['label' => t('home'),      'url' => APP_URL . '/index.php'],
    ['label' => t('shop'),      'url' => APP_URL . '/catalog/index.php'],
    ['label' => t('your_cart')],
]) ?>

<!-- Cart Area -->
<div class="cart_area">
    <div class="container">

        <?php if (empty($cartItems)): ?>
        <!-- Empty cart -->
        <div style="text-align:center;padding:80px 20px;">
            <i class="icon-shopping-bag2" style="font-size:5rem;color:#e0e0e0;display:block;margin-bottom:24px;"></i>
            <h3 style="color:#555;margin-bottom:12px;"><?= t('cart_empty') ?></h3>
            <p style="color:#999;margin-bottom:24px;">Вы ещё ничего не добавили в корзину.</p>
            <a href="<?= APP_URL ?>/catalog/index.php"
               style="display:inline-block;background:#d32f2f;color:#fff;padding:12px 32px;border-radius:4px;text-decoration:none;font-weight:600;">
                <?= t('continue_shopping') ?>
            </a>
        </div>

        <?php else: ?>
        <div class="row">
            <!-- ── CART TABLE ──────────────────────────────────────────── -->
            <div class="col-lg-8 col-md-12">
                <form method="post" action="" id="cart-update-form">
                    <input type="hidden" name="_action" value="update">
                    <input type="hidden" name="_csrf"   value="<?= sanitize($csrf) ?>">

                    <div class="cart_table table_desc">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="product_thumb"><?= t('product') ?? 'Товар' ?></th>
                                        <th class="product_name"><?= t('name') ?? 'Наименование' ?></th>
                                        <th class="product-price"><?= t('price') ?></th>
                                        <th class="product-quantity"><?= t('quantity') ?></th>
                                        <th class="product-subtotal"><?= t('total') ?></th>
                                        <th class="product-remove"><?= t('remove') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item):
                                        $imgUrl   = productImageUrl($item['images']);
                                        $rowTotal = (float)$item['price'] * (int)$item['quantity'];
                                    ?>
                                    <tr data-part-id="<?= (int)$item['part_id'] ?>">
                                        <td class="product_thumb">
                                            <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$item['part_id'] ?>">
                                                <img src="<?= sanitize($imgUrl) ?>"
                                                     alt="<?= sanitize($item['name']) ?>"
                                                     style="width:80px;height:70px;object-fit:cover;border-radius:4px;">
                                            </a>
                                        </td>
                                        <td class="product_name">
                                            <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$item['part_id'] ?>"
                                               style="font-weight:600;color:#333;text-decoration:none;">
                                                <?= sanitize(truncate($item['name'], 50)) ?>
                                            </a>
                                            <div style="font-size:0.75rem;color:#aaa;margin-top:3px;">
                                                <?= sanitize($item['brand_name']) ?>
                                                &middot; <?= t('part_number') ?>: <?= sanitize($item['part_number']) ?>
                                            </div>
                                        </td>
                                        <td class="product-price">
                                            <span class="amount"><?= formatPrice($item['price']) ?></span>
                                        </td>
                                        <td class="product-quantity">
                                            <div style="display:flex;align-items:center;gap:0;border:1px solid #e0e0e0;border-radius:4px;overflow:hidden;width:fit-content;margin:0 auto;">
                                                <button type="button"
                                                        style="width:32px;height:36px;background:#f5f5f5;border:none;font-size:1.1rem;cursor:pointer;color:#555;"
                                                        onclick="var inp=this.nextElementSibling;inp.value=Math.max(1,parseInt(inp.value)-1);updateRowTotal(this.closest('tr'));">
                                                    &minus;
                                                </button>
                                                <input type="number"
                                                       name="quantity[<?= (int)$item['part_id'] ?>]"
                                                       value="<?= (int)$item['quantity'] ?>"
                                                       min="1" max="99"
                                                       style="width:48px;height:36px;border:none;text-align:center;font-size:0.9rem;font-weight:600;outline:none;"
                                                       onchange="updateRowTotal(this.closest('tr'));">
                                                <button type="button"
                                                        style="width:32px;height:36px;background:#f5f5f5;border:none;font-size:1.1rem;cursor:pointer;color:#555;"
                                                        onclick="var inp=this.previousElementSibling;inp.value=Math.min(99,parseInt(inp.value)+1);updateRowTotal(this.closest('tr'));">
                                                    +
                                                </button>
                                            </div>
                                        </td>
                                        <td class="product-subtotal">
                                            <span class="amount row-total" data-price="<?= (float)$item['price'] ?>">
                                                <?= formatPrice($rowTotal) ?>
                                            </span>
                                        </td>
                                        <td class="product-remove">
                                            <a href="javascript:void(0)"
                                               onclick="removeCartItem(<?= (int)$item['part_id'] ?>, this.closest('tr'))"
                                               style="color:#d32f2f;font-size:1.2rem;text-decoration:none;"
                                               title="<?= t('remove') ?>">
                                                &times;
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div><!-- /.cart_table -->

                    <!-- Cart actions -->
                    <div class="cart_submit" style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;flex-wrap:wrap;gap:12px;">
                        <a href="<?= APP_URL ?>/catalog/index.php"
                           style="border:1px solid #d32f2f;color:#d32f2f;padding:9px 24px;border-radius:4px;text-decoration:none;font-weight:600;font-size:0.875rem;">
                            &larr; <?= t('continue_shopping') ?>
                        </a>
                        <button type="submit"
                                style="background:#d32f2f;color:#fff;border:none;padding:9px 24px;border-radius:4px;font-weight:600;cursor:pointer;font-size:0.875rem;">
                            <?= t('update_cart') ?>
                        </button>
                    </div>
                </form>
            </div><!-- /.col -->

            <!-- ── CART TOTALS ─────────────────────────────────────────── -->
            <div class="col-lg-4 col-md-12">
                <div class="cart_page_total" style="background:#f9f9f9;border:1px solid #eee;border-radius:6px;padding:28px 24px;">
                    <h2 style="font-size:1.1rem;font-weight:700;color:#222;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid #e0e0e0;">
                        <?= t('order_summary') ?? 'Итого по заказу' ?>
                    </h2>
                    <ul style="list-style:none;margin:0;padding:0;">
                        <li style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:0.875rem;color:#555;">
                            <span><?= t('subtotal') ?></span>
                            <span id="cart-subtotal-display" style="font-weight:600;color:#333;"><?= formatPrice($cartSubtotal) ?></span>
                        </li>
                        <li style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:0.875rem;color:#555;">
                            <span><?= t('free_delivery') ?? 'Доставка' ?></span>
                            <span style="color:#388e3c;font-weight:600;"><?= t('free') ?? 'Уточняется' ?></span>
                        </li>
                        <li style="display:flex;justify-content:space-between;padding:16px 0 0;font-size:1.05rem;">
                            <span style="font-weight:700;color:#222;"><?= t('total') ?></span>
                            <span id="cart-total-display" style="font-weight:700;color:#d32f2f;font-size:1.2rem;"><?= formatPrice($cartSubtotal) ?></span>
                        </li>
                    </ul>

                    <div style="margin-top:24px;">
                        <a href="<?= APP_URL ?>/buyer/checkout.php"
                           style="display:block;text-align:center;background:#d32f2f;color:#fff;padding:13px 24px;border-radius:4px;font-weight:700;font-size:0.95rem;text-decoration:none;transition:background 0.2s;"
                           onmouseover="this.style.background='#b71c1c'" onmouseout="this.style.background='#d32f2f'">
                            <?= t('proceed_checkout') ?> &rarr;
                        </a>
                    </div>
                </div>
            </div><!-- /.col -->

        </div><!-- /.row -->
        <?php endif; ?>

    </div><!-- /.container -->
</div><!-- /.cart_area -->

<script>
var CSRF_TOKEN = '<?= sanitize($csrf) ?>';

function formatPrice(amount) {
    return new Intl.NumberFormat('ru-RU', {maximumFractionDigits: 0}).format(amount) + ' ₽';
}

function updateRowTotal(row) {
    var input    = row.querySelector('input[type="number"]');
    var totalEl  = row.querySelector('.row-total');
    var price    = parseFloat(totalEl.dataset.price);
    var qty      = parseInt(input.value) || 1;
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
            // Update header cart count
            var cnt = document.querySelector('.cart_count');
            if (cnt) cnt.textContent = data.cart_count;
            var cprice = document.querySelector('.cart_price');
            if (cprice) cprice.innerHTML = formatPrice(data.cart_total) + ' <i class="ion-ios-arrow-down"></i>';
            // Check if cart empty
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
