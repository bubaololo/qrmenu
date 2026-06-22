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
    // One chosen option id per variation axis: { [groupId]: optionId }.
    selections: {},
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
      // img.src is the thumb (the full size lives only in srcset);
      // data-full carries the 800w original for the bottom sheet.
      image_url: img ? (img.dataset.full || img.src) : null,
      thumb_url: img ? img.src : null,
      starred: article.dataset.starred === '1',
      description: extras.description || null,
      price: typeof extras.price === 'number' ? extras.price : 0,
      orderable: extras.orderable !== false,
      // modifierGroups: unified tree. A `replace` group is the old variation
      // (option price ABSOLUTE, replaces base); an `add` group is the old
      // add-on (option price DELTA). See menu-core/types.ts.
      modifierGroups: extras.modifierGroups || [],
    };
  },

  _replaceGroups(item) {
    return (item.modifierGroups || []).filter(g => g.pricing_mode === 'replace');
  },

  _addGroups(item) {
    return (item.modifierGroups || []).filter(g => g.pricing_mode === 'add');
  },

  // Preselect one option per REQUIRED single-select `replace` group: the
  // is_default option, else the first. Mirrors menu-core's defaultSelections.
  // Selections shape: { [groupId]: optionId } (single pick) for replace groups.
  _defaultSelections(item) {
    const selections = {};
    this._replaceGroups(item).forEach(g => {
      if (!g.options || !g.options.length) return;
      if (g.selection_type !== 'single' || !g.required) return;
      const def = g.options.find(o => o.is_default) || g.options[0];
      selections[g.id] = def.id;
    });
    return selections;
  },

  _selectedOption(group, selections) {
    if (!group.options || !group.options.length) return null;
    const id = selections ? selections[group.id] : undefined;
    if (id == null) return null;
    return group.options.find(o => o.id === id) || null;
  },

  // Names of the chosen `replace` options — the order's headline configuration.
  _getSelectionNames(item, selections) {
    return this._replaceGroups(item)
      .map(g => this._selectedOption(g, selections))
      .filter(Boolean)
      .map(o => o.name)
      .join(', ');
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

  // ---- Category chip + sheet ----

  updateActiveTab() {
    const label = document.getElementById('cat-chip-label');
    let activeName = null;
    document.querySelectorAll('.cat-option').forEach(opt => {
      const cat = opt.dataset.cat;
      const isActive = (this.activeCategory === null && cat === 'all')
        || (this.activeCategory !== null && cat === String(this.activeCategory));
      opt.classList.toggle('cat-option-active', isActive);
      if (isActive) activeName = opt.textContent.trim();
    });
    if (label && activeName) label.textContent = activeName;
  },

  openCatSheet() {
    const sheet = document.getElementById('cat-sheet');
    if (!sheet) return;
    document.getElementById('overlay').classList.add('visible');
    sheet.classList.add('visible');
    document.body.style.overflow = 'hidden';
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

    // One passive, rAF-coalesced scroll handler driving two things: reset the
    // active tab near the top, and auto-hide the header (hide on scroll down,
    // reveal on scroll up). The frame callback only reads window.scrollY (no
    // layout-forcing reads) and toggles a single class — cheap on the main
    // thread; the actual slide is a GPU transform.
    const header = document.querySelector('.topbar-sticky');
    const SHOW_AT = 80;   // always visible near the top of the page
    const DEADBAND = 8;   // ignore sub-pixel jitter / trackpad noise
    let lastY = window.scrollY;
    let ticking = false;

    const onScrollFrame = () => {
      ticking = false;
      const y = window.scrollY < 0 ? 0 : window.scrollY;

      if (y < 100) {
        this.activeCategory = null;
        this.updateActiveTab();
      }

      // Never hide while the search field is expanded inside the bar.
      if (header && !header.classList.contains('topbar-searching')) {
        if (y <= SHOW_AT) {
          header.classList.remove('topbar-hidden');
          lastY = y;
        } else {
          const dy = y - lastY;
          if (dy > DEADBAND) {
            header.classList.add('topbar-hidden');
            lastY = y;
          } else if (dy < -DEADBAND) {
            header.classList.remove('topbar-hidden');
            lastY = y;
          }
        }
      }
    };

    this._scrollHandler = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(onScrollFrame);
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
    this._sheet.selections = cartEntry && cartEntry.selections
      ? { ...cartEntry.selections }
      : this._defaultSelections(item);
    this._sheet.qty = cartEntry ? cartEntry.qty : 1;
    // Flat list of selected atomic add-on ids.
    this._sheet.addons = cartEntry && Array.isArray(cartEntry.addons)
      ? [...cartEntry.addons]
      : [];

    const unitPrice = this._previewUnitPrice(item, this._sheet.selections, this._sheet.addons);
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
    if (item.starred) {
      fragment.querySelector('.sheet-badges').hidden = false;
    }
    const desc = item.description;
    if (desc && desc.trim()) {
      const descEl = fragment.querySelector('.sheet-desc');
      descEl.textContent = desc;
      descEl.hidden = false;
    }

    const replaceGroups = this._replaceGroups(item);
    if (replaceGroups.length) {
      // `replace` groups render as single-select chip rows (radio per group).
      // Each option's price is ABSOLUTE, so it's shown plainly (no +delta).
      const variantsBlock = fragment.querySelector('.sheet-variants');
      const labelTpl = variantsBlock.querySelector('.sheet-variants-label');
      const chipsTpl = variantsBlock.querySelector('.variant-chips');
      const chipTpl = document.getElementById('tpl-variant-chip');
      variantsBlock.replaceChildren();

      replaceGroups.forEach(group => {
        if (!group.options || !group.options.length) return;
        const hasDifferentPrices = new Set(group.options.map(o => o.price)).size > 1;

        const label = labelTpl.cloneNode(true);
        label.textContent = group.name || labelTpl.textContent;
        variantsBlock.appendChild(label);

        const chipsContainer = chipsTpl.cloneNode(false);
        group.options.forEach(opt => {
          const chip = chipTpl.content.firstElementChild.cloneNode(true);
          const suffix = hasDifferentPrices ? ' \u00B7 ' + this.formatPrice(opt.price) : '';
          chip.textContent = opt.name + suffix;
          chip.dataset.groupId = group.id;
          chip.dataset.optionId = opt.id;
          if (this._sheet.selections[group.id] === opt.id) {
            chip.classList.add('variant-chip-active');
          }
          chipsContainer.appendChild(chip);
        });
        variantsBlock.appendChild(chipsContainer);
      });

      variantsBlock.hidden = false;
    }

    const addGroups = this._addGroups(item);
    if (addGroups.length) {
      // `add` groups render as optional multi-select blocks: each option is an
      // independent checkbox, picked 0..N, adding its delta price.
      const optionsContainer = fragment.querySelector('.sheet-options-container');
      const groupTpl = document.getElementById('tpl-option-group');
      const choiceTpl = document.getElementById('tpl-option-choice');

      addGroups.forEach(group => {
        if (!group.options || !group.options.length) return;
        const grp = groupTpl.content.firstElementChild.cloneNode(true);
        grp.dataset.optionGroup = group.id;

        grp.querySelector('.option-group-name').textContent = group.name || this.t('addons');

        const tag = grp.querySelector('.option-tag');
        // A required add group ("pick exactly one, free") shows as required;
        // anything optional (selection_min === 0) shows as optional.
        if (group.required) {
          tag.textContent = this.t('required');
          tag.classList.add('option-tag-required');
        } else {
          tag.textContent = this.t('optional');
          tag.classList.add('option-tag-optional');
        }

        // For size-driven groups the per-option surcharge depends on the chosen
        // driver (size) option; show the delta for whichever driver option is
        // selected right now (falls back to the flat price when none / no row).
        const driverOptionId = this._driverChoiceId(group, item, this._sheet.selections);
        // Single-select add groups behave like radios (replace on tap); multi
        // groups are independent checkboxes.
        const isSingle = group.selection_type === 'single';
        const choicesEl = grp.querySelector('.option-choices');
        group.options.forEach(opt => {
          const chc = choiceTpl.content.firstElementChild.cloneNode(true);
          chc.dataset.choiceId = opt.id;
          if (this._sheet.addons.includes(opt.id)) {
            chc.classList.add('option-choice-selected');
          }
          chc.querySelector('.option-choice-check').classList.add(isSingle ? 'option-radio' : 'option-checkbox');
          chc.querySelector('.option-choice-name').textContent = opt.name;
          const delta = this._addOptionDelta(opt, driverOptionId);
          if (delta > 0) {
            const priceEl = chc.querySelector('.option-choice-price');
            priceEl.textContent = '+' + this.formatPrice(delta);
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

  // Delta for one chosen `add` option given the active driver option (if the
  // group is size-driven). Mirrors menu-core/pricing.ts `optionDelta`.
  _addOptionDelta(option, driverOptionId) {
    if (driverOptionId != null && option.prices && option.prices.length) {
      const row = option.prices.find(p => p.driver_option_id === driverOptionId);
      if (row) return row.price || 0;
    }
    return option.price || 0;
  },

  // The chosen option id of an `add` group's price-driver group, or undefined.
  // The driver is a single-select (replace) group, tracked in `selections`.
  // Mirrors menu-core/pricing.ts `driverChoiceId`.
  _driverChoiceId(group, item, selections) {
    if (group.price_driver_group_id == null) return undefined;
    const driverGroup = (item.modifierGroups || []).find(
      g => g.id === group.price_driver_group_id
    );
    if (!driverGroup) return undefined;
    const opt = this._selectedOption(driverGroup, selections);
    return opt ? opt.id : undefined;
  },

  // All `add`-group options selected across the item, by id. When a group is
  // size-driven, each option's delta is its per-driver-option price.
  _getAddonsExtra(item, addonIds, selections) {
    if (!addonIds || !addonIds.length) return 0;
    let sum = 0;
    this._addGroups(item).forEach(g => {
      const driverOptionId = this._driverChoiceId(g, item, selections);
      (g.options || []).forEach(o => {
        if (addonIds.includes(o.id)) sum += this._addOptionDelta(o, driverOptionId);
      });
    });
    return sum;
  },

  /**
   * Unit price preview. MUST stay in sync with
   * frontend/src/lib/menu-core/pricing.ts `previewUnitPrice`. The single source
   * of truth for this rule lives in menu-core; this is a deliberate, isolated
   * replica because the guest bundle is built by a separate Vite/Laravel
   * pipeline that does not import the TS frontend module. If you change the
   * pricing rule there, change it here too (and vice-versa).
   *
   *   unit = ( the single `replace` group's chosen option price, falling back to
   *            item.price when that option price is null or no replace group is
   *            chosen )
   *        + Σ over every chosen `add` option of ( delta * qty )          // qty=1 in phase 1
   *
   * where `delta` is the option's flat `price`, EXCEPT for an `add` group with a
   * `price_driver_group_id`: then it's the option's `prices` row matching the
   * chosen option of the driver group (falling back to the flat `price` when no
   * driver option is chosen or no matching row exists). Driver-aware resolution
   * mirrors menu-core/pricing.ts `optionDelta`/`driverChoiceId`.
   */
  _previewUnitPrice(item, selections, addonIds) {
    let base = item.price;
    this._replaceGroups(item).forEach(group => {
      const opt = this._selectedOption(group, selections);
      if (opt && opt.price != null) base = opt.price;
    });
    return base + this._getAddonsExtra(item, addonIds, selections);
  },

  // Back-compat alias retained for any external callers.
  _computeUnitPrice(item, selections, addonIds) {
    return this._previewUnitPrice(item, selections, addonIds);
  },

  _updateSheetPrice() {
    const item = this._findItem(this._sheet.itemId);
    if (!item) return;

    const unitPrice = this._previewUnitPrice(item, this._sheet.selections, this._sheet.addons);
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
    // `replace` groups default to an option, so they're always satisfied. An
    // `add` group can be required (e.g. "pick exactly one, free choice") and a
    // capped one (selection_max) must not be exceeded — both gate the button.
    const item = this._findItem(this._sheet.itemId);
    // A non-orderable dish can be viewed but not added — hide the order button.
    const orderable = !item || item.orderable !== false;
    let valid = true;
    if (item && orderable) {
      this._addGroups(item).forEach(group => {
        const ids = (group.options || []).map(o => o.id);
        const count = (this._sheet.addons || []).filter(id => ids.includes(id)).length;
        // selection_min is authoritative (matches the server validator); the
        // `required` flag is for display only.
        const min = group.selection_min || 0;
        if (count < min) valid = false;
        if (group.selection_max != null && count > group.selection_max) valid = false;
      });
    }
    const btn = document.querySelector('.add-to-cart-btn');
    if (btn) {
      btn.hidden = !orderable;
      btn.disabled = !valid || !orderable;
      btn.classList.toggle('add-to-cart-btn-disabled', btn.disabled);
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

  // Flatten every option across the item's `add` groups (the old add-ons).
  _allAddOptions(item) {
    const out = [];
    this._addGroups(item).forEach(g => (g.options || []).forEach(o => out.push(o)));
    return out;
  },

  _getCartOptionNames(item, addonIds) {
    if (!addonIds || !addonIds.length) return '';
    return this._allAddOptions(item)
      .filter(o => addonIds.includes(o.id))
      .map(o => o.name)
      .join(', ');
  },

  _getWaiterOptionHtml(item, addonIds) {
    if (!addonIds || !addonIds.length) return '';
    const chosen = this._allAddOptions(item).filter(o => addonIds.includes(o.id));
    if (!chosen.length) return '';
    const tags = chosen.map(a => {
      const priceTag = a.price ? ' +' + this.formatPrice(a.price) : '';
      return '<span class="waiter-opt-tag">+ ' + a.name + priceTag + '</span>';
    }).join('');
    return '<div class="waiter-opts"><div class="waiter-opt-group">' + tags + '</div></div>';
  },

  // Stable key for a selections map so identical picks dedup in the cart.
  _selectionsKey(selections) {
    return JSON.stringify(
      Object.keys(selections || {}).sort().map(k => [k, selections[k]]),
    );
  },

  addToCart(itemId, selections, qty, selectedAddons) {
    const item = this._findItem(itemId);
    if (!item) return;

    const addons = Array.isArray(selectedAddons) ? selectedAddons : [];
    const sel = selections ? { ...selections } : {};
    const unitPrice = this._previewUnitPrice(item, sel, addons);

    const addonKey = JSON.stringify([...addons].sort());
    const selKey = this._selectionsKey(sel);
    const existing = this.cart.find(c =>
      c.itemId === itemId &&
      this._selectionsKey(c.selections) === selKey &&
      JSON.stringify([...(c.addons || [])].sort()) === addonKey
    );
    if (existing) {
      existing.qty += qty;
    } else {
      this.cart.push({ itemId, selections: sel, qty, unitPrice, addons });
    }

    this.updateCartFab();
  },

  _updateCartEntry(index) {
    const entry = this.cart[index];
    if (!entry) return;
    const item = this._findItem(entry.itemId);
    if (!item) return;

    const addons = Array.isArray(this._sheet.addons) ? this._sheet.addons : [];
    entry.selections = { ...this._sheet.selections };
    entry.qty = this._sheet.qty;
    entry.unitPrice = this._previewUnitPrice(item, entry.selections, addons);
    entry.addons = addons;

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
      if (item.modifierGroups && item.modifierGroups.length) {
        info.classList.add('cart-item-editable');
      }

      node.querySelector('.cart-item-name').textContent = item.name;

      const variantName = this._getSelectionNames(item, entry.selections);
      if (variantName) {
        const variantEl = node.querySelector('.cart-item-variant--variant');
        variantEl.textContent = variantName;
        variantEl.hidden = false;
      }

      const optionNames = this._getCartOptionNames(item, entry.addons);
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
   * Each cart item emits a flat `selections` list:
   *   { group_id, option_id, qty }
   * built from the chosen `replace`-group picks (one per group) and the
   * selected `add`-group option ids. We resolve each option's owning group via
   * the embedded modifier_groups tree. Phase 1: no children, qty defaults to 1.
   * Mirrors menu-core/pricing.ts `buildOrderPayloadSelections`.
   */
  _buildApiOrderPayload() {
    const cfg = window.__CONFIG__ || {};
    return {
      restaurant_uniqid: cfg.restaurantUniqid,
      table_uniqid: cfg.tableUniqid || null,
      items: this.cart.map(e => {
        const item = this._findItem(e.itemId);
        const selections = [];

        // Replace-group picks: { [groupId]: optionId }.
        const picks = e.selections || {};
        Object.keys(picks).forEach(groupId => {
          const optionId = picks[groupId];
          if (optionId == null) return;
          selections.push({
            group_id: Number(groupId),
            option_id: Number(optionId),
            qty: 1,
          });
        });

        // Add-group option ids: resolve each to its owning group.
        const addonIds = Array.isArray(e.addons) ? e.addons : [];
        if (addonIds.length && item) {
          this._addGroups(item).forEach(g => {
            (g.options || []).forEach(o => {
              if (addonIds.includes(o.id)) {
                selections.push({
                  group_id: Number(g.id),
                  option_id: Number(o.id),
                  qty: 1,
                });
              }
            });
          });
        }

        return {
          menu_item_id: e.itemId,
          quantity: e.qty,
          selections,
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
      const variantName = this._getSelectionNames(item, entry.selections);
      const optHtml = this._getWaiterOptionHtml(item, entry.addons);
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
    const catSheet = document.getElementById('cat-sheet');
    if (catSheet) catSheet.classList.remove('visible');
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

      // Category chip -> open category sheet
      if (e.target.closest('#cat-chip')) {
        this.openCatSheet();
        return;
      }

      // Search morph: magnifier expands the field, cross collapses + clears
      if (e.target.closest('#search-open')) {
        document.querySelector('.topbar').classList.add('topbar-searching');
        const input = document.getElementById('search-input');
        if (input) input.focus();
        return;
      }
      if (e.target.closest('#search-close')) {
        const input = document.getElementById('search-input');
        if (input && input.value) {
          input.value = '';
          this.filterBySearch('');
        }
        document.querySelector('.topbar').classList.remove('topbar-searching');
        return;
      }

      // Restaurant info panel: chevron toggles the collapsible block.
      const infoToggle = e.target.closest('#info-toggle');
      if (infoToggle) {
        const panel = document.getElementById('info-panel');
        if (panel) {
          const open = panel.classList.toggle('open');
          infoToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        return;
      }

      // Copy address / phone to clipboard.
      const copyBtn = e.target.closest('.info-copy');
      if (copyBtn) {
        e.preventDefault();
        this._copyToClipboard(copyBtn.dataset.copy, copyBtn);
        return;
      }

      // Category option (in the sheet)
      const tabBtn = e.target.closest('[data-cat]');
      if (tabBtn) {
        if (tabBtn.closest('#cat-sheet')) this._closeAllSheets();
        return this.scrollToCategory(tabBtn.dataset.cat);
      }

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

      // Variant chip selection — one pick per axis (radio within its group).
      const variantChip = e.target.closest('.variant-chip');
      if (variantChip) {
        const groupId = Number(variantChip.dataset.groupId);
        const optionId = Number(variantChip.dataset.optionId);
        this._sheet.selections[groupId] = optionId;
        const row = variantChip.closest('.variant-chips') || document;
        row.querySelectorAll('.variant-chip').forEach(c => c.classList.remove('variant-chip-active'));
        variantChip.classList.add('variant-chip-active');
        this._updateSheetPrice();
        this._updateSheetValidation();
        return;
      }

      // Add-group choice click. A `single` group acts like a radio (tapping a
      // new option replaces the group's current pick); a `multi` group toggles
      // independently (0..N), refusing extra picks once at the cap.
      const optionChoice = e.target.closest('.option-choice');
      if (optionChoice) {
        const addonId = Number(optionChoice.dataset.choiceId);
        const item = this._findItem(this._sheet.itemId);
        const group = item && this._addGroups(item).find(
          g => (g.options || []).some(o => o.id === addonId),
        );
        const isSingle = group && group.selection_type === 'single';
        const alreadyOn = this._sheet.addons.includes(addonId);

        if (alreadyOn) {
          // A required single-select group must keep one pick — ignore the tap.
          if (isSingle && group.required) return;
          this._sheet.addons = this._sheet.addons.filter(id => id !== addonId);
        } else if (isSingle) {
          // Replace whatever was chosen in this group with the tapped option.
          const ids = group.options.map(o => o.id);
          this._sheet.addons = this._sheet.addons.filter(id => !ids.includes(id));
          this._sheet.addons.push(addonId);
          const box = optionChoice.closest('.option-choices');
          if (box) {
            box.querySelectorAll('.option-choice-selected')
              .forEach(c => c.classList.remove('option-choice-selected'));
          }
        } else {
          if (group && group.selection_max != null) {
            const ids = group.options.map(o => o.id);
            const count = this._sheet.addons.filter(id => ids.includes(id)).length;
            // At the cap — ignore the extra pick rather than over-select.
            if (count >= group.selection_max) return;
          }
          this._sheet.addons.push(addonId);
        }
        optionChoice.classList.toggle(
          'option-choice-selected',
          this._sheet.addons.includes(addonId),
        );
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
          this.addToCart(this._sheet.itemId, this._sheet.selections, this._sheet.qty, this._sheet.addons);
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
          const hasConfig =
            item && item.modifierGroups && item.modifierGroups.length;
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
  /**
   * Copy text to the clipboard with a graceful fallback for non-secure
   * contexts / older browsers (Clipboard API needs HTTPS). On success flips
   * the button into a brief check state and shows the shared toast.
   */
  _copyToClipboard(text, btn) {
    if (!text) return;

    const done = () => {
      if (btn) {
        btn.classList.add('copied');
        clearTimeout(btn._copyTimer);
        btn._copyTimer = setTimeout(() => btn.classList.remove('copied'), 1400);
      }
      this._showToast(this.t('copied'));
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(() => this._fallbackCopy(text, done));
    } else {
      this._fallbackCopy(text, done);
    }
  },

  _fallbackCopy(text, done) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.top = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
      if (document.execCommand('copy')) done();
    } catch (_) { /* ignore */ }
    document.body.removeChild(ta);
  },

  /** Shared bottom toast (also used by add-to-cart). */
  _showToast(message) {
    let toast = document.getElementById('toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toast';
      toast.className = 'toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('visible');
    clearTimeout(this._toastTimer);
    this._toastTimer = setTimeout(() => toast.classList.remove('visible'), 1800);
  },

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
