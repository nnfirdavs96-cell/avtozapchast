/**
 * АвтоЗапчасть — Main JavaScript
 */

'use strict';

/* ── Live Search ─────────────────────────────────────────────── */
(function initSearch() {
  const input    = document.getElementById('header-search');
  const dropdown = document.getElementById('search-dropdown');
  if (!input || !dropdown) return;

  let timer = null;
  let lastQuery = '';

  function formatPrice(p) {
    return parseFloat(p).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <span class="cur-sym">смн</span>';
  }

  function renderResults(results, query) {
    if (!results.length) {
      dropdown.innerHTML = `<div class="search-no-results">Ничего не найдено по запросу «${escHtml(query)}»</div>`;
    } else {
      const html = results.map(r => {
        const hl = highlightText(escHtml(r.part_number), escHtml(query));
        return `
          <a href="/catalog/part.php?id=${r.id}" class="search-result-item">
            <span class="search-result-num">${hl}</span>
            <span class="search-result-name">${escHtml(r.name)}</span>
            <span class="search-result-price">${formatPrice(r.price)}</span>
          </a>`;
      }).join('');
      dropdown.innerHTML = html;
    }
    dropdown.classList.add('open');
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function highlightText(text, query) {
    if (!query) return text;
    const re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
    return text.replace(re, '<mark class="highlight">$1</mark>');
  }

  async function doSearch(q) {
    try {
      const res  = await fetch('/api/search.php?q=' + encodeURIComponent(q));
      const data = await res.json();
      if (Array.isArray(data)) renderResults(data, q);
    } catch (e) {
      console.error('Search error:', e);
    }
  }

  input.addEventListener('input', function () {
    const q = this.value.trim();
    clearTimeout(timer);
    if (q.length < 2) {
      dropdown.classList.remove('open');
      dropdown.innerHTML = '';
      lastQuery = '';
      return;
    }
    if (q === lastQuery) return;
    lastQuery = q;
    timer = setTimeout(() => doSearch(q), 300);
  });

  // Close on outside click
  document.addEventListener('click', function (e) {
    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.remove('open');
    }
  });

  // Close on Escape
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      dropdown.classList.remove('open');
      this.blur();
    }
  });
})();


/* ── User Dropdown ───────────────────────────────────────────── */
function toggleUserDropdown() {
  const dd = document.getElementById('user-dropdown');
  if (dd) dd.classList.toggle('open');
}
document.addEventListener('click', function (e) {
  const btn = document.getElementById('user-btn');
  const dd  = document.getElementById('user-dropdown');
  if (!dd) return;
  if (btn && !btn.contains(e.target) && !dd.contains(e.target)) {
    dd.classList.remove('open');
  }
});


/* ── Mobile Menu ─────────────────────────────────────────────── */
function toggleMobileMenu() {
  const links = document.getElementById('nav-links');
  if (!links) return;
  links.style.display = links.style.display === 'flex' ? 'none' : 'flex';
  links.style.flexDirection = 'column';
}


/* ── Flash auto-dismiss ──────────────────────────────────────── */
(function flashDismiss() {
  const container = document.getElementById('flash-container');
  if (!container) return;
  setTimeout(() => {
    container.style.transition = 'opacity 0.5s';
    container.style.opacity    = '0';
    setTimeout(() => container.remove(), 500);
  }, 4000);
})();


/* ── Cart AJAX ───────────────────────────────────────────────── */
const Cart = {
  async add(partId, quantity = 1) {
    return await this._post({ action: 'add', part_id: partId, quantity });
  },
  async remove(partId) {
    return await this._post({ action: 'remove', part_id: partId });
  },
  async update(partId, quantity) {
    return await this._post({ action: 'update', part_id: partId, quantity });
  },
  async getCount() {
    try {
      const res  = await fetch('/api/cart.php');
      const data = await res.json();
      return data.count ?? 0;
    } catch { return 0; }
  },
  async _post(data) {
    try {
      const res  = await fetch('/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      return await res.json();
    } catch (e) {
      return { success: false, error: e.message };
    }
  },
  updateBadge(count) {
    let badge = document.getElementById('cart-badge');
    const cartBtn = document.querySelector('.cart-btn');
    if (!cartBtn) return;
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.id = 'cart-badge';
        badge.className = 'cart-badge';
        cartBtn.appendChild(badge);
      }
      badge.textContent = count;
    } else if (badge) {
      badge.remove();
    }
  }
};


