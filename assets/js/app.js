/* АвтоЗапчасть — app.js */

// Add to cart via AJAX
function refreshMiniCart() {
    fetch('/api/cart.php?action=mini')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            document.querySelectorAll('.cart_count').forEach(function(el) { el.textContent = d.cart_count; });
            document.querySelectorAll('.cart_amount').forEach(function(el) { el.innerHTML = d.cart_total_html; });
            document.querySelectorAll('.cart_subtotal').forEach(function(el) { el.innerHTML = d.cart_total_html; });
            var items = document.querySelector('.mini_cart_items');
            if (items) items.innerHTML = d.items_html;
        })
        .catch(function() {});
}

function addToCart(partId, qty) {
    qty = qty || 1;
    fetch('/api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'add', part_id: partId, quantity: qty, _csrf: window._csrf})
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            showToast(d.message || 'Добавлено в корзину', 'success');
            document.querySelectorAll('.cart_count').forEach(function(el) { el.textContent = d.cart_count; });
            if (d.cart_total_html) {
                document.querySelectorAll('.cart_amount').forEach(function(el) { el.innerHTML = d.cart_total_html; });
                document.querySelectorAll('.cart_subtotal').forEach(function(el) { el.innerHTML = d.cart_total_html; });
            }
            refreshMiniCart();
        } else {
            if (d.redirect) { window.location = d.redirect; }
            else showToast(d.message || 'Ошибка', 'danger');
        }
    })
    .catch(function() { showToast('Ошибка соединения', 'danger'); });
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

    // Collapse/expand subcategories in catalog sidebar widget
    document.querySelectorAll('.widget_categories .cat_toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var li = this.closest('.cat_parent');
            if (li) li.classList.toggle('is_open');
        });
    });

    // Availability toggle (checkbox) — navigate on change
    var availBox = document.getElementById('avail_in_stock');
    if (availBox) {
        availBox.addEventListener('change', function () {
            var url = this.checked ? this.getAttribute('data-on') : this.getAttribute('data-off');
            if (url) window.location.href = url;
        });
    }

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

    // ── Телефон с выбором страны: селектор кода + маска ────────────────
    // Маска заполняет шаблон вида "(XX) XXX-XX-XX"; разделители ставятся
    // только ПЕРЕД следующей цифрой, поэтому удаление работает без «отскока».
    // Список стран приходит из настроек (window.PHONE_COUNTRIES); по умолчанию — только Таджикистан.
    var PHONE_COUNTRIES = (window.PHONE_COUNTRIES && window.PHONE_COUNTRIES.length)
        ? window.PHONE_COUNTRIES
        : [{ code:'tj', dial:'992', flag:'🇹🇯', name:'Таджикистан', mask:'(XX) XXX-XX-XX' }];
    function pcByCode(code) {
        for (var i = 0; i < PHONE_COUNTRIES.length; i++) {
            if (PHONE_COUNTRIES[i].code === code) return PHONE_COUNTRIES[i];
        }
        return PHONE_COUNTRIES[0];
    }
    function pcFlagUrl(c) { return 'https://flagcdn.com/w40/' + c.code + '.png'; }
    function pcMaxDigits(c) { return (c.mask.match(/X/g) || []).length; }
    function pcApplyMask(mask, digits) {
        var out = '', di = 0;
        for (var i = 0; i < mask.length && di < digits.length; i++) {
            out += (mask[i] === 'X') ? digits[di++] : mask[i];
        }
        return out;
    }
    // National digits already known → full "+dial ...".
    function pcFormatNational(c, nat) {
        nat = nat.slice(0, pcMaxDigits(c));
        return nat.length ? '+' + c.dial + ' ' + pcApplyMask(c.mask, nat) : '';
    }
    // Strip the dial code from a raw string of all-digits.
    function pcStripDial(c, allDigits) {
        return (allDigits.slice(0, c.dial.length) === c.dial)
            ? allDigits.slice(c.dial.length) : allDigits;
    }
    // Guess country from a pre-filled value (longest dial match first).
    function pcDetect(val) {
        var d = (val || '').replace(/\D/g, '');
        var sorted = PHONE_COUNTRIES.slice().sort(function (a, b) { return b.dial.length - a.dial.length; });
        for (var i = 0; i < sorted.length; i++) {
            if (d.slice(0, sorted[i].dial.length) === sorted[i].dial) return sorted[i];
        }
        return null;
    }

    document.querySelectorAll('input[data-phone]').forEach(function (inp) {
        if (inp.dataset.phoneInit) return;
        inp.dataset.phoneInit = '1';

        var country = pcByCode(inp.getAttribute('data-phone'));

        // Pre-filled value: detect country from existing number, reformat
        if (inp.value.trim()) {
            var det = pcDetect(inp.value);
            if (det) country = det;
            inp.value = pcFormatNational(country, pcStripDial(country, inp.value.replace(/\D/g, '')));
        }

        inp.addEventListener('input', function () {
            inp.value = pcFormatNational(country, pcStripDial(country, inp.value.replace(/\D/g, '')));
        });

        // Селектор страны (кастомный, с картинками флагов) — только если включено больше одной страны.
        if (PHONE_COUNTRIES.length > 1) {
            var wrap = document.createElement('div');
            wrap.className = 'phone-wrap';
            inp.parentNode.insertBefore(wrap, inp);

            var cc = document.createElement('div');
            cc.className = 'phone-cc';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'phone-cc-btn';
            function ccBtnHtml(c) {
                return '<img src="' + pcFlagUrl(c) + '" alt="" width="22" height="16" loading="lazy">' +
                       '<span class="cc-d">+' + c.dial + '</span><i class="cc-caret">▾</i>';
            }
            btn.innerHTML = ccBtnHtml(country);

            var panel = document.createElement('div');
            panel.className = 'phone-cc-panel';
            PHONE_COUNTRIES.forEach(function (c) {
                var opt = document.createElement('button');
                opt.type = 'button';
                opt.className = 'phone-cc-opt';
                opt.innerHTML = '<img src="' + pcFlagUrl(c) + '" alt="" width="22" height="16" loading="lazy">' +
                                '<span class="cc-name">' + c.name + '</span>' +
                                '<span class="cc-dial">+' + c.dial + '</span>';
                opt.addEventListener('click', function () {
                    var prev = country;
                    country = c;
                    var nat = pcStripDial(prev, inp.value.replace(/\D/g, ''));
                    inp.value = pcFormatNational(country, nat);
                    btn.innerHTML = ccBtnHtml(country);
                    cc.classList.remove('open');
                    inp.focus();
                });
                panel.appendChild(opt);
            });

            btn.addEventListener('click', function () { cc.classList.toggle('open'); });
            document.addEventListener('click', function (e) { if (!cc.contains(e.target)) cc.classList.remove('open'); });

            cc.appendChild(btn);
            cc.appendChild(panel);
            wrap.appendChild(cc);
            wrap.appendChild(inp);
        }
    });

    // ── Показать/скрыть пароль (кнопка-глаз в .pwd-field)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pwd-toggle');
        if (!btn) return;
        e.preventDefault();
        var field = btn.closest('.pwd-field');
        var input = field ? field.querySelector('input') : null;
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'fa fa-eye-slash' : 'fa fa-eye';
        btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
    });

    // ── Auth: переключение вкладок «По номеру» / «По email»
    document.querySelectorAll('.auth_tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var which = this.getAttribute('data-auth-tab');
            var root  = this.closest('.account_form');
            if (!root) return;
            root.querySelectorAll('.auth_tab').forEach(function (t) {
                t.classList.toggle('active', t.getAttribute('data-auth-tab') === which);
            });
            root.querySelectorAll('.auth_pane').forEach(function (p) {
                p.style.display = (p.getAttribute('data-auth-pane') === which) ? '' : 'none';
            });
        });
    });

    // ── Auth: «Войти по паролю» (раскрыть поле пароля во вкладке номера)
    document.querySelectorAll('.pwd_login_toggle').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var form = this.closest('form');
            var wrap = form ? form.querySelector('.pwd_login_wrap') : null;
            if (!wrap) return;
            var open = wrap.style.display !== 'none';
            wrap.style.display = open ? 'none' : 'block';
            this.textContent = open ? 'Войти по паролю' : 'Войти по SMS-коду';
        });
    });

    // ── Auth: отправка SMS-кода (AJAX)
    document.querySelectorAll('.sms_send_btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form   = this.closest('form');
            if (!form) return;
            var phone  = (form.querySelector('input[name="phone"]') || {}).value || '';
            var mode   = form.getAttribute('data-sms-mode') || 'login';
            var csrf   = (form.querySelector('input[name="csrf_token"]') || {}).value || '';
            var status = form.querySelector('.sms_send_status');
            var wrap   = form.querySelector('.sms_code_wrap');
            var self   = this;

            if (phone.replace(/\D+/g, '').length < 9) {
                if (status) { status.style.color = '#c0392b'; status.textContent = 'Введите номер'; }
                return;
            }
            self.disabled = true;
            if (status) { status.style.color = '#888'; status.textContent = 'Отправка…'; }

            var fd = new FormData();
            fd.append('phone', phone);
            fd.append('mode', mode);
            fd.append('csrf_token', csrf);

            fetch((window.APP_URL || '') + '/api/sms_auth.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        if (wrap) wrap.style.display = 'block';
                        var codeInput = form.querySelector('input[name="code"]');
                        if (codeInput) codeInput.focus();
                        if (status) {
                            status.style.color = '#0a7';
                            status.textContent = data.dev_code
                                ? ('Код (тест-режим): ' + data.dev_code)
                                : 'Код отправлен по SMS';
                        }
                        // Кулдаун 60с
                        var left = 60;
                        self.textContent = 'Повторить (' + left + ')';
                        var timer = setInterval(function () {
                            left--;
                            if (left <= 0) {
                                clearInterval(timer);
                                self.disabled = false;
                                self.innerHTML = '<i class="fa fa-paper-plane-o"></i> Отправить код снова';
                            } else {
                                self.textContent = 'Повторить (' + left + ')';
                            }
                        }, 1000);
                    } else {
                        self.disabled = false;
                        if (status) { status.style.color = '#c0392b'; status.textContent = data.error || 'Ошибка'; }
                    }
                })
                .catch(function () {
                    self.disabled = false;
                    if (status) { status.style.color = '#c0392b'; status.textContent = 'Ошибка сети'; }
                });
        });
    });

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

    // ── Надёжное закрытие мини-корзины (оверлей / крестик / Escape).
    function closeMiniCart() {
        var els = document.querySelectorAll('.mini_cart, .off_canvars_overlay, .offcanvas_menu_wrapper');
        els.forEach(function (el) { el.classList.remove('active'); });
        // также через jQuery чтобы перекрыть обработчики темы
        if (window.jQuery) {
            jQuery('.mini_cart,.off_canvars_overlay,.offcanvas_menu_wrapper').removeClass('active');
        }
    }
    // Вешаем на capture-фазу — сработает раньше, чем jQuery-обработчики темы
    document.addEventListener('click', function (e) {
        var tgt = e.target;
        if (tgt.classList.contains('off_canvars_overlay') ||
            tgt.closest('.mini_cart_close') ||
            tgt.closest('.mini_cart_wrapper .mini_cart_close')) {
            closeMiniCart();
        }
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) closeMiniCart();
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
