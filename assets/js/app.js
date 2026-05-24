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

// Apply data-bgimg as background-image, picking the mobile variant on small screens.
// Height is fully controlled by CSS (380px on mobile, 420px on desktop).
function applyResponsiveBg() {
    var isMobile = window.matchMedia('(max-width: 767px)').matches;
    document.querySelectorAll('[data-bgimg]').forEach(function (el) {
        var desktop = el.getAttribute('data-bgimg');
        var mobile  = el.getAttribute('data-bgimg-mobile');
        var url = (isMobile && mobile) ? mobile : desktop;
        if (url) el.style.backgroundImage = 'url(' + url + ')';
    });
}
// Mazlay main.js re-applies data-bgimg on window 'load'; run after it so the mobile variant wins.
window.addEventListener('load', applyResponsiveBg);
document.addEventListener('DOMContentLoaded', function () {
    applyResponsiveBg();
    var _bgResizeT;
    window.addEventListener('resize', function () {
        clearTimeout(_bgResizeT);
        _bgResizeT = setTimeout(applyResponsiveBg, 150);
    });

    // Toggle for the categories dropdown (class-based, works on mobile touch)
    document.querySelectorAll('.categori_toggle').forEach(function (toggle) {
        toggle.style.cursor = 'pointer';
        toggle.addEventListener('click', function () {
            var menu = this.closest('.categories_menu').querySelector('.categories_menu_toggle');
            if (menu) menu.classList.toggle('is-open');
        });
    });
    // Close categories dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.categories_menu')) {
            document.querySelectorAll('.categories_menu_toggle.is-open').forEach(function (m) {
                m.classList.remove('is-open');
            });
        }
    });

    var isMobile = function () { return window.matchMedia('(max-width: 991px)').matches; };

    // ── Body lock when offcanvas is open (prevents background scroll & x-overflow)
    var lockObserver = new MutationObserver(function () {
        var wrap = document.querySelector('.offcanvas_menu_wrapper');
        if (!wrap) return;
        if (wrap.classList.contains('active')) document.body.classList.add('no-scroll');
        else document.body.classList.remove('no-scroll');
    });
    var ocWrap = document.querySelector('.offcanvas_menu_wrapper');
    if (ocWrap) lockObserver.observe(ocWrap, { attributes: true, attributeFilter: ['class'] });

    // ── Cart icon: на мобиле — прямая ссылка на /buyer/cart.php, не открывать панель
    document.querySelectorAll('.mini_cart_wrapper > a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (isMobile()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.location.href = (window.APP_URL || '') + '/buyer/cart.php';
            }
        }, true);
    });

    // ── Filter sidebar accordion: на мобиле каждый widget_list — collapsible
    if (isMobile()) {
        document.querySelectorAll('.sidebar_widget .widget_list').forEach(function (w) {
            var h = w.querySelector('h3');
            if (!h) return;
            var body = document.createElement('div');
            body.className = 'widget_body';
            while (h.nextSibling) body.appendChild(h.nextSibling);
            w.appendChild(body);
            body.style.display = 'none';
            h.style.cursor = 'pointer';
            h.classList.add('widget_toggle');
            h.addEventListener('click', function () {
                var open = body.style.display !== 'none';
                body.style.display = open ? 'none' : 'block';
                h.classList.toggle('open', !open);
            });
        });
    }

});

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

// Scroll-to-top fallback (на случай если scrollUp-плагин не работает)
document.addEventListener('DOMContentLoaded', function() {
    // Ждём создания #scrollUp плагином
    setTimeout(function() {
        var btn = document.getElementById('scrollUp');
        if (!btn) {
            // Создаём кнопку сами
            btn = document.createElement('a');
            btn.id = 'scrollUp';
            btn.href = '#';
            btn.innerHTML = '<i class="fa fa-angle-double-up"></i>';
            document.body.appendChild(btn);
        }
        // Перехватываем клик
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        // Показ/скрытие при скролле
        window.addEventListener('scroll', function() {
            btn.style.display = window.scrollY > 300 ? 'block' : 'none';
        }, { passive: true });
    }, 800);
});
