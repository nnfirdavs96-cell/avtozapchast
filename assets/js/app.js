/* АвтоЗапчасть — app.js */

// Add to cart via AJAX
function addToCart(partId, qty) {
    qty = qty || 1;
    fetch('/api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'add', part_id: partId, quantity: qty, _csrf: window._csrf})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast(d.message || 'Добавлено в корзину', 'success');
            document.querySelectorAll('.cart_count').forEach(el => el.textContent = d.cart_count);
        } else {
            if (d.redirect) { window.location = d.redirect; }
            else showToast(d.message || 'Ошибка', 'danger');
        }
    })
    .catch(() => showToast('Ошибка соединения', 'danger'));
}

// Add to wishlist via AJAX
function addToWishlist(partId) {
    fetch('/api/wishlist.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'toggle', part_id: partId, _csrf: window._csrf})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast(d.message, 'success');
            document.querySelectorAll('.wishlist_count').forEach(el => el.textContent = d.wishlist_count);
        } else {
            if (d.redirect) { window.location = d.redirect; }
            else showToast(d.message || 'Ошибка', 'danger');
        }
    })
    .catch(() => showToast('Ошибка соединения', 'danger'));
}

// Toast notification
function showToast(msg, type) {
    type = type || 'success';
    const colors = {success:'#28a745', danger:'#dc3545', warning:'#ffc107', info:'#17a2b8'};
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:20px;right:20px;z-index:99999;padding:12px 20px;border-radius:6px;background:${colors[type]||colors.success};color:#fff;font-weight:600;font-size:0.9rem;box-shadow:0 4px 12px rgba(0,0,0,0.2);transition:opacity 0.3s;max-width:320px`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// CSRF token from meta tag
window._csrf = document.querySelector('meta[name=csrf]') ? document.querySelector('meta[name=csrf]').content : '';

// Quantity input steppers
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.qty-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = this.closest('.quantity').querySelector('input[type=number]');
            if (!input) return;
            let val = parseInt(input.value) || 1;
            if (this.dataset.dir === '+') val = Math.min(val + 1, 99);
            if (this.dataset.dir === '-') val = Math.max(val - 1, 1);
            input.value = val;
        });
    });
});
