/* ============================================================
   QR Menu - App (Blade integration)
   UI strings come from window.__UI__, config from window.__CONFIG__.
   Per-item modal extras (variants/options/full description) are read
   from <script type="application/json" class="menu-card-extras">
   embedded inside each <article data-item-id="X">. Card-visible
   fields (name, price, image) are read from the DOM itself.
   HTML is rendered server-side by Blade.
   JS handles: cart, bottom sheet, search/filter, theme, swipe, order submit.
   ============================================================ */

const App = {
  activeCategory: null,
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
    const formatted = price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return formatted + (window.__CONFIG__ || {}).currency;
  },

  _findItem(id) {
    const article = document.querySelector('[data-item-id="' + id + '"]');
    if (!article) return null;

    const extrasEl = article.querySelector('.menu-card-extras');
    const extras = extrasEl ? JSON.parse(extrasEl.textContent) : {};

    const img = article.querySelector('img.menu-card__image');
    const sectionEl = article.closest('section.category-section');
    const nameEl = article.querySelector('.menu-card-name');

    return {
      id: Number(id),
      sectionId: sectionEl ? Number(sectionEl.dataset.catId) : null,
      name: nameEl ? nameEl.textContent.trim() : '',
      image_url: img ? img.src : null,
      thumb_url: img ? img.src : null,
      description: extras.description || null,
      price: typeof extras.price === 'number' ? extras.price : 0,
      orderable: extras.orderable !== false,
      variants: extras.variants,
      options: extras.options,
    };
  },

  // ---- Lifecycle ----

  init() {
    // Restore saved theme. Both sun + moon SVGs live in DOM; CSS toggles which one
    // shows by [data-theme]. JS only manages the attribute + theme-color meta.
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
      document.documentElement.setAttribute('data-theme', savedTheme);
      if (savedTheme === 'dark') {
        document.querySelector('meta[name="theme-color"]').content = '#171717';
      }
    }

    this._restoreCart();
    this.updateCartFab();
    this._setupObserver();
    this._setupDelegation();
    this._setupSwipe();

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

  // Build a one-time {id: {name, desc}} index so search doesn't re-parse each
  // card's extras JSON on every keystroke.
  _buildSearchIndex() {
    this._searchIndex = {};
    document.querySelectorAll('.menu-card').forEach(card => {
      const item = this._findItem(Number(card.dataset.itemId));
      if (!item) return;
      this._searchIndex[card.dataset.itemId] = {
        name: (item.name || '').toLowerCase(),
        desc: (item.description || '').toLowerCase(),
      };
    });
  },

  filterBySearch(query) {
    const q = (query || '').toLowerCase().trim();
    if (!this._searchIndex) this._buildSearchIndex();
    const sections = document.querySelectorAll('.category-section');
    let totalVisible = 0;

    sections.forEach(section => {
      let sectionVisible = 0;
      section.querySelectorAll('.menu-card').forEach(card => {
        const idx = this._searchIndex[card.dataset.itemId];
        const match = !q || (idx && (idx.name.includes(q) || idx.desc.includes(q)));
        card.classList.toggle('hidden-by-search', !match);
        if (match) sectionVisible++;
      });
      section.classList.toggle('hidden-by-search', sectionVisible === 0);
      totalVisible += sectionVisible;
    });

    // No-results placeholder is pre-rendered in Blade; JS only toggles `hidden`.
    const noResult = document.getElementById('no-results');
    if (noResult) noResult.hidden = !(!totalVisible && q);
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

    const unitPrice = this._computeUnitPrice(item, this._sheet.variantIndex, this._sheet.options);
    const totalPrice = unitPrice * this._sheet.qty;

    // Clone the parsed-once template \u2014 far cheaper than innerHTML string parse.
    const fragment = document.getElementById('tpl-item-sheet').content.cloneNode(true);

    const visualEl = fragment.querySelector('.sheet-visual');
    const imgEl = fragment.querySelector('.sheet-image');
    if (item.image_url) {
      imgEl.src = item.image_url;
      imgEl.hidden = false;
    } else {
      visualEl.classList.add('sheet-visual--empty');
    }

    fragment.querySelector('.sheet-title').textContent = item.name;
    const desc = item.description;
    if (desc && desc.trim()) {
      const descEl = fragment.querySelector('.sheet-desc');
      descEl.textContent = desc;
      descEl.hidden = false;
    }

    if (item.variants && item.variants.length) {
      const variantsBlock = fragment.querySelector('.sheet-variants');
      const chipsContainer = fragment.querySelector('.variant-chips');
      const chipTpl = document.getElementById('tpl-variant-chip');
      const hasDifferentPrices = new Set(item.variants.map(v => v.price)).size > 1;
      item.variants.forEach((v, i) => {
        const chip = chipTpl.content.firstElementChild.cloneNode(true);
        chip.textContent = v.name + (hasDifferentPrices ? ' \u00B7 ' + this.formatPrice(v.price) : '');
        chip.dataset.variantIndex = i;
        if (i === this._sheet.variantIndex) chip.classList.add('variant-chip-active');
        chipsContainer.appendChild(chip);
      });
      variantsBlock.hidden = false;
    }

    if (item.options && item.options.length) {
      const optionsContainer = fragment.querySelector('.sheet-options-container');
      const groupTpl = document.getElementById('tpl-option-group');
      const choiceTpl = document.getElementById('tpl-option-choice');
      item.options.forEach(group => {
        const grp = groupTpl.content.firstElementChild.cloneNode(true);
        grp.dataset.optionGroup = group.id;
        grp.dataset.optionType = group.type;
        grp.dataset.optionMax = group.max || '';
        if (group.required) grp.classList.add('sheet-option-required');

        grp.querySelector('.option-group-name').textContent = group.name;

        const tag = grp.querySelector('.option-tag');
        const maxHint = group.type === 'multiple' && group.max
          ? ' \u00B7 ' + this.t('maxChoices').replace('{n}', group.max)
          : '';
        tag.textContent = (group.required ? this.t('required') : this.t('optional')) + maxHint;
        tag.classList.add(group.required ? 'option-tag-required' : 'option-tag-optional');

        const choicesEl = grp.querySelector('.option-choices');
        const selectedIds = this._sheet.options[group.id] || [];
        group.choices.forEach(choice => {
          const chc = choiceTpl.content.firstElementChild.cloneNode(true);
          chc.dataset.optionGroup = group.id;
          chc.dataset.choiceId = choice.id;
          if (selectedIds.includes(choice.id)) chc.classList.add('option-choice-selected');

          // Inner check marker: radio for single-select, checkbox for multi-select
          chc.querySelector('.option-choice-check').classList.add(
            group.type === 'single' ? 'option-radio' : 'option-checkbox'
          );

          chc.querySelector('.option-choice-name').textContent = choice.name;
          if (choice.price > 0) {
            const priceEl = chc.querySelector('.option-choice-price');
            priceEl.textContent = '+' + this.formatPrice(choice.price);
            priceEl.hidden = false;
          }
          choicesEl.appendChild(chc);
        });
        optionsContainer.appendChild(grp);
      });
      optionsContainer.hidden = false;
    }

    const btn = fragment.querySelector('.add-to-cart-btn');
    btn.dataset.price = unitPrice;
    btn.textContent = (isEdit ? this.t('updateCart') : this.t('addToCart')) + ' \u00B7 ' + this.formatPrice(totalPrice);

    fragment.querySelector('.qty-value').textContent = this._sheet.qty;

    const content = document.getElementById('item-sheet-content');
    content.replaceChildren(fragment);

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

  _getOptionsExtra(item, opts) {
    if (!item.options || !opts) return 0;
    let extra = 0;
    item.options.forEach(group => {
      const selected = opts[group.id] || [];
      group.choices.forEach(choice => {
        if (selected.includes(choice.id)) extra += choice.price;
      });
    });
    return extra;
  },

  _computeUnitPrice(item, variantIndex, opts) {
    const basePrice = item.variants ? item.variants[variantIndex].price : item.price;
    return basePrice + this._getOptionsExtra(item, opts);
  },

  _updateSheetPrice() {
    const item = this._findItem(this._sheet.itemId);
    if (!item) return;

    const unitPrice = this._computeUnitPrice(item, this._sheet.variantIndex, this._sheet.options);
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
    let pendingRaf = null;

    const onStart = (e) => {
      sheet = e.target.closest('.bottom-sheet');
      if (!sheet || !sheet.classList.contains('visible')) return;
      const t = e.target;
      const isHandle = t.closest('.bottom-sheet-handle');
      const isImage = t.closest('.sheet-image');
      if (!isHandle && !isImage) return;
      dragging = true;
      startY = e.touches ? e.touches[0].clientY : e.clientY;
      currentY = startY;
      sheet.classList.add('dragging');
    };

    // Throttle transform writes through rAF — keeps swipe smooth on slow CPUs
    // by coalescing N touchmove events into one paint per frame.
    const onMove = (e) => {
      if (!dragging || !sheet) return;
      currentY = e.touches ? e.touches[0].clientY : e.clientY;
      if (pendingRaf) return;
      pendingRaf = requestAnimationFrame(() => {
        const dy = Math.max(0, currentY - startY);
        sheet.style.transform = 'translateY(' + dy + 'px)';
        const overlay = document.getElementById('overlay');
        if (overlay) overlay.style.opacity = Math.max(0, 1 - dy / 300);
        pendingRaf = null;
      });
    };

    const onEnd = () => {
      if (!dragging || !sheet) return;
      dragging = false;
      if (pendingRaf) {
        cancelAnimationFrame(pendingRaf);
        pendingRaf = null;
      }
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

  addToCart(itemId, variantIndex, qty, selectedOptions) {
    const item = this._findItem(itemId);
    if (!item) return;

    const opts = selectedOptions || {};
    const unitPrice = this._computeUnitPrice(item, variantIndex, opts);

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

    const opts = this._sheet.options || {};
    entry.variantIndex = this._sheet.variantIndex;
    entry.qty = this._sheet.qty;
    entry.unitPrice = this._computeUnitPrice(item, this._sheet.variantIndex, opts);
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
      fab.querySelector('.cart-fab-total').textContent = this.formatPrice(this.getCartTotal());
      fab.querySelector('.cart-fab-count').textContent = count;
      fab.classList.add('visible');
    } else {
      fab.classList.remove('visible');
    }
    this._saveCart();
  },

  openCart() {
    if (this.getCartCount() === 0) return;

    this._renderCartEditView();

    document.getElementById('overlay').classList.add('visible');
    document.getElementById('cart-sheet').classList.add('visible');
    document.body.style.overflow = 'hidden';
    this._setupCartSwipe();
    setTimeout(() => this._trapFocus(document.getElementById('cart-sheet')), 100);
  },

  _renderCartEditView() {
    const content = document.getElementById('cart-sheet-content');

    // Clone the parsed-once shell — no HTML parsing, just DOM clone + textContent updates.
    const fragment = document.getElementById('tpl-cart-shell').content.cloneNode(true);

    if (this.cart.length === 0) {
      // Empty state: drop the items container and footer, replace with simple message.
      const itemsEl = fragment.querySelector('.cart-items');
      itemsEl.classList.add('cart-empty');
      const msg = document.createElement('p');
      msg.textContent = this.t('orderEmpty');
      itemsEl.replaceChildren(msg);
      fragment.querySelector('.cart-footer').remove();
      content.replaceChildren(fragment);
      return;
    }

    const itemsContainer = fragment.querySelector('.cart-items');
    const itemTpl = document.getElementById('tpl-cart-item');

    this.cart.forEach((entry, index) => {
      const item = this._findItem(entry.itemId);
      if (!item) return;

      const node = itemTpl.content.firstElementChild.cloneNode(true);
      node.dataset.cartIndex = index;
      node.querySelector('.cart-item-inner').dataset.swipeIndex = index;

      const info = node.querySelector('.cart-item-info');
      if (item.variants || (item.options && item.options.length)) {
        info.classList.add('cart-item-editable');
      }

      node.querySelector('.cart-item-name').textContent = item.name;

      const variantName = item.variants && item.variants[entry.variantIndex]
        ? item.variants[entry.variantIndex].name
        : '';
      if (variantName) {
        const variantEl = node.querySelector('.cart-item-variant--variant');
        variantEl.textContent = variantName;
        variantEl.hidden = false;
      }

      const optionNames = this._getCartOptionNames(item, entry.options);
      if (optionNames) {
        const optionsEl = node.querySelector('.cart-item-variant--options');
        optionsEl.textContent = optionNames;
        optionsEl.hidden = false;
      }

      node.querySelectorAll('.cart-qty-btn').forEach(btn => {
        btn.dataset.cartIndex = index;
      });

      node.querySelector('.qty-value').textContent = entry.qty;
      node.querySelector('.cart-item-total').textContent = this.formatPrice(entry.unitPrice * entry.qty);

      itemsContainer.appendChild(node);
    });

    fragment.querySelector('.cart-total-value').textContent = this.formatPrice(this.getCartTotal());

    content.replaceChildren(fragment);
  },

  closeCart() {
    document.getElementById('overlay').classList.remove('visible');
    document.getElementById('cart-sheet').classList.remove('visible');
    document.body.style.overflow = 'auto';
  },

  /**
   * Build API payload matching POST /api/v1/public/orders schema.
   *
   * Variations live in their own option-group with `is_variation: true`; the
   * Blade controller flattens them into `item.variants[]` per item, indexed
   * by position. To map a chosen variant back to a `variation_option_id`, we
   * look at the source item's option-groups (kept in window.__ITEMS_RAW__ if
   * available) — but the simplified itemsJson doesn't expose option IDs for
   * variants. So we send the variant index as a hint via selected_options,
   * and rely on the server to snapshot the price from menu_items.price_value.
   * For items without variations the omission is fine.
   */
  _buildApiOrderPayload() {
    const cfg = window.__CONFIG__ || {};
    return {
      restaurant_uniqid: cfg.restaurantUniqid,
      table_uniqid: cfg.tableUniqid || null,
      items: this.cart.map(e => {
        const item = this._findItem(e.itemId);
        const groups = (item && item.options) ? item.options : [];
        const selectedOptions = [];
        if (e.options) {
          Object.keys(e.options).forEach(groupId => {
            const ids = e.options[groupId] || [];
            if (ids.length > 0) {
              selectedOptions.push({
                group_id: Number(groupId),
                option_ids: ids.map(Number),
              });
            }
          });
        }
        return {
          menu_item_id: e.itemId,
          quantity: e.qty,
          selected_options: selectedOptions.length ? selectedOptions : null,
        };
      }),
    };
  },

  async submitOrder() {
    const cfg = window.__CONFIG__ || {};
    if (!cfg.tableUniqid) {
      this._showToast(this.t('orderRequiresTable') || 'Open menu via table QR to order');
      return;
    }
    const payload = this._buildApiOrderPayload();
    const content = document.getElementById('cart-sheet-content');
    content.innerHTML = '<div class="waiter-view"><h2 class="waiter-title">' + this.t('placingOrder') + '…</h2></div>';

    try {
      const csrfToken = this._getCookie('XSRF-TOKEN');
      const response = await fetch(cfg.orderEndpoint || '/api/v1/public/orders', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        },
        body: JSON.stringify(payload),
      });
      if (!response.ok) {
        const err = await response.json().catch(() => ({ message: 'Failed' }));
        throw new Error(err.message || 'Order failed');
      }
      const json = await response.json();
      this._renderOrderConfirmation(json.data);
      this.cart = [];
      this._saveCart();
      this.updateCartFab();
    } catch (e) {
      content.innerHTML =
        '<div class="waiter-view">' +
          '<h2 class="waiter-title">' + (this.t('orderFailed') || 'Order failed') + '</h2>' +
          '<p class="order-qr-hint">' + (e && e.message ? e.message : '') + '</p>' +
          '<div class="waiter-footer">' +
            '<button class="waiter-view-back">' + this.t('back') + '</button>' +
          '</div>' +
        '</div>';
    }
  },

  _getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
  },

  _showToast(message) {
    const el = document.createElement('div');
    el.className = 'qr-toast';
    el.textContent = message;
    el.style.cssText = 'position:fixed;left:50%;bottom:1rem;transform:translateX(-50%);z-index:2000;background:rgba(20,20,20,.92);color:#fff;padding:.65rem 1rem;border-radius:999px;font-size:.85rem;';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2400);
  },

  _renderOrderConfirmation(orderData) {
    const content = document.getElementById('cart-sheet-content');
    const orderId = orderData.id;
    const items = (orderData.relationships && orderData.relationships.items)
      ? (orderData.relationships.items.data || [])
      : [];
    const itemsHtml = items.map(it => {
      const attr = it.attributes || {};
      return '<div class="waiter-item">' +
        '<span class="waiter-item-qty">' + (attr.quantity || 1) + 'x</span>' +
        '<div class="waiter-item-info">' +
          '<span class="waiter-item-name">#' + it.id + '</span>' +
        '</div>' +
        '<span class="waiter-item-price">' + this.formatPrice((attr.unit_price || 0) * (attr.quantity || 1)) + '</span>' +
      '</div>';
    }).join('');

    content.innerHTML =
      '<div class="waiter-view">' +
        '<h2 class="waiter-title">' + (this.t('orderPlaced') || 'Order placed') + '</h2>' +
        '<p class="order-qr-hint">' + (this.t('orderNumber') || 'Order') + ' #' + orderId + '</p>' +
        '<div class="order-divider"></div>' +
        '<div class="waiter-items">' + itemsHtml + '</div>' +
        '<div class="waiter-footer">' +
          '<button class="waiter-view-back">' + this.t('close') + '</button>' +
        '</div>' +
      '</div>';
  },

  showWaiterView() {
    const content = document.getElementById('cart-sheet-content');
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
        '<div class="waiter-items">' + items + '</div>' +
        '<div class="waiter-total">' +
          '<span class="waiter-total-label">' + this.t('total') + '</span>' +
          '<span class="waiter-total-value">' + this.formatPrice(this.getCartTotal()) + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="waiter-footer">' +
        '<button class="waiter-view-back">' + this.t('back') + '</button>' +
        '<button class="cart-submit-order">' + (this.t('submitOrder') || this.t('showWaiter')) + '</button>' +
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
  // Lang dropdown — lazy populate
  // ============================================================

  /**
   * Populate #lang-all-list from #tpl-lang-options on first open.
   * Until this runs, native language names live inside an inert <template>
   * — browsers don't trigger unicode-range font fetches for that content,
   * so the page's initial paint only fetches subsets needed for visible text.
   */
  _lazyInitLangDropdown() {
    const list = document.getElementById('lang-all-list');
    const tpl = document.getElementById('tpl-lang-options');
    if (!list || !tpl || list.dataset.populated) return;

    list.insertBefore(tpl.content.cloneNode(true), list.firstChild);
    list.dataset.populated = '1';

    const input = document.getElementById('lang-search-input');
    const emptyState = document.getElementById('lang-no-results');
    if (!input) return;

    const items = Array.prototype.slice.call(list.querySelectorAll('a.lang-option'));

    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      let visibleCount = 0;
      for (const a of items) {
        const hit = q === ''
          || (a.dataset.label || '').indexOf(q) !== -1
          || (a.dataset.native || '').indexOf(q) !== -1
          || (a.dataset.code || '').indexOf(q) === 0;
        a.hidden = !hit;
        if (hit) visibleCount++;
      }
      if (emptyState) emptyState.hidden = visibleCount > 0;
    });

    // Don't let the dropdown's outside-click handler eat clicks inside the input.
    input.addEventListener('click', e => e.stopPropagation());
    input.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        input.value = '';
        input.dispatchEvent(new Event('input'));
      }
    });
  },

  // ============================================================
  // Event Delegation
  // ============================================================

  _setupDelegation() {
    document.addEventListener('click', (e) => {
      // Dark mode toggle — icons live in DOM, CSS hides the inactive one.
      // JS only flips the data-theme attribute + theme-color meta + storage.
      if (e.target.closest('#theme-toggle')) {
        const html = document.documentElement;
        const nextTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', nextTheme);
        document.querySelector('meta[name="theme-color"]').content =
          nextTheme === 'dark' ? '#171717' : '#ffffff';
        localStorage.setItem('theme', nextTheme);
        return;
      }

      // Language dropdown toggle
      if (e.target.closest('#lang-toggle')) {
        const dropdown = document.getElementById('lang-switcher');
        this._lazyInitLangDropdown();
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
            this._renderCartEditView();
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

      // Submit order to API
      if (e.target.closest('.cart-submit-order')) {
        this.submitOrder();
        return;
      }

      // Waiter view back button
      if (e.target.closest('.waiter-view-back')) {
        this._renderCartEditView();
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

    // Keyboard activation for focusable cards (Enter / Space)
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const card = e.target.closest('.menu-card');
      if (!card) return;
      e.preventDefault();
      this.openBottomSheet(Number(card.dataset.itemId));
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
        this._renderCartEditView();
      }
    };
    cartItemEl.addEventListener('transitionend', onDone);
    setTimeout(onDone, 350);
  },

  /**
   * Delegated swipe handling for cart items.
   *
   * Listeners are attached ONCE to the cart-sheet container (idempotent via
   * dataset flag) instead of per-item. Re-renders of the cart list don't
   * re-bind anything — swipe just works on whatever .cart-item-inner is
   * touched. Transform writes during touchmove are throttled through
   * requestAnimationFrame to avoid jank on slow CPUs.
   */
  _setupCartSwipe() {
    const container = document.getElementById('cart-sheet-content');
    if (!container || container.dataset.swipeBound) return;
    container.dataset.swipeBound = '1';

    let target = null;
    let startX = 0;
    let currentX = 0;
    let pendingRaf = null;

    container.addEventListener('touchstart', (e) => {
      const inner = e.target.closest('.cart-item-inner');
      if (!inner) return;
      target = inner;
      startX = e.touches[0].clientX;
      currentX = startX;
      target.style.transition = 'none';
    }, { passive: true });

    container.addEventListener('touchmove', (e) => {
      if (!target) return;
      currentX = e.touches[0].clientX;
      if (pendingRaf) return;
      pendingRaf = requestAnimationFrame(() => {
        const dx = Math.min(0, currentX - startX);
        target.style.transform = 'translateX(' + dx + 'px)';
        pendingRaf = null;
      });
    }, { passive: true });

    container.addEventListener('touchend', () => {
      if (!target) return;
      if (pendingRaf) {
        cancelAnimationFrame(pendingRaf);
        pendingRaf = null;
      }
      target.style.transition = 'transform .2s ease';
      const dx = currentX - startX;
      target.style.transform = dx < -60 ? 'translateX(-72px)' : '';
      target = null;
    });
  }
};

// ---- Bootstrap ----
document.addEventListener('DOMContentLoaded', () => App.init());
