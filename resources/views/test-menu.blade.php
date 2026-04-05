<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Analyzer</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f5f5f5; padding: 2rem; color: #222; }
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; }
        h2 { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #555; text-transform: uppercase; letter-spacing: .05em; }

        /* Auth */
        #auth-section { max-width: 360px; }
        .field { margin-bottom: .75rem; }
        .field label { display: block; font-size: .875rem; margin-bottom: .35rem; color: #444; }
        .field input { width: 100%; padding: .55rem .9rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .btn { padding: .6rem 1.5rem; background: #1a56db; color: #fff; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #1e429f; }
        .btn:disabled { background: #93c5fd; cursor: not-allowed; }
        .btn-sm { padding: .35rem 1rem; font-size: .875rem; }
        .btn-gray { background: #6b7280; }
        .btn-gray:hover { background: #4b5563; }
        .error-msg { color: #c00; font-size: .875rem; margin-top: .5rem; }

        /* Upload */
        #upload-section { display: none; }
        .drop-zone {
            border: 2px dashed #ccc; border-radius: 10px; padding: 2rem; text-align: center;
            cursor: pointer; transition: border-color .2s, background .2s; margin-bottom: 1rem;
        }
        .drop-zone.drag-over { border-color: #1a56db; background: #eff6ff; }
        .drop-zone p { color: #888; font-size: .95rem; }
        .drop-zone input[type="file"] { display: none; }
        .previews { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
        .preview-item { position: relative; width: 100px; height: 100px; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
        .preview-item .remove {
            position: absolute; top: -6px; right: -6px; background: #ef4444; color: #fff;
            border: none; border-radius: 50%; width: 20px; height: 20px; font-size: .75rem;
            cursor: pointer; line-height: 20px; text-align: center; padding: 0;
        }
        .actions { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }

        /* Spinner */
        .spinner { display: none; align-items: center; gap: .6rem; color: #555; font-size: .95rem; }
        .spinner.active { display: flex; }
        .spinner svg { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Results */
        #results-section { display: none; }
        .results-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .card { background: #fff; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .card .category { font-size: .75rem; font-weight: 600; text-transform: uppercase; color: #888; margin-bottom: .4rem; letter-spacing: .05em; }
        .card .name { font-size: 1.05rem; font-weight: 700; color: #111; margin-bottom: .2rem; }
        .card .name-original { font-size: .85rem; color: #666; margin-bottom: .6rem; }
        .card .description { font-size: .875rem; color: #444; line-height: 1.5; margin-bottom: .75rem; }
        .card .price { display: inline-block; font-weight: 700; color: #1a56db; background: #eff6ff; padding: .2rem .7rem; border-radius: 99px; font-size: .875rem; }
        .restaurant-card { grid-column: 1 / -1; border-left: 4px solid #1a56db; }
        .section-heading { grid-column: 1 / -1; margin-top: .75rem; padding-bottom: .35rem; border-bottom: 1px solid #e5e7eb; }
        .section-heading:first-of-type { margin-top: 0; }
        .section-heading h3 { font-size: .95rem; font-weight: 700; color: #374151; }
        .muted { font-size: .85rem; color: #6b7280; margin-top: .15rem; }
        .variations { margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed #e5e7eb; font-size: .8rem; color: #4b5563; }
        .variation-line { margin-top: .25rem; }
        details summary { cursor: pointer; color: #888; font-size: .85rem; margin-bottom: .5rem; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: .8rem; line-height: 1.5; white-space: pre; }
        .user-info { font-size: .875rem; color: #666; margin-bottom: 1.5rem; }
        .user-info span { font-weight: 600; color: #222; }
    </style>
</head>
<body>

<h1>Menu Analyzer</h1>

<!-- AUTH -->
<div id="auth-section">
    <h2>Sign In</h2>
    <div class="field">
        <label>Email</label>
        <input type="email" id="email" placeholder="admin@example.com" autocomplete="username">
    </div>
    <div class="field">
        <label>Password</label>
        <input type="password" id="password" autocomplete="current-password">
    </div>
    <button class="btn" id="login-btn" onclick="login()">Sign In</button>
    <p class="error-msg" id="auth-error"></p>
</div>

<!-- UPLOAD -->
<div id="upload-section">
    <p class="user-info">Signed in as <span id="user-email"></span> — <a href="#" onclick="logout(); return false;">Sign out</a></p>
    <h2>Upload Menu Images</h2>

    <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
        <p>Drag &amp; drop images here or <strong>click to select</strong></p>
        <input type="file" id="file-input" multiple accept="image/*" onchange="addFiles(this.files)">
    </div>

    <div class="previews" id="previews"></div>

    <div class="actions">
        <button class="btn" id="analyze-btn" onclick="analyze()">Analyze</button>
        <div class="spinner" id="spinner">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a56db" stroke-width="2.5">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            Analyzing, please wait…
        </div>
        <p class="error-msg" id="analyze-error"></p>
    </div>
</div>

<!-- RESULTS -->
<div id="results-section">
    <div class="results-header">
        <h2 id="results-title"></h2>
        <button class="btn btn-sm btn-gray" onclick="resetResults()">← New analysis</button>
    </div>
    <div class="menu-grid" id="menu-grid"></div>
    <details>
        <summary>Raw JSON</summary>
        <pre id="raw-json"></pre>
    </details>
</div>

<script>
    const API = '/api/v1';
    let selectedFiles = [];

    // ── Init ──────────────────────────────────────────────────────────────────
    (function init() {
        const token = sessionStorage.getItem('api_token');
        const email = sessionStorage.getItem('api_email');
        if (token) showUpload(email);
    })();

    // ── Auth ──────────────────────────────────────────────────────────────────
    async function login() {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const btn = document.getElementById('login-btn');
        const err = document.getElementById('auth-error');

        err.textContent = '';
        btn.disabled = true;
        btn.textContent = 'Signing in…';

        try {
            const res = await fetch(`${API}/tokens`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ email, password }),
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.errors?.email?.[0] ?? data.message ?? 'Login failed');
            }

            sessionStorage.setItem('api_token', data.token);
            sessionStorage.setItem('api_email', email);
            showUpload(email);
        } catch (e) {
            err.textContent = e.message;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Sign In';
        }
    }

    function logout() {
        const token = sessionStorage.getItem('api_token');
        if (token) {
            fetch(`${API}/tokens/current`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
            }).catch(() => {});
        }
        sessionStorage.removeItem('api_token');
        sessionStorage.removeItem('api_email');
        document.getElementById('auth-section').style.display = 'block';
        document.getElementById('upload-section').style.display = 'none';
        document.getElementById('results-section').style.display = 'none';
    }

    function showUpload(email) {
        document.getElementById('auth-section').style.display = 'none';
        document.getElementById('upload-section').style.display = 'block';
        document.getElementById('user-email').textContent = email ?? '';
    }

    // ── File selection ────────────────────────────────────────────────────────
    function addFiles(fileList) {
        Array.from(fileList).forEach(f => {
            if (!selectedFiles.find(x => x.name === f.name && x.size === f.size)) {
                selectedFiles.push(f);
            }
        });
        renderPreviews();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        renderPreviews();
    }

    function renderPreviews() {
        const container = document.getElementById('previews');
        container.innerHTML = '';
        selectedFiles.forEach((f, i) => {
            const url = URL.createObjectURL(f);
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `
                <img src="${url}" alt="${f.name}">
                <button class="remove" onclick="removeFile(${i})">&#x2715;</button>`;
            container.appendChild(div);
        });
    }

    // Drag and drop
    const dropZone = document.getElementById('drop-zone');
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        addFiles(e.dataTransfer.files);
    });

    document.getElementById('password').addEventListener('keydown', e => {
        if (e.key === 'Enter') login();
    });

    // ── Analyze ───────────────────────────────────────────────────────────────
    async function analyze() {
        if (selectedFiles.length === 0) return;

        const token = sessionStorage.getItem('api_token');
        const btn = document.getElementById('analyze-btn');
        const spinner = document.getElementById('spinner');
        const err = document.getElementById('analyze-error');

        err.textContent = '';
        btn.disabled = true;
        spinner.classList.add('active');

        const fd = new FormData();
        selectedFiles.forEach((f, i) => fd.append(`images[${i}]`, f));

        try {
            const res = await fetch(`${API}/menu-analyses`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/vnd.api+json' },
                body: fd,
            });

            if (res.status === 401) {
                sessionStorage.removeItem('api_token');
                logout();
                return;
            }

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message ?? `Error ${res.status}`);
            }

            const attrs = data.data?.attributes ?? data.data ?? data;
            renderResults(attrs);
        } catch (e) {
            err.textContent = e.message;
        } finally {
            btn.disabled = false;
            spinner.classList.remove('active');
        }
    }

    // ── Render results (restaurant + sections/categories + items, bilingual + price) ─
    function menuSections(menu) {
        if (!menu || typeof menu !== 'object') return [];
        const s = menu.sections ?? menu.categories ?? [];
        return Array.isArray(s) ? s : [];
    }

    function bilingualPair(field) {
        if (field == null) return { primary: '', secondary: null };
        if (typeof field === 'string') return { primary: field, secondary: null };
        if (typeof field !== 'object') return { primary: String(field), secondary: null };
        const en = field.en != null ? String(field.en) : '';
        const vi = field.vi != null ? String(field.vi) : '';
        if (en !== '' && vi !== '' && en !== vi) return { primary: en, secondary: vi };
        return { primary: en || vi, secondary: null };
    }

    function formatPriceDisplay(price, defaultCurrency) {
        if (!price || typeof price !== 'object') return '';
        const cur = price.currency != null && price.currency !== '' ? String(price.currency) : (defaultCurrency || '');
        let amount = '';
        if (price.type === 'range' && price.min != null && price.max != null) {
            amount = String(price.min) + '–' + String(price.max);
        } else if (price.value != null) {
            amount = String(price.value);
        }
        const parts = [];
        if (amount !== '') parts.push(amount);
        if (cur !== '') parts.push(cur);
        let line = parts.join(' ');
        if (price.original_text) line += (line ? ' ' : '') + '(' + String(price.original_text) + ')';
        const unit = price.unit_en || price.unit;
        if (unit) line += (line ? ' ' : '') + String(unit);
        return line.trim();
    }

    function renderVariationLines(variations, defaultCurrency) {
        if (!Array.isArray(variations) || variations.length === 0) return '';
        const lines = variations.map(v => {
            const vn = bilingualPair(v.name);
            const vp = v.price ? formatPriceDisplay(v.price, defaultCurrency) : '';
            const label = [vn.primary, vn.secondary].filter(Boolean).join(' · ');
            return `<div class="variation-line">${esc(label)}${vp ? ` — <strong>${esc(vp)}</strong>` : ''}</div>`;
        }).join('');
        return `<div class="variations">${lines}</div>`;
    }

    function renderResults(attrs) {
        const menu = attrs.menu;
        const itemCount = attrs.item_count ?? 0;
        const imageCount = attrs.image_count ?? 0;
        document.getElementById('results-title').textContent =
            `${itemCount} dish(es) from ${imageCount} image(s)`;

        const grid = document.getElementById('menu-grid');
        grid.innerHTML = '';

        if (!menu || typeof menu !== 'object' || (menu.sections == null && menu.categories == null && !menu.restaurant)) {
            const fallback = document.createElement('div');
            fallback.className = 'card';
            fallback.style.gridColumn = '1 / -1';
            fallback.textContent = 'No menu data in response.';
            grid.appendChild(fallback);
        } else {
            const defaultCur = menu.restaurant && menu.restaurant.currency != null
                ? String(menu.restaurant.currency) : '';

            if (menu.restaurant) {
                const r = menu.restaurant;
                const nameP = bilingualPair(r.name);
                const addrP = bilingualPair(r.address);
                const card = document.createElement('div');
                card.className = 'card restaurant-card';
                const extra = [];
                if (r.city) extra.push(esc(String(r.city)));
                if (r.district) extra.push(esc(String(r.district)));
                if (r.opening_hours && typeof r.opening_hours === 'string') extra.push(esc(r.opening_hours));
                card.innerHTML = `
                    <div class="category">Restaurant</div>
                    <div class="name">${esc(nameP.primary || '—')}</div>
                    ${nameP.secondary ? `<div class="name-original">${esc(nameP.secondary)}</div>` : ''}
                    ${addrP.primary ? `<div class="description">${esc(addrP.primary)}</div>` : ''}
                    ${addrP.secondary ? `<div class="muted">${esc(addrP.secondary)}</div>` : ''}
                    ${extra.length ? `<div class="muted">${extra.join(' · ')}</div>` : ''}
                `;
                grid.appendChild(card);
            }

            menuSections(menu).forEach((section, idx) => {
                const catP = bilingualPair(section.category_name);
                const heading = document.createElement('div');
                heading.className = 'section-heading';
                heading.innerHTML = `
                    <h3>${esc(catP.primary || ('Section ' + (idx + 1)))}</h3>
                    ${catP.secondary ? `<div class="muted">${esc(catP.secondary)}</div>` : ''}
                `;
                grid.appendChild(heading);

                const secItems = Array.isArray(section.items) ? section.items : [];
                for (const item of secItems) {
                    const nameP = bilingualPair(item.name);
                    const descP = item.description ? bilingualPair(item.description) : { primary: '', secondary: null };
                    const priceStr = item.price ? formatPriceDisplay(item.price, defaultCur) : '';
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.innerHTML = `
                        <div class="name">${esc(nameP.primary || '—')}</div>
                        ${nameP.secondary ? `<div class="name-original">${esc(nameP.secondary)}</div>` : ''}
                        ${descP.primary ? `<div class="description">${esc(descP.primary)}</div>` : ''}
                        ${descP.secondary ? `<div class="muted">${esc(descP.secondary)}</div>` : ''}
                        ${priceStr ? `<span class="price">${esc(priceStr)}</span>` : ''}
                        ${renderVariationLines(item.variations, defaultCur)}
                    `;
                    grid.appendChild(card);
                }
            });
        }

        document.getElementById('raw-json').textContent = JSON.stringify(menu ?? {}, null, 2);
        document.getElementById('upload-section').style.display = 'none';
        document.getElementById('results-section').style.display = 'block';
    }

    function resetResults() {
        selectedFiles = [];
        renderPreviews();
        document.getElementById('file-input').value = '';
        document.getElementById('results-section').style.display = 'none';
        document.getElementById('upload-section').style.display = 'block';
    }

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
</script>

</body>
</html>