/* ── Add to cart buttons ─────────────────────────────────────── */
document.addEventListener('click', async function (e) {
  const btn = e.target.closest('[data-add-cart]');
  if (!btn) return;
  e.preventDefault();
  const partId = btn.dataset.addCart;
  const qty    = parseInt(btn.dataset.qty || 1);
  btn.disabled = true;
  btn.textContent = '...';
  const result = await Cart.add(partId, qty);
  if (result.success) {
    btn.textContent = '✓ Добавлено';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-primary');
    Cart.updateBadge(result.cart_count);
    setTimeout(() => {
      btn.disabled = false;
      btn.textContent = 'В корзину';
      btn.classList.remove('btn-success');
      btn.classList.add('btn-primary');
    }, 2000);
  } else {
    btn.textContent = result.error || 'Ошибка';
    btn.classList.add('btn-danger');
    btn.classList.remove('btn-primary');
    setTimeout(() => {
      btn.disabled = false;
      btn.textContent = 'В корзину';
      btn.classList.remove('btn-danger');
      btn.classList.add('btn-primary');
    }, 2500);
  }
});


/* ── Cart quantity controls ──────────────────────────────────── */
document.addEventListener('click', async function (e) {
  // Quantity +
  const plus = e.target.closest('[data-qty-plus]');
  if (plus) {
    const row   = plus.closest('[data-cart-row]');
    const input = row?.querySelector('[data-qty-input]');
    if (!input) return;
    const newQty = Math.min(99, parseInt(input.value) + 1);
    input.value  = newQty;
    await updateCartRow(row, parseInt(row.dataset.cartRow), newQty);
    return;
  }
  // Quantity -
  const minus = e.target.closest('[data-qty-minus]');
  if (minus) {
    const row   = minus.closest('[data-cart-row]');
    const input = row?.querySelector('[data-qty-input]');
    if (!input) return;
    const newQty = Math.max(1, parseInt(input.value) - 1);
    input.value  = newQty;
    await updateCartRow(row, parseInt(row.dataset.cartRow), newQty);
    return;
  }
  // Remove
  const removeBtn = e.target.closest('[data-cart-remove]');
  if (removeBtn) {
    const partId = removeBtn.dataset.cartRemove;
    const row    = removeBtn.closest('tr, [data-cart-row]');
    const result = await Cart.remove(partId);
    if (result.success) {
      row?.remove();
      Cart.updateBadge(result.cart_count);
      updateCartTotal(result.cart_total);
    }
  }
});

async function updateCartRow(row, partId, qty) {
  const result = await Cart.update(partId, qty);
  if (result.success) {
    Cart.updateBadge(result.cart_count);
    updateCartTotal(result.cart_total);
    // Update row subtotal
    const sub = row?.querySelector('[data-row-subtotal]');
    if (sub && result.row_subtotal !== undefined) {
      sub.textContent = parseFloat(result.row_subtotal).toLocaleString('ru-RU', { maximumFractionDigits: 0 }) + ' ₽';
    }
  }
}

function updateCartTotal(total) {
  const el = document.getElementById('cart-total');
  if (el && total !== undefined) {
    el.textContent = parseFloat(total).toLocaleString('ru-RU', { maximumFractionDigits: 0 }) + ' ₽';
  }
}


/* ── Modal helpers ───────────────────────────────────────────── */
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.add('open');
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('open');
}
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
  const closeBtn = e.target.closest('[data-modal-close]');
  if (closeBtn) {
    const modal = closeBtn.closest('.modal-overlay');
    if (modal) modal.classList.remove('open');
  }
});


/* ── Status update dropdowns ─────────────────────────────────── */
document.addEventListener('change', async function (e) {
  const sel = e.target.closest('[data-status-update]');
  if (!sel) return;
  const orderId = sel.dataset.statusUpdate;
  const status  = sel.value;
  const csrf    = sel.dataset.csrf;
  try {
    const fd = new FormData();
    fd.append('action',     'update_status');
    fd.append('order_id',   orderId);
    fd.append('status',     status);
    fd.append('csrf_token', csrf);
    const res  = await fetch('/admin/orders.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) alert('Ошибка обновления: ' + (data.error || ''));
  } catch (e) {
    console.error(e);
  }
});


/* ── Price range slider ──────────────────────────────────────── */
(function initPriceRange() {
  const minInput = document.getElementById('price-min');
  const maxInput = document.getElementById('price-max');
  if (!minInput || !maxInput) return;
  // Just ensure min <= max on blur
  [minInput, maxInput].forEach(inp => {
    inp.addEventListener('blur', () => {
      let min = parseInt(minInput.value) || 0;
      let max = parseInt(maxInput.value) || 0;
      if (min > max && max > 0) minInput.value = max;
    });
  });
})();


/* ── Confirm delete ──────────────────────────────────────────── */
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  if (!confirm(btn.dataset.confirm || 'Вы уверены?')) {
    e.preventDefault();
    e.stopImmediatePropagation();
  }
});


/* ── Filter form auto-submit ─────────────────────────────────── */
document.addEventListener('change', function (e) {
  const el = e.target.closest('[data-auto-submit]');
  if (!el) return;
  el.closest('form')?.submit();
});
