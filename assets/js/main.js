/* АвтоЗапчасть / AutoDoc — storefront JS */
(function () {
  'use strict';

  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const csrf = () => document.querySelector('meta[name="csrf"]')?.content || window.__csrf || '';

  function fetchJson(url, opts = {}) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', ...(opts.headers || {}) },
      ...opts
    }).then((r) => r.json());
  }

  function toast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = 'az-toast az-toast-' + type;
    t.textContent = msg;
    Object.assign(t.style, {
      position: 'fixed', right: '24px', bottom: '24px',
      background: type === 'success' ? '#2ecc71' : (type === 'error' ? '#e74c3c' : '#222'),
      color: '#fff', padding: '12px 18px', borderRadius: '6px',
      boxShadow: '0 8px 24px rgba(0,0,0,.18)', zIndex: 9999, fontSize: '14px',
      transform: 'translateY(20px)', opacity: '0', transition: 'all .25s ease'
    });
    document.body.appendChild(t);
    requestAnimationFrame(() => { t.style.transform = 'translateY(0)'; t.style.opacity = '1'; });
    setTimeout(() => {
      t.style.opacity = '0'; t.style.transform = 'translateY(20px)';
      setTimeout(() => t.remove(), 250);
    }, 2200);
  }

  /* Top-bar switchers */
  window.toggleSwitcher = function (id, ev) {
    if (ev) ev.stopPropagation();
    $$('.topbar-switcher').forEach((el) => { if (el.id !== id) el.classList.remove('open'); });
    document.getElementById(id)?.classList.toggle('open');
  };
  document.addEventListener('click', () => $$('.topbar-switcher.open').forEach((el) => el.classList.remove('open')));

  /* Categories mega-menu */
  window.toggleCatMenu = function (ev) {
    if (ev) ev.stopPropagation();
    $('#cat-toggle')?.classList.toggle('open');
  };
  document.addEventListener('click', () => $('#cat-toggle')?.classList.remove('open'));

  /* Cart add */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-add-cart]');
    if (!btn) return;
    e.preventDefault();
    const partId = btn.dataset.addCart;
    const qty    = btn.dataset.qty || ($('input[name="quantity"]')?.value || 1);
    btn.disabled = true;
    try {
      const res = await fetchJson('/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'add', part_id: partId, quantity: qty, csrf_token: csrf() })
      });
      if (res.success) {
        toast(res.message || 'Товар добавлен в корзину');
        const badge = $('#cart-badge');
        if (badge) badge.textContent = res.cart_count;
      } else {
        toast(res.message || 'Ошибка', 'error');
      }
    } catch { toast('Ошибка сети', 'error'); }
    finally { btn.disabled = false; }
  });

  /* Wishlist toggle */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-wishlist]');
    if (!btn) return;
    e.preventDefault();
    const partId = btn.dataset.wishlist;
    btn.disabled = true;
    try {
      const res = await fetchJson('/api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'toggle', part_id: partId, csrf_token: csrf() })
      });
      if (res.success) {
        btn.classList.toggle('active', res.in_wishlist);
        toast(res.message);
      } else {
        toast(res.message || 'Войдите в аккаунт', 'error');
      }
    } catch { toast('Ошибка сети', 'error'); }
    finally { btn.disabled = false; }
  });

  /* Compare toggle */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-compare]');
    if (!btn) return;
    e.preventDefault();
    const partId = btn.dataset.compare;
    btn.disabled = true;
    try {
      const res = await fetchJson('/api/compare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'toggle', part_id: partId, csrf_token: csrf() })
      });
      if (res.success) {
        btn.classList.toggle('active', res.in_compare);
        toast(res.message);
      } else { toast(res.message || 'Ошибка', 'error'); }
    } catch { toast('Ошибка сети', 'error'); }
    finally { btn.disabled = false; }
  });

  /* Quantity controls */
  document.addEventListener('click', (e) => {
    const inc = e.target.closest('[data-qty-plus]');
    const dec = e.target.closest('[data-qty-minus]');
    if (!inc && !dec) return;
    const wrap = (inc || dec).closest('.qty-input');
    const inp  = wrap?.querySelector('input');
    if (!inp) return;
    const v = parseInt(inp.value, 10) || 1;
    inp.value = inc ? v + 1 : Math.max(1, v - 1);
    inp.dispatchEvent(new Event('change', { bubbles: true }));
  });

  /* Product tabs */
  document.addEventListener('click', (e) => {
    const t = e.target.closest('.tab-btn');
    if (!t) return;
    const tab = t.dataset.tab;
    const root = t.closest('.product-tabs') || document;
    $$('.tab-btn', root).forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
    $$('.tab-content', root).forEach((c) => c.classList.toggle('active', c.id === 'tab-' + tab));
  });

  /* Gallery thumbs */
  document.addEventListener('click', (e) => {
    const th = e.target.closest('.gallery-thumb');
    if (!th) return;
    const main = $('.gallery-main img');
    if (!main) return;
    main.src = th.dataset.full || th.querySelector('img')?.src;
    $$('.gallery-thumb').forEach((x) => x.classList.toggle('active', x === th));
  });

  /* VIN finder cascading */
  const makeSel = $('#vin-make');
  const modelSel = $('#vin-model');
  if (makeSel && modelSel) {
    makeSel.addEventListener('change', async () => {
      modelSel.innerHTML = '<option>…</option>';
      const makeId = makeSel.value;
      if (!makeId) { modelSel.innerHTML = '<option value="">— Модель —</option>'; return; }
      try {
        const data = await fetchJson('/api/car_models.php?make_id=' + encodeURIComponent(makeId));
        modelSel.innerHTML = '<option value="">— Модель —</option>' + data.models.map(
          (m) => `<option value="${m.id}">${m.name} (${m.year_from}–${m.year_to})</option>`
        ).join('');
      } catch { modelSel.innerHTML = '<option value="">Ошибка загрузки</option>'; }
    });
  }

  /* Newsletter */
  $$('form[action*="newsletter.php"]').forEach((f) => {
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        const res = await fetch(f.action, { method: 'POST', body: new FormData(f) }).then((r) => r.json());
        toast(res.message || (res.success ? 'Подписка оформлена' : 'Ошибка'), res.success ? 'success' : 'error');
        if (res.success) f.reset();
      } catch { toast('Ошибка сети', 'error'); }
    });
  });

  /* Modals */
  window.openModal  = (id) => document.getElementById(id)?.classList.add('open');
  window.closeModal = (id) => document.getElementById(id)?.classList.remove('open');
  document.addEventListener('click', (e) => {
    if (e.target.classList?.contains('modal-overlay')) e.target.classList.remove('open');
  });

  /* Image upload preview (admin) */
  document.addEventListener('change', (e) => {
    const inp = e.target;
    if (!inp.matches('.image-uploader input[type=file]')) return;
    const wrap = inp.closest('.image-uploader');
    if (!wrap) return;
    const text = wrap.querySelector('.upload-text');
    if (text && inp.files.length) {
      text.textContent = inp.files.length + ' файл(ов) выбрано';
    }
  });
})();
