/* ============================================================
   QR Menu - App (Blade integration)
   Data comes from window.__ITEMS__, window.__UI__, window.__CONFIG__
   HTML is rendered server-side by Blade.
   JS handles: cart, bottom sheet, search/filter, theme, swipe, QR.
   ============================================================ */

const App = {
  activeCategory: null,
  activeFilter: null,
  _searchTimer: null,
  _observer: null,

  // Cart state
  cart: [],

  // Bottom sheet state
  _sheet: {
    itemId: null,
    variantIndex: 0,
    qty: 1
  },

  // ---- Helpers ----

  t(key) {
    return (window.__UI__ || {})[key] || key;
  },

  formatPrice(price) {
    const formatted = price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return formatted + (window.__CONFIG__ || {}).currency;
  },

  _findItem(id) {
    return (window.__ITEMS__ || []).find(i => i.id === id) || null;
  },

  // ---- Lifecycle ----

  init() {
    // Restore saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
      document.documentElement.setAttribute('data-theme', savedTheme);
      if (savedTheme === 'dark') {
        document.querySelector('#theme-toggle .ui-icon').outerHTML = '<svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
        document.querySelector('meta[name="theme-color"]').content = '#0A0D0A';
      }
    }

    this._restoreCart();
    this.updateCartFab();
    this._setupObserver();
    this._setupDelegation();
    this._setupSwipe();

    // Preload QR lib after main content is ready
    (typeof requestIdleCallback !== 'undefined')
      ? requestIdleCallback(() => this._loadQRLib())
      : setTimeout(() => this._loadQRLib(), 1000);

    const hash = window.location.hash;
    if (hash && hash.startsWith('#cat-')) {
      const catId = Number(hash.replace('#cat-', ''));
      if (catId) {
        this.activeCategory = catId;
        this.updateActiveTab();
        setTimeout(() => {
          const el = document.getElementById(hash.slice(1));
          if (el) el.scrollIntoView({ block: 'start' });
        }, 100);
      }
    }
  },

  // ---- Tabs ----

  updateActiveTab() {
    const container = document.getElementById('tabs');
    if (!container) return;
    const tabs = container.querySelectorAll('.tab');
    tabs.forEach(t => {
      const cat = t.dataset.cat;
      const isActive = (this.activeCategory === null && cat === 'all')
        || (this.activeCategory !== null && cat === String(this.activeCategory));
      t.classList.toggle('tab-active', isActive);
      if (isActive) {
        const offset = t.offsetLeft - container.offsetLeft - (container.clientWidth / 2) + (t.offsetWidth / 2);
        container.scrollTo({ left: offset, behavior: 'smooth' });
      }
    });
  },

  // ---- Search Filter ----

  filterBySearch(query) {
    const q = (query || '').toLowerCase().trim();
    const filter = this.activeFilter;
    const sections = document.querySelectorAll('.category-section');
    let totalVisible = 0;

    sections.forEach(section => {
      const cards = section.querySelectorAll('.menu-card');
      let sectionVisible = 0;
      cards.forEach(card => {
        const id = Number(card.dataset.itemId);
        const item = this._findItem(id);
        if (!item) return;
        const name = (item.name || '').toLowerCase();
        const desc = (item.description || '').toLowerCase();
        const match = (!q || name.includes(q) || desc.includes(q));
        card.style.display = match ? '' : 'none';
        if (match) sectionVisible++;
      });
      section.style.display = sectionVisible ? '' : 'none';
      totalVisible += sectionVisible;
    });

    let noResult = document.getElementById('no-results');
    if (!totalVisible && q) {
      if (!noResult) {
        noResult = document.createElement('div');
        noResult.id = 'no-results';
        noResult.className = 'no-results';
        document.getElementById('menu').appendChild(noResult);
      }
      noResult.innerHTML = '<svg class="ui-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><span>' + this.t('noResults') + '</span>';
      noResult.style.display = '';
    } else if (noResult) {
      noResult.style.display = 'none';
    }
  },

  // ---- Scroll To Category ----

  scrollToCategory(categoryId) {
    if (categoryId === 'all') {
      this.activeCategory = null;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      this.activeCategory = Number(categoryId);
      const target = document.getElementById('cat-' + categoryId);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    const hash = categoryId === 'all' ? '' : '#cat-' + categoryId;
    window.history.replaceState(null, '', hash || window.location.pathname + window.location.search);
    this.updateActiveTab();
  },

  // ---- Intersection Observer for Tabs ----

  _setupObserver() {
    if (this._observer) this._observer.disconnect();
    const sections = document.querySelectorAll('.category-section');
    if (!sections.length) return;

    this._observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          this.activeCategory = Number(entry.target.dataset.catId);
          this.updateActiveTab();
        }
      });
    }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });

    sections.forEach(s => this._observer.observe(s));

    this._scrollHandler = () => {
      if (window.scrollY < 100) {
        this.activeCategory = null;
        this.updateActiveTab();
      }
    };
    window.removeEventListener('scroll', this._scrollHandler);
    window.addEventListener('scroll', this._scrollHandler, { passive: true });
  },

  // ============================================================
  // Bottom Sheet (Item Detail)
  // ============================================================

  openBottomSheet(itemId, editCartIndex) {
    const item = this._findItem(itemId);
    if (!item) return;

    const isEdit = typeof editCartIndex === 'number';
    const cartEntry = isEdit ? this.cart[editCartIndex] : null;

    this._sheet.itemId = itemId;
    this._sheet.editCartIndex = isEdit ? editCartIndex : null;
    this._sheet.variantIndex = cartEntry ? cartEntry.variantIndex : 0;
    this._sheet.qty = cartEntry ? cartEntry.qty : 1;
    this._sheet.options = {};
    if (item.options) {
      item.options.forEach(group => {
        this._sheet.options[group.id] = cartEntry && cartEntry.options && cartEntry.options[group.id]
          ? [...cartEntry.options[group.id]]
          : [];
      });
    }

    const name = item.name;
    const desc = item.description;
    const hasDesc = desc && desc.trim();
    const basePrice = item.variants
      ? item.variants[this._sheet.variantIndex].price
      : item.price;
    const optionsExtra = this._getOptionsExtra(item);
    const unitPrice = basePrice + optionsExtra;
    const totalPrice = unitPrice * this._sheet.qty;

    // Icon placeholder for sheet
    const iconHtml = (typeof FoodIcons !== 'undefined')
      ? '<div class="sheet-icon">' + FoodIcons.render('dish', 56, 'food-icon') + '</div>'
      : '<div class="sheet-icon"><svg class="food-icon" width="56" height="56"><use href="/icons/food-icons.svg#dish"/></svg></div>';

    let variantsHtml = '';
    if (item.variants && item.variants.length) {
      const hasDifferentPrices = new Set(item.variants.map(v => v.price)).size > 1;
      variantsHtml = '<div class="sheet-variants">' +
        '<p class="sheet-variants-label">' + this.t('chooseVariant') + '</p>' +
        '<div class="variant-chips">' +
        item.variants.map((v, i) =>
          '<button class="variant-chip' + (i === this._sheet.variantIndex ? ' variant-chip-active' : '') + '" data-variant-index="' + i + '">' +
          v.name + (hasDifferentPrices ? ' \u00B7 ' + this.formatPrice(v.price) : '') +
          '</button>'
        ).join('') +
        '</div></div>';
    }

    let optionsHtml = '';
    if (item.options && item.options.length) {
      optionsHtml = item.options.map(group => {
        const tag = group.required ? this.t('required') : this.t('optional');
        const tagClass = group.required ? 'option-tag-required' : 'option-tag-optional';
        const maxHint = group.type === 'multiple' && group.max
          ? ' \u00B7 ' + this.t('maxChoices').replace('{n}', group.max)
          : '';
        const selectedIds = this._sheet.options[group.id] || [];
        return '<div class="sheet-option-group' + (group.required ? ' sheet-option-required' : '') + '" data-option-group="' + group.id + '" data-option-type="' + group.type + '" data-option-max="' + (group.max || '') + '">' +
          '<div class="option-group-header">' +
            '<span class="option-group-name">' + group.name + '</span>' +
            '<span class="option-tag ' + tagClass + '">' + tag + maxHint + '</span>' +
          '</div>' +
          '<div class="option-choices">' +
          group.choices.map(choice =>
            '<label class="option-choice' + (selectedIds.includes(choice.id) ? ' option-choice-selected' : '') + '" data-option-group="' + group.id + '" data-choice-id="' + choice.id + '">' +
              '<span class="option-choice-check">' +
                (group.type === 'single' ? '<span class="option-radio"></span>' : '<span class="option-checkbox"></span>') +
              '</span>' +
              '<span class="option-choice-name">' + choice.name + '</span>' +
              (choice.price > 0 ? '<span class="option-choice-price">+' + this.formatPrice(choice.price) + '</span>' : '') +
            '</label>'
          ).join('') +
          '</div></div>';
      }).join('');
    }

    const btnLabel = isEdit ? this.t('updateCart') : this.t('addToCart');

    const content = document.getElementById('item-sheet-content');
    content.innerHTML =
      '<div class="bottom-sheet-handle"></div>' +
      '<div class="sheet-visual">' +
        '<button class="bottom-sheet-close" aria-label="' + this.t('close') + '">&times;</button>' +
        iconHtml +
      '</div>' +
      '<div class="sheet-body">' +
        '<h2 class="sheet-title">' + name + '</h2>' +
        (hasDesc ? '<p class="sheet-desc">' + desc + '</p>' : '') +
        variantsHtml +
        optionsHtml +
      '</div>' +
      '<div class="sheet-footer">' +
        '<div class="sheet-controls">' +
          '<div class="qty-control">' +
            '<button class="qty-btn qty-minus" data-delta="-1">&minus;</button>' +
            '<span class="qty-value">' + this._sheet.qty + '</span>' +
            '<button class="qty-btn qty-plus" data-delta="1">+</button>' +
          '</div>' +
          '<button class="add-to-cart-btn" data-price="' + unitPrice + '">' +
            btnLabel + ' \u00B7 ' + this.formatPrice(totalPrice) +
          '</button>' +
        '</div>' +
      '</div>';

    document.getElementById('overlay').classList.add('visible');
    document.getElementById('item-sheet').classList.add('visible');
    document.body.style.overflow = 'hidden';
    this._updateSheetValidation();
    setTimeout(() => this._trapFocus(document.getElementById('item-sheet')), 100);
  },

  closeBottomSheet() {
    document.getElementById('item-sheet').classList.remove('visible');
    const cartOpen = document.getElementById('cart-sheet').classList.contains('visible');
    if (!cartOpen) {
      document.getElementById('overlay').classList.remove('visible');
      document.body.style.overflow = 'auto';
    }
  },

  _getOptionsExtra(item) {
    if (!item.options) return 0;
    let extra = 0;
    item.options.forEach(group => {
      const selected = this._sheet.options[group.id] || [];
      group.choices.forEach(choice => {
        if (selected.includes(choice.id)) extra += choice.price;
      });
    });
    return extra;
  },

  _updateSheetPrice() {
    const item = this._findItem(this._sheet.itemId);
    if (!item) return;

    const basePrice = item.variants
      ? item.variants[this._sheet.variantIndex].price
      : item.price;
    const optionsExtra = this._getOptionsExtra(item);
    const unitPrice = basePrice + optionsExtra;
    const totalPrice = unitPrice * this._sheet.qty;

    const btn = document.querySelector('.add-to-cart-btn');
    if (btn) {
      const isEdit = typeof this._sheet.editCartIndex === 'number';
      const label = isEdit ? this.t('updateCart') : this.t('addToCart');
      btn.dataset.price = unitPrice;
      btn.textContent = label + ' \u00B7 ' + this.formatPrice(totalPrice);
    }

    const qtyEl = document.querySelector('.qty-value');
    if (qtyEl) qtyEl.textContent = this._sheet.qty;
  },

  _updateSheetValidation() {
    const item = this._findItem(this._sheet.itemId);
    if (!item || !item.options) return;
    let allValid = true;
    item.options.forEach(group => {
      const selected = this._sheet.options[group.id] || [];
      const groupEl = document.querySelector('.sheet-option-group[data-option-group="' + group.id + '"]');
      if (!groupEl) return;
      if (group.required && selected.length === 0) {
        allValid = false;
        groupEl.classList.add('sheet-option-invalid');
      } else {
        groupEl.classList.remove('sheet-option-invalid');
      }
    });
    const btn = document.querySelector('.add-to-cart-btn');
    if (btn) {
      btn.disabled = !allValid;
      btn.classList.toggle('add-to-cart-btn-disabled', !allValid);
    }
  },

  // ============================================================
  // Swipe to close
  // ============================================================

  _setupSwipe() {
    let startY = 0;
    let currentY = 0;
    let dragging = false;
    let sheet = null;

    const onStart = (e) => {
      sheet = e.target.closest('.bottom-sheet');
      if (!sheet || !sheet.classList.contains('visible')) return;
      const t = e.target;
      const isHandle = t.closest('.bottom-sheet-handle');
      const isImage = t.closest('.sheet-image') || t.closest('.sheet-icon');
      if (!isHandle && !isImage) return;
      dragging = true;
      startY = e.touches ? e.touches[0].clientY : e.clientY;
      currentY = startY;
      sheet.classList.add('dragging');
    };

    const onMove = (e) => {
      if (!dragging || !sheet) return;
      currentY = e.touches ? e.touches[0].clientY : e.clientY;
      const dy = Math.max(0, currentY - startY);
      sheet.style.transform = 'translateY(' + dy + 'px)';
      const overlay = document.getElementById('overlay');
      if (overlay) overlay.style.opacity = Math.max(0, 1 - dy / 300);
    };

    const onEnd = () => {
      if (!dragging || !sheet) return;
      dragging = false;
      sheet.classList.remove('dragging');
      sheet.style.transform = '';
      const overlay = document.getElementById('overlay');
      if (overlay) overlay.style.opacity = '';
      const dy = currentY - startY;
      if (dy > 100) {
        if (sheet.id === 'item-sheet' && document.getElementById('cart-sheet').classList.contains('visible')) {
          this.closeBottomSheet();
        } else {
          this._closeAllSheets();
        }
      }
      sheet = null;
    };

    document.addEventListener('touchstart', onStart, { passive: true });
    document.addEventListener('touchmove', onMove, { passive: true });
    document.addEventListener('touchend', onEnd);
    document.addEventListener('mousedown', onStart);
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onEnd);
  },

  // ============================================================
  // Cart
  // ============================================================

  _saveCart() {
    try {
      localStorage.setItem('cart', JSON.stringify({
        items: this.cart,
        ts: Date.now()
      }));
    } catch (_) {}
  },

  _restoreCart() {
    try {
      const raw = localStorage.getItem('cart');
      if (!raw) return;
      const { items, ts } = JSON.parse(raw);
      if (Date.now() - ts > 2 * 60 * 60 * 1000) {
        localStorage.removeItem('cart');
        return;
      }
      if (Array.isArray(items) && items.length) {
        this.cart = items.filter(c =>
          c.itemId && c.qty > 0 && this._findItem(c.itemId)
        );
      }
    } catch (_) {
      localStorage.removeItem('cart');
    }
  },

  _getCartOptionNames(item, opts) {
    if (!item.options || !opts) return '';
    const names = [];
    item.options.forEach(group => {
      const sel = opts[group.id] || [];
      group.choices.forEach(choice => {
        if (sel.includes(choice.id)) {
          names.push(choice.name);
        }
      });
    });
    return names.join(', ');
  },

  _getWaiterOptionHtml(item, opts) {
    if (!item.options || !opts) return '';
    const groups = [];
    item.options.forEach(group => {
      const sel = opts[group.id] || [];
      const chosen = group.choices.filter(c => sel.includes(c.id));
      if (!chosen.length) return;
      const tags = chosen.map(c => {
        const priceTag = c.price ? ' +' + this.formatPrice(c.price) : '';
        return '<span class="waiter-opt-tag">+ ' + c.name + priceTag + '</span>';
      }).join('');
      groups.push('<div class="waiter-opt-group"><span class="waiter-opt-label">' + group.name + ':</span>' + tags + '</div>');
    });
    return groups.length ? '<div class="waiter-opts">' + groups.join('') + '</div>' : '';
  },

  _getCartCountForItem(itemId) {
    return this.cart.filter(c => c.itemId === itemId).reduce((sum, c) => sum + c.qty, 0);
  },

  _updateCardBadges() {
    document.querySelectorAll('.menu-card-add').forEach(btn => {
      const itemId = Number(btn.dataset.quickAdd);
      const count = this._getCartCountForItem(itemId);
      const existing = btn.querySelector('.menu-card-add-count');
      if (count > 0) {
        if (existing) {
          existing.textContent = count;
        } else {
          const badge = document.createElement('span');
          badge.className = 'menu-card-add-count';
          badge.textContent = count;
          btn.appendChild(badge);
        }
      } else if (existing) {
        existing.remove();
      }
    });
  },

  addToCart(itemId, variantIndex, qty, selectedOptions) {
    const item = this._findItem(itemId);
    if (!item) return;

    const basePrice = item.variants
      ? item.variants[variantIndex].price
      : item.price;

    let optionsExtra = 0;
    const opts = selectedOptions || {};
    if (item.options) {
      item.options.forEach(group => {
        const sel = opts[group.id] || [];
        group.choices.forEach(choice => {
          if (sel.includes(choice.id)) optionsExtra += choice.price;
        });
      });
    }
    const unitPrice = basePrice + optionsExtra;

    const optKey = JSON.stringify(opts);
    const existing = this.cart.find(c =>
      c.itemId === itemId &&
      c.variantIndex === variantIndex &&
      JSON.stringify(c.options || {}) === optKey
    );
    if (existing) {
      existing.qty += qty;
    } else {
      this.cart.push({ itemId, variantIndex, qty, unitPrice, options: opts });
    }

    this.updateCartFab();
  },

  _updateCartEntry(index) {
    const entry = this.cart[index];
    if (!entry) return;
    const item = this._findItem(entry.itemId);
    if (!item) return;

    const basePrice = item.variants
      ? item.variants[this._sheet.variantIndex].price
      : item.price;

    let optionsExtra = 0;
    const opts = this._sheet.options || {};
    if (item.options) {
      item.options.forEach(group => {
        const sel = opts[group.id] || [];
        group.choices.forEach(choice => {
          if (sel.includes(choice.id)) optionsExtra += choice.price;
        });
      });
    }

    entry.variantIndex = this._sheet.variantIndex;
    entry.qty = this._sheet.qty;
    entry.unitPrice = basePrice + optionsExtra;
    entry.options = opts;

    this.updateCartFab();
  },

  _haptic(style) {
    if (!navigator.vibrate) return;
    if (style === 'light') navigator.vibrate(10);
    else if (style === 'medium') navigator.vibrate(20);
    else navigator.vibrate([15, 30, 15]);
  },

  _showAddedFeedback(itemName, sourceEl) {
    const fab = document.getElementById('cart-fab');

    if (sourceEl && fab) {
      const from = sourceEl.getBoundingClientRect();
      const to = fab.getBoundingClientRect();
      const dot = document.createElement('div');
      dot.className = 'fly-dot';
      dot.style.left = (from.left + from.width / 2 - 6) + 'px';
      dot.style.top = (from.top + from.height / 2 - 6) + 'px';
      document.body.appendChild(dot);

      requestAnimationFrame(() => {
        dot.style.transition = 'all .45s cubic-bezier(.4,0,.2,1)';
        dot.style.left = (to.left + to.width / 2 - 6) + 'px';
        dot.style.top = (to.top + to.height / 2 - 6) + 'px';
        dot.style.opacity = '0';
        dot.style.transform = 'scale(.3)';
      });
      setTimeout(() => dot.remove(), 500);
    }

    if (fab) {
      fab.classList.remove('bounce');
      void fab.offsetWidth;
      fab.classList.add('bounce');
    }

    let toast = document.getElementById('toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toast';
      toast.className = 'toast';
      document.body.appendChild(toast);
    }
    toast.textContent = this.t('added') + ': ' + itemName;
    toast.classList.add('visible');
    clearTimeout(this._toastTimer);
    this._toastTimer = setTimeout(() => toast.classList.remove('visible'), 1800);
  },

  removeFromCart(index) {
    this.cart.splice(index, 1);
    this.updateCartFab();
  },

  updateCartQty(index, delta) {
    if (!this.cart[index]) return;
    this.cart[index].qty += delta;
    if (this.cart[index].qty <= 0) {
      this.cart.splice(index, 1);
    }
    this.updateCartFab();
  },

  clearCart() {
    this.cart = [];
    this.updateCartFab();
    this.closeCart();
  },

  getCartTotal() {
    return this.cart.reduce((sum, c) => sum + c.unitPrice * c.qty, 0);
  },

  getCartCount() {
    return this.cart.reduce((sum, c) => sum + c.qty, 0);
  },

  updateCartFab() {
    const fab = document.getElementById('cart-fab');
    if (!fab) return;
    const count = this.getCartCount();
    if (count > 0) {
      fab.classList.add('visible');
      fab.innerHTML = '<span>' + this.t('cart') + '</span> <span>' + this.formatPrice(this.getCartTotal()) + ' <span class="cart-fab-count">' + count + '</span></span>';
      document.getElementById('menu').classList.add('has-cart');
    } else {
      fab.classList.remove('visible');
      fab.innerHTML = '';
      document.getElementById('menu').classList.remove('has-cart');
    }
    this._updateCardBadges();
    this._saveCart();
  },

  openCart() {
    if (this.getCartCount() === 0) return;

    const content = document.getElementById('cart-sheet-content');
    content.innerHTML = this._renderCartEditView();

    document.getElementById('overlay').classList.add('visible');
    document.getElementById('cart-sheet').classList.add('visible');
    document.body.style.overflow = 'hidden';
    this._setupCartSwipe();
    setTimeout(() => this._trapFocus(document.getElementById('cart-sheet')), 100);
  },

  _renderCartEditView() {
    if (this.cart.length === 0) {
      return '<div class="bottom-sheet-handle"></div>' +
        '<div class="cart-header">' +
          '<h2 class="cart-title">' + this.t('cart') + '</h2>' +
          '<button class="bottom-sheet-close" aria-label="' + this.t('close') + '">&times;</button>' +
        '</div>' +
        '<div class="cart-empty"><p>' + this.t('orderEmpty') + '</p></div>';
    }

    const itemsHtml = this.cart.map((entry, index) => {
      const item = this._findItem(entry.itemId);
      if (!item) return '';
      const name = item.name;
      const variantName = item.variants && item.variants[entry.variantIndex]
        ? item.variants[entry.variantIndex].name
        : '';
      const optionNames = this._getCartOptionNames(item, entry.options);
      const lineTotal = entry.unitPrice * entry.qty;

      return '<div class="cart-item" data-cart-index="' + index + '">' +
        '<div class="cart-item-delete">' + this.t('deleteItem') + '</div>' +
        '<div class="cart-item-inner" data-swipe-index="' + index + '">' +
          '<div class="cart-item-info' + ((item.variants || (item.options && item.options.length)) ? ' cart-item-editable' : '') + '">' +
            '<span class="cart-item-name">' + name + '</span>' +
            (variantName ? '<span class="cart-item-variant">' + variantName + '</span>' : '') +
            (optionNames ? '<span class="cart-item-variant">' + optionNames + '</span>' : '') +
          '</div>' +
          '<div class="cart-item-controls">' +
            '<div class="qty-control qty-control-sm">' +
              '<button class="qty-btn cart-qty-btn" data-cart-index="' + index + '" data-delta="-1">&minus;</button>' +
              '<span class="qty-value">' + entry.qty + '</span>' +
              '<button class="qty-btn cart-qty-btn" data-cart-index="' + index + '" data-delta="1">+</button>' +
            '</div>' +
            '<span class="cart-item-total">' + this.formatPrice(lineTotal) + '</span>' +
          '</div>' +
        '</div>' +
      '</div>';
    }).join('');

    return '<div class="cart-header">' +
        '<h2 class="cart-title">' + this.t('cart') + '</h2>' +
        '<button class="bottom-sheet-close" aria-label="' + this.t('close') + '">&times;</button>' +
      '</div>' +
      '<div class="cart-items">' + itemsHtml + '</div>' +
      '<div class="cart-footer">' +
        '<div class="cart-total-row">' +
          '<span class="cart-total-label">' + this.t('total') + '</span>' +
          '<span class="cart-total-value">' + this.formatPrice(this.getCartTotal()) + '</span>' +
        '</div>' +
        '<div class="cart-actions">' +
          '<button class="cart-clear">' + this.t('clearCart') + '</button>' +
          '<button class="cart-show-waiter">' + this.t('showWaiter') + '</button>' +
        '</div>' +
      '</div>';
  },

  closeCart() {
    document.getElementById('overlay').classList.remove('visible');
    document.getElementById('cart-sheet').classList.remove('visible');
    document.body.style.overflow = 'auto';
  },

  _buildOrderPayload() {
    return this.cart.map(e => {
      const base = [e.itemId, e.variantIndex, e.qty];
      if (e.options && Object.keys(e.options).some(k => e.options[k].length > 0)) {
        base.push(e.options);
      }
      return base;
    });
  },

  _qrLoaded: false,

  _loadQRLib() {
    if (this._qrLoaded || typeof qrcode !== 'undefined') {
      this._qrLoaded = true;
      return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
      s.onload = () => { this._qrLoaded = true; resolve(); };
      s.onerror = reject;
      document.head.appendChild(s);
    });
  },

  _generateQR(data) {
    if (typeof qrcode === 'undefined') return '';
    const text = JSON.stringify(data);
    const qr = qrcode(0, 'M');
    qr.addData(text);
    qr.make();
    return qr.createSvgTag({ cellSize: 4, margin: 4 });
  },

  async showWaiterView() {
    const content = document.getElementById('cart-sheet-content');
    await this._loadQRLib().catch(() => {});

    const orderPayload = this._buildOrderPayload();
    const qrSvg = this._generateQR(orderPayload);

    const items = this.cart.map(entry => {
      const item = this._findItem(entry.itemId);
      if (!item) return '';
      const name = item.name;
      const variantName = item.variants && item.variants[entry.variantIndex]
        ? item.variants[entry.variantIndex].name
        : '';
      const optHtml = this._getWaiterOptionHtml(item, entry.options);
      const lineTotal = entry.unitPrice * entry.qty;

      return '<div class="waiter-item">' +
        '<span class="waiter-item-qty">' + entry.qty + 'x</span>' +
        '<div class="waiter-item-info">' +
          '<span class="waiter-item-name">' + name + '</span>' +
          (variantName ? '<span class="waiter-item-variant">' + variantName + '</span>' : '') +
          optHtml +
        '</div>' +
        '<span class="waiter-item-price">' + this.formatPrice(lineTotal) + '</span>' +
      '</div>';
    }).join('');

    content.innerHTML =
      '<div class="waiter-view">' +
        '<h2 class="waiter-title">' + this.t('yourOrder') + '</h2>' +
        '<div class="order-qr">' + qrSvg + '</div>' +
        '<p class="order-qr-hint">' + this.t('scanOrder') + '</p>' +
        '<div class="order-divider"></div>' +
        '<div class="waiter-items">' + items + '</div>' +
        '<div class="waiter-total">' +
          '<span class="waiter-total-label">' + this.t('total') + '</span>' +
          '<span class="waiter-total-value">' + this.formatPrice(this.getCartTotal()) + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="waiter-footer">' +
        '<button class="waiter-view-back">' + this.t('back') + '</button>' +
      '</div>';
  },

  // ============================================================
  // Close all sheets
  // ============================================================

  _closeAllSheets() {
    this._releaseFocus(document.getElementById('item-sheet'));
    this._releaseFocus(document.getElementById('cart-sheet'));
    document.getElementById('overlay').classList.remove('visible');
    document.getElementById('item-sheet').classList.remove('visible');
    document.getElementById('cart-sheet').classList.remove('visible');
    document.body.style.overflow = 'auto';
  },

  _trapFocus(sheet) {
    const focusable = sheet.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    first.focus();
    sheet._focusTrap = (e) => {
      if (e.key !== 'Tab') return;
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
      if (e.key === 'Escape') this._closeAllSheets();
    };
    sheet.addEventListener('keydown', sheet._focusTrap);
  },

  _releaseFocus(sheet) {
    if (sheet._focusTrap) {
      sheet.removeEventListener('keydown', sheet._focusTrap);
      sheet._focusTrap = null;
    }
  },

  // ============================================================
  // Event Delegation
  // ============================================================

  _setupDelegation() {
    document.addEventListener('click', (e) => {
      // Dark mode toggle
      if (e.target.closest('#theme-toggle')) {
        const html = document.documentElement;
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        const sunSvg = '<svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
        const moonSvg = '<svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
        document.querySelector('#theme-toggle .ui-icon').outerHTML = isDark ? moonSvg : sunSvg;
        document.querySelector('meta[name="theme-color"]').content = isDark ? '#f8fafc' : '#0A0D0A';
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
        return;
      }

      // Language dropdown toggle
      if (e.target.closest('#lang-toggle')) {
        const dropdown = document.getElementById('lang-switcher');
        dropdown.classList.toggle('open');
        e.stopPropagation();
        return;
      }

      // Close lang dropdown on outside click
      const langDropdown = document.getElementById('lang-switcher');
      if (langDropdown && langDropdown.classList.contains('open') && !e.target.closest('.lang-dropdown')) {
        langDropdown.classList.remove('open');
      }

      // Category tabs
      const tabBtn = e.target.closest('[data-cat]');
      if (tabBtn) return this.scrollToCategory(tabBtn.dataset.cat);

      // Overlay click -> close all
      if (e.target.id === 'overlay') {
        return this._closeAllSheets();
      }

      // Close button on any bottom sheet
      const closeBtn = e.target.closest('.bottom-sheet-close');
      if (closeBtn) {
        const inItemSheet = closeBtn.closest('#item-sheet');
        if (inItemSheet && document.getElementById('cart-sheet').classList.contains('visible')) {
          return this.closeBottomSheet();
        }
        return this._closeAllSheets();
      }

      // Variant chip selection
      const variantChip = e.target.closest('.variant-chip');
      if (variantChip) {
        const idx = Number(variantChip.dataset.variantIndex);
        this._sheet.variantIndex = idx;
        document.querySelectorAll('.variant-chip').forEach(c => c.classList.remove('variant-chip-active'));
        variantChip.classList.add('variant-chip-active');
        this._updateSheetPrice();
        return;
      }

      // Option choice click
      const optionChoice = e.target.closest('.option-choice');
      if (optionChoice) {
        const groupId = Number(optionChoice.dataset.optionGroup);
        const choiceId = Number(optionChoice.dataset.choiceId);
        const groupEl = document.querySelector('.sheet-option-group[data-option-group="' + groupId + '"]');
        const type = groupEl.dataset.optionType;
        const max = groupEl.dataset.optionMax ? Number(groupEl.dataset.optionMax) : null;
        const selected = this._sheet.options[groupId] || [];

        if (type === 'single') {
          this._sheet.options[groupId] = [choiceId];
        } else {
          const idx = selected.indexOf(choiceId);
          if (idx >= 0) {
            selected.splice(idx, 1);
          } else {
            if (max && selected.length >= max) return;
            selected.push(choiceId);
          }
          this._sheet.options[groupId] = selected;
        }

        groupEl.querySelectorAll('.option-choice').forEach(el => {
          const cid = Number(el.dataset.choiceId);
          const isSelected = this._sheet.options[groupId].includes(cid);
          el.classList.toggle('option-choice-selected', isSelected);
        });
        this._updateSheetPrice();
        this._updateSheetValidation();
        return;
      }

      // Qty buttons in item bottom sheet
      const qtyBtn = e.target.closest('.qty-btn:not(.cart-qty-btn)');
      if (qtyBtn && document.getElementById('item-sheet').classList.contains('visible')) {
        const delta = Number(qtyBtn.dataset.delta);
        this._sheet.qty = Math.max(1, this._sheet.qty + delta);
        this._updateSheetPrice();
        return;
      }

      // Add to cart / Update cart button
      const addBtn = e.target.closest('.add-to-cart-btn');
      if (addBtn) {
        if (addBtn.disabled) return;
        this._haptic('medium');
        const isEdit = typeof this._sheet.editCartIndex === 'number';
        if (isEdit) {
          this._updateCartEntry(this._sheet.editCartIndex);
          this.closeBottomSheet();
          if (document.getElementById('cart-sheet').classList.contains('visible')) {
            document.getElementById('cart-sheet-content').innerHTML = this._renderCartEditView();
          }
        } else {
          this.addToCart(this._sheet.itemId, this._sheet.variantIndex, this._sheet.qty, this._sheet.options);
          const item = this._findItem(this._sheet.itemId);
          if (item) this._showAddedFeedback(item.name, addBtn);
          this.closeBottomSheet();
        }
        return;
      }

      // Cart FAB
      if (e.target.closest('#cart-fab')) {
        this.openCart();
        return;
      }

      // Cart item edit
      const cartItemInfo = e.target.closest('.cart-item-info');
      if (cartItemInfo) {
        const cartItem = cartItemInfo.closest('.cart-item');
        const index = Number(cartItem.dataset.cartIndex);
        const entry = this.cart[index];
        if (entry) {
          const item = this._findItem(entry.itemId);
          const hasConfig = (item && item.variants) || (item && item.options && item.options.length);
          if (hasConfig) {
            this.openBottomSheet(entry.itemId, index);
            return;
          }
        }
      }

      // Cart qty buttons
      const cartQtyBtn = e.target.closest('.cart-qty-btn');
      if (cartQtyBtn) {
        const index = Number(cartQtyBtn.dataset.cartIndex);
        const delta = Number(cartQtyBtn.dataset.delta);
        if (delta < 0 && this.cart[index] && this.cart[index].qty <= 1) {
          const cartItem = cartQtyBtn.closest('.cart-item');
          this._animateCartRemove(cartItem, index);
          return;
        }
        this.updateCartQty(index, delta);
        const cartItem = cartQtyBtn.closest('.cart-item');
        const qtyEl = cartItem.querySelector('.qty-value');
        const totalEl = cartItem.querySelector('.cart-item-total');
        if (qtyEl) qtyEl.textContent = this.cart[index].qty;
        if (totalEl) totalEl.textContent = this.formatPrice(this.cart[index].unitPrice * this.cart[index].qty);
        const totalValue = document.querySelector('.cart-total-value');
        if (totalValue) totalValue.textContent = this.formatPrice(this.getCartTotal());
        return;
      }

      // Clear cart
      if (e.target.closest('.cart-clear')) {
        this.clearCart();
        return;
      }

      // Show waiter view
      if (e.target.closest('.cart-show-waiter')) {
        this.showWaiterView();
        return;
      }

      // Waiter view back button
      if (e.target.closest('.waiter-view-back')) {
        const content = document.getElementById('cart-sheet-content');
        content.innerHTML = this._renderCartEditView();
        return;
      }

      // Quick add button on card
      const quickAdd = e.target.closest('[data-quick-add]');
      if (quickAdd) {
        e.stopPropagation();
        const itemId = Number(quickAdd.dataset.quickAdd);
        const hasVariants = quickAdd.dataset.hasVariants === 'true';
        const hasOptions = quickAdd.dataset.hasOptions === 'true';
        if (hasVariants || hasOptions) {
          this.openBottomSheet(itemId);
        } else {
          this._haptic('medium');
          this.addToCart(itemId, 0, 1);
          const item = this._findItem(itemId);
          if (item) this._showAddedFeedback(item.name, quickAdd);
        }
        return;
      }

      // Menu card click -> open bottom sheet
      const card = e.target.closest('.menu-card');
      if (card) {
        const itemId = Number(card.dataset.itemId);
        this.openBottomSheet(itemId);
        return;
      }
    });

    // Cart item delete
    document.addEventListener('click', (e2) => {
      const del = e2.target.closest('.cart-item-delete');
      if (del) {
        const cartItem = del.closest('.cart-item');
        const index = Number(cartItem.dataset.cartIndex);
        this._animateCartRemove(cartItem, index);
      }
    }, true);

    // Search input handler
    document.addEventListener('input', (e) => {
      if (e.target.id !== 'search-input') return;
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => {
        this.filterBySearch(e.target.value);
      }, 300);
    });
  },

  _animateCartRemove(cartItemEl, index) {
    if (cartItemEl.classList.contains('removing')) return;
    const h = cartItemEl.offsetHeight;
    cartItemEl.style.height = h + 'px';
    void cartItemEl.offsetHeight;
    cartItemEl.classList.add('removing');

    let done = false;
    const onDone = () => {
      if (done) return;
      done = true;
      cartItemEl.removeEventListener('transitionend', onDone);
      this.removeFromCart(index);
      if (this.cart.length === 0) {
        this.closeCart();
      } else {
        const content = document.getElementById('cart-sheet-content');
        content.innerHTML = this._renderCartEditView();
        this._setupCartSwipe();
      }
    };
    cartItemEl.addEventListener('transitionend', onDone);
    setTimeout(onDone, 350);
  },

  _setupCartSwipe() {
    const items = document.querySelectorAll('.cart-item-inner[data-swipe-index]');
    items.forEach(inner => {
      let startX = 0, currentX = 0, swiping = false;
      inner.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        currentX = startX;
        swiping = true;
        inner.style.transition = 'none';
      }, { passive: true });
      inner.addEventListener('touchmove', (e) => {
        if (!swiping) return;
        currentX = e.touches[0].clientX;
        const dx = Math.min(0, currentX - startX);
        inner.style.transform = 'translateX(' + dx + 'px)';
      }, { passive: true });
      inner.addEventListener('touchend', () => {
        if (!swiping) return;
        swiping = false;
        inner.style.transition = 'transform .2s ease';
        const dx = currentX - startX;
        if (dx < -60) {
          inner.style.transform = 'translateX(-72px)';
        } else {
          inner.style.transform = '';
        }
      });
    });
  }
};

// ---- Bootstrap ----
document.addEventListener('DOMContentLoaded', () => App.init());
