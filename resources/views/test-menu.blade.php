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
        #analyze-debug-wrap { display: none; margin-top: .75rem; max-width: 100%; }
        #analyze-debug-wrap summary { cursor: pointer; color: #555; font-size: .85rem; margin-bottom: .35rem; }
        #analyze-debug-body {
            background: #1e1e1e; color: #d4d4d4; padding: .75rem; border-radius: 6px;
            font-size: .72rem; line-height: 1.45; overflow-x: auto; white-space: pre-wrap; word-break: break-word;
            max-height: 50vh;
        }

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

        /* Live progress (stages + log) */
        .progress-panel { display: none; margin-top: 1rem; background: #fff; border-radius: 8px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); border-left: 3px solid #1a56db; }
        .progress-panel.active { display: block; }
        .stages-list { list-style: none; padding: 0; margin: 0; }
        .stages-list li { display: flex; align-items: flex-start; gap: .65rem; margin-bottom: .55rem; font-size: .9rem; }
        .stages-list li:last-child { margin-bottom: 0; }
        .stage-icon { flex-shrink: 0; width: 1.25rem; height: 1.25rem; display: flex; align-items: center; justify-content: center; margin-top: .1rem; }
        .stage-icon-dot { width: .5rem; height: .5rem; border-radius: 50%; background: #d1d5db; }
        .stage-icon-spinner { width: 1rem; height: 1rem; border: 2px solid #93c5fd; border-top-color: #1a56db; border-radius: 50%; animation: spin .8s linear infinite; }
        .stage-icon-check { color: #16a34a; font-weight: 700; font-size: 1rem; line-height: 1; }
        .stage-icon-x { color: #dc2626; font-weight: 700; font-size: 1rem; line-height: 1; }
        .stage-icon-skip { width: .65rem; height: 2px; background: #9ca3af; }
        .stage-body { flex: 1; min-width: 0; }
        .stage-label { font-weight: 500; line-height: 1.3; }
        .stage-label.pending, .stage-label.skipped { color: #9ca3af; }
        .stage-label.in_progress { color: #1a56db; }
        .stage-label.done { color: #374151; }
        .stage-label.failed { color: #dc2626; }
        .stage-detail { font-size: .78rem; color: #6b7280; margin-top: .15rem; line-height: 1.3; }
        .live-log-wrap { margin-top: .75rem; }
        .live-log-wrap summary { font-size: .8rem; color: #6b7280; cursor: pointer; user-select: none; padding: .2rem 0; }
        .live-log-wrap summary:hover { color: #374151; }
        #live-log { max-height: 16rem; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: .5rem .75rem; border-radius: 4px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; line-height: 1.55; margin-top: .35rem; white-space: pre; }
        #live-log .row { display: flex; gap: .6rem; }
        #live-log .ts { color: #888; flex-shrink: 0; }
        #live-log .lvl { font-weight: 600; flex-shrink: 0; width: 4rem; text-transform: uppercase; }
        #live-log .lvl.info { color: #9ca3af; }
        #live-log .lvl.ok { color: #4ade80; }
        #live-log .lvl.warn { color: #fbbf24; }
        #live-log .lvl.error { color: #f87171; }
        #live-log .msg { color: #e5e7eb; word-break: break-all; white-space: pre-wrap; flex: 1; }

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
        .restaurant-mode-panel { display: none; }
        .restaurant-mode-panel.mode-id-row { gap: .5rem; align-items: center; flex-wrap: wrap; }
        .restaurant-mode-panel.is-visible:not(.mode-id-row) { display: block; }
        .restaurant-mode-panel.mode-id-row.is-visible { display: flex; }
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

    <div style="max-width:480px; margin-bottom:1.25rem;">
        <label style="display:block; font-size:.875rem; margin-bottom:.5rem; color:#444;">
            Restaurant
            <span style="color:#888; font-weight:400;">(optional — saves the analyzed menu)</span>
        </label>

        <!-- Mode tabs -->
        <div style="display:flex; gap:.5rem; margin-bottom:.75rem; flex-wrap:wrap;">
            <button type="button" class="mode-btn active" data-mode="none"  onclick="setMode('none')"  style="padding:.35rem .9rem; border-radius:6px; border:1px solid #ccc; background:#fff; cursor:pointer; font-size:.875rem;">Skip</button>
            <button type="button" class="mode-btn"        data-mode="mine"  onclick="setMode('mine')"  style="padding:.35rem .9rem; border-radius:6px; border:1px solid #ccc; background:#fff; cursor:pointer; font-size:.875rem;">My restaurants</button>
            <button type="button" class="mode-btn"        data-mode="id"    onclick="setMode('id')"    style="padding:.35rem .9rem; border-radius:6px; border:1px solid #ccc; background:#fff; cursor:pointer; font-size:.875rem;">By ID</button>
            <button type="button" class="mode-btn"        data-mode="new"   onclick="setMode('new')"   style="padding:.35rem .9rem; border-radius:6px; border:1px solid #ccc; background:#fff; cursor:pointer; font-size:.875rem;">Create new</button>
        </div>

        <!-- Mine -->
        <div id="mode-mine" class="restaurant-mode-panel">
            <select id="restaurant-select" style="width:100%; padding:.55rem .9rem; border:1px solid #ccc; border-radius:6px; font-size:1rem; background:#fff;">
                <option value="">Loading…</option>
            </select>
            <p id="restaurant-select-hint" class="muted" style="margin-top:.5rem;"></p>
        </div>

        <!-- By ID -->
        <div id="mode-id" class="restaurant-mode-panel mode-id-row">
            <input type="number" id="restaurant-id-input" placeholder="e.g. 42" min="1" step="1"
                   style="width:120px; padding:.55rem .9rem; border:1px solid #ccc; border-radius:6px; font-size:1rem;"
                   oninput="onRestaurantIdInput()">
            <span id="restaurant-id-status" style="font-size:.85rem; color:#888;"></span>
        </div>

        <!-- Create new -->
        <div id="mode-new" class="restaurant-mode-panel">
            <button type="button" class="btn btn-sm" id="create-restaurant-btn" onclick="createRestaurant()">
                Create empty restaurant
            </button>
            <span id="create-restaurant-status" style="font-size:.85rem; color:#16a34a; margin-left:.75rem;"></span>
        </div>
    </div>

    <div style="margin-bottom:1.25rem;">
        <label style="display:block; font-size:.875rem; margin-bottom:.5rem; color:#444;">Vision Model</label>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
            <button type="button" class="model-btn" data-model="auto" onclick="setVisionModel('auto')"
                style="padding:.35rem .9rem; border-radius:6px; border:2px solid #1a56db; background:#1a56db; color:#fff; cursor:pointer; font-size:.875rem;">
                Auto (cascade)
            </button>
            <select id="openrouter-select" onchange="setVisionModel(this.value)"
                style="padding:.35rem .9rem; border-radius:6px; border:1px solid #ccc; background:#fff; cursor:pointer; font-size:.875rem; color:#111;">
                <option value="" disabled selected>— force specific model —</option>
                <option value="google/gemma-4-26b-a4b-it:free">Gemma 4 26B (free)</option>
                <option value="google/gemma-4-26b-a4b-it">Gemma 4 26B</option>
                <option value="google/gemma-4-31b-it:free">Gemma 4 31B (free)</option>
                <option value="google/gemma-4-31b-it">Gemma 4 31B</option>
                <option value="qwen/qwen3.6-plus">Qwen 3.6 Plus</option>
                <option value="opengvlab/internvl3-78b">InternVL3 78B</option>
                <option value="rekaai/reka-edge">Reka Edge</option>
                <option value="arcee-ai/spotlight">Arcee Spotlight</option>
                <option value="meta-llama/llama-4-maverick">Llama 4 Maverick</option>
            </select>
        </div>
        <p id="model-hint" style="font-size:.8rem; color:#6b7280; margin-top:.4rem;">
            Qwen → Gemini → Gemma cascade with automatic fallback
        </p>
    </div>

    <div class="actions">
        <button class="btn" id="analyze-btn" onclick="analyze()">Analyze</button>
        <div class="spinner" id="spinner">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a56db" stroke-width="2.5">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            Analyzing, please wait…
        </div>
        <p class="error-msg" id="analyze-error"></p>
        <div id="analyze-debug-wrap">
            <details open>
                <summary>Request / response details (debug)</summary>
                <pre id="analyze-debug-body"></pre>
            </details>
        </div>
    </div>

    <div class="progress-panel" id="progress-panel">
        <ul class="stages-list" id="stages-list"></ul>
        <div class="live-log-wrap">
            <details open>
                <summary>Live log <span id="live-log-count" style="color:#9ca3af">(0)</span></summary>
                <div id="live-log"></div>
            </details>
        </div>
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
    let selectedVisionModel = 'auto';

    function setVisionModel(model) {
        selectedVisionModel = model;
        const isAuto = model === 'auto';

        const autoBtn = document.querySelector('.model-btn[data-model="auto"]');
        autoBtn.style.background = isAuto ? '#1a56db' : '#fff';
        autoBtn.style.color = isAuto ? '#fff' : '#111';
        autoBtn.style.borderColor = isAuto ? '#1a56db' : '#ccc';
        autoBtn.style.borderWidth = '2px';

        const orSelect = document.getElementById('openrouter-select');
        orSelect.style.borderColor = isAuto ? '#ccc' : '#1a56db';
        orSelect.style.borderWidth = isAuto ? '1px' : '2px';
        if (!isAuto) {
            orSelect.value = model;
        } else {
            orSelect.selectedIndex = 0;
        }

        const hint = document.getElementById('model-hint');
        if (isAuto) {
            hint.textContent = 'Qwen → Gemini → Gemma cascade with automatic fallback';
        } else {
            hint.textContent = `Direct call to ${model} — no fallback`;
        }
    }

    // ── CSRF helpers ──────────────────────────────────────────────────────────
    function getCsrfToken() {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    async function initCsrf() {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    }

    function authHeaders(extra = {}) {
        const csrf = getCsrfToken();
        return {
            'Accept': 'application/json',
            ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
            ...extra,
        };
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    (async function init() {
        try {
            const res = await fetch(`${API}/auth/user`, {
                credentials: 'include',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                showUpload(data.user?.email ?? '');
            }
        } catch (_) { /* not logged in */ }
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
            await initCsrf();

            const res = await fetch(`${API}/auth/login`, {
                method: 'POST',
                credentials: 'include',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ email, password }),
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.errors?.email?.[0] ?? data.message ?? 'Login failed');
            }

            showUpload(data.user?.email ?? email);
        } catch (e) {
            err.textContent = e.message;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Sign In';
        }
    }

    async function logout() {
        try {
            await fetch(`${API}/auth/logout`, {
                method: 'POST',
                credentials: 'include',
                headers: authHeaders(),
            });
        } catch (_) {}
        document.getElementById('auth-section').style.display = 'block';
        document.getElementById('upload-section').style.display = 'none';
        document.getElementById('results-section').style.display = 'none';
    }

    async function showUpload(email) {
        document.getElementById('auth-section').style.display = 'none';
        document.getElementById('upload-section').style.display = 'block';
        document.getElementById('user-email').textContent = email ?? '';
        await loadRestaurants();
    }

    // ── Restaurant selector ───────────────────────────────────────────────────
    let currentMode = 'none';
    let selectedRestaurantId = null;

    function setMode(mode) {
        currentMode = mode;
        selectedRestaurantId = null;
        const idInput = document.getElementById('restaurant-id-input');
        if (idInput) {
            idInput.value = '';
        }
        const idStatus = document.getElementById('restaurant-id-status');
        if (idStatus) {
            idStatus.textContent = '';
        }
        const createStatus = document.getElementById('create-restaurant-status');
        if (createStatus) {
            createStatus.textContent = '';
        }

        ['none', 'mine', 'id', 'new'].forEach((m) => {
            const el = document.getElementById(`mode-${m}`);
            if (!el) {
                return;
            }
            el.classList.toggle('is-visible', m === mode && mode !== 'none');
        });
        document.querySelectorAll('.mode-btn').forEach((btn) => {
            const active = btn.dataset.mode === mode;
            btn.style.background = active ? '#1a56db' : '#fff';
            btn.style.color = active ? '#fff' : '';
            btn.style.borderColor = active ? '#1a56db' : '#ccc';
        });

        if (mode === 'mine') {
            const select = document.getElementById('restaurant-select');
            if (select && (select.options.length <= 1 || select.options[0].textContent === 'Loading…')) {
                loadRestaurants();
            } else if (select) {
                selectedRestaurantId = select.value || null;
            }
        }
    }

    async function loadRestaurants() {
        const select = document.getElementById('restaurant-select');
        const hint = document.getElementById('restaurant-select-hint');
        if (!select) {
            return;
        }
        select.innerHTML = '<option value="">Loading…</option>';
        select.disabled = true;
        if (hint) {
            hint.textContent = '';
        }
        try {
            const res = await fetch(`${API}/restaurants`, {
                credentials: 'include',
                headers: authHeaders(),
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) {
                select.innerHTML = '<option value="">Failed to load</option>';
                if (hint) {
                    hint.textContent = payload.message ?? `HTTP ${res.status}`;
                    hint.style.color = '#c00';
                }
                select.disabled = false;
                return;
            }
            const list = Array.isArray(payload.data) ? payload.data : [];
            select.innerHTML = '<option value="">— select —</option>';
            list.forEach((r) => {
                const opt = document.createElement('option');
                const attrs = r.attributes ?? r;
                opt.value = String(r.id);
                opt.textContent = `#${r.id} ${attrs.name ?? ''}` + (attrs.city ? ` · ${attrs.city}` : '');
                select.appendChild(opt);
            });
            select.onchange = () => {
                selectedRestaurantId = select.value || null;
            };
            if (list.length === 0) {
                select.innerHTML = '<option value="">No restaurants — use Create new or By ID</option>';
                if (hint) {
                    hint.textContent = 'You have no restaurants yet.';
                    hint.style.color = '#6b7280';
                }
            } else if (hint) {
                hint.textContent = 'Only restaurants you own are listed.';
                hint.style.color = '#6b7280';
            }
        } catch (e) {
            select.innerHTML = '<option value="">Network error</option>';
            if (hint) {
                hint.textContent = e.message;
                hint.style.color = '#c00';
            }
        } finally {
            select.disabled = false;
            if (currentMode === 'mine') {
                selectedRestaurantId = select.value || null;
            }
        }
    }

    function onRestaurantIdInput() {
        const input = document.getElementById('restaurant-id-input');
        const statusEl = document.getElementById('restaurant-id-status');
        const raw = input ? input.value.trim() : '';
        const n = raw === '' ? NaN : Number(raw);
        if (raw === '' || !Number.isInteger(n) || n < 1) {
            selectedRestaurantId = null;
            if (statusEl) {
                statusEl.textContent = raw === '' ? '' : 'Enter a positive integer restaurant ID.';
                statusEl.style.color = '#c00';
            }
            return;
        }
        selectedRestaurantId = String(n);
        if (statusEl) {
            statusEl.textContent = `Will save to restaurant #${n} (you must be owner).`;
            statusEl.style.color = '#6b7280';
        }
    }

    async function createRestaurant() {
        const btn = document.getElementById('create-restaurant-btn');
        const status = document.getElementById('create-restaurant-status');
        btn.disabled = true;
        status.textContent = 'Creating…';
        status.style.color = '#888';
        try {
            const res = await fetch(`${API}/restaurants`, {
                method: 'POST',
                credentials: 'include',
                headers: authHeaders(),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                status.textContent = body.message ?? `HTTP ${res.status}`;
                status.style.color = '#c00';
                return;
            }
            const id = body.data?.id;
            if (id == null) {
                status.textContent = 'Invalid response: missing restaurant id';
                status.style.color = '#c00';
                return;
            }
            selectedRestaurantId = String(id);
            status.textContent = `Created restaurant #${id} — analysis will be saved there.`;
            status.style.color = '#16a34a';
            await loadRestaurants();
            const select = document.getElementById('restaurant-select');
            if (select) {
                select.value = String(id);
            }
        } catch (e) {
            status.textContent = 'Failed: ' + e.message;
            status.style.color = '#c00';
        } finally {
            btn.disabled = false;
        }
    }

    function getSelectedRestaurantId() {
        if (currentMode === 'none') {
            return null;
        }
        if (currentMode === 'mine') {
            const v = document.getElementById('restaurant-select')?.value?.trim();
            return v && v !== '' ? v : null;
        }
        if (currentMode === 'id') {
            return selectedRestaurantId;
        }
        if (currentMode === 'new') {
            return selectedRestaurantId;
        }
        return null;
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

        const btn = document.getElementById('analyze-btn');
        const spinner = document.getElementById('spinner');
        const err = document.getElementById('analyze-error');

        err.textContent = '';
        const debugWrap = document.getElementById('analyze-debug-wrap');
        const debugBody = document.getElementById('analyze-debug-body');
        debugWrap.style.display = 'none';
        debugBody.textContent = '';

        btn.disabled = true;
        spinner.classList.add('active');

        const fd = new FormData();
        selectedFiles.forEach((f, i) => fd.append(`images[${i}]`, f));
        const restaurantId = getSelectedRestaurantId();
        if (currentMode === 'mine' && !restaurantId) {
            err.textContent = 'Choose a restaurant from the list, or switch mode to Skip / By ID / Create new.';
            btn.disabled = false;
            spinner.classList.remove('active');
            return;
        }
        if (currentMode === 'id' && !restaurantId) {
            err.textContent = 'Enter a valid restaurant ID, or switch to another mode.';
            btn.disabled = false;
            spinner.classList.remove('active');
            return;
        }
        if (currentMode === 'new' && !restaurantId) {
            err.textContent = 'Create a restaurant first (button above), or switch mode.';
            btn.disabled = false;
            spinner.classList.remove('active');
            return;
        }
        if (restaurantId) {
            fd.append('restaurant_id', restaurantId);
        }
        if (selectedVisionModel !== 'auto') {
            fd.append('model', selectedVisionModel);
        }

        try {
            const res = await fetch(`${API}/menu-analyses`, {
                method: 'POST',
                credentials: 'include',
                headers: authHeaders({ 'Accept': 'application/vnd.api+json' }),
                body: fd,
            });

            if (res.status === 401) {
                logout();
                return;
            }

            let data = {};
            try {
                data = await res.json();
            } catch (_) {
                err.textContent = `Error ${res.status} (invalid JSON body)`;
                return;
            }

            if (!res.ok) {
                err.textContent = data.message ?? `Error ${res.status}`;
                if (data.debug) {
                    debugBody.textContent = JSON.stringify(data.debug, null, 2);
                    debugWrap.style.display = 'block';
                }
                return;
            }

            const uuid = data.data?.id;
            const initialAttrs = data.data?.attributes ?? {};

            if (initialAttrs.status === 'completed') {
                renderResults(initialAttrs);
                return;
            }

            // Subscribe to SSE for live chunk progress
            await subscribeAnalysis(uuid);
        } catch (e) {
            err.textContent = e.message;
        } finally {
            btn.disabled = false;
            spinner.classList.remove('active');
        }
    }

    /**
     * Server-Sent Events stream of analysis progress.
     * Emits: analysis.started, analysis.chunk-complete (xN), analysis.completed,
     * analysis.failed. The browser auto-reconnects via Last-Event-ID; we close
     * the stream once we hit a terminal event and pull the final result via
     * GET /menu-analyses/{uuid} so renderResults() gets the same shape it
     * always did (full menu tree, saved_menu_id, etc).
     */
    async function subscribeAnalysis(uuid) {
        const spinner = document.getElementById('spinner');
        const err = document.getElementById('analyze-error');
        const textNode = Array.from(spinner.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());

        const startedAt = Date.now();
        const setStatus = (message) => {
            if (!textNode) return;
            const elapsedSec = Math.floor((Date.now() - startedAt) / 1000);
            textNode.textContent = ` ${message} (${elapsedSec}s)`;
        };
        const tickInterval = setInterval(() => {
            if (textNode && textNode.textContent.trim()) {
                // Refresh the elapsed counter without losing the chunk label.
                const stripped = textNode.textContent.replace(/ \(\d+s\)\s*$/, '');
                const elapsedSec = Math.floor((Date.now() - startedAt) / 1000);
                textNode.textContent = `${stripped} (${elapsedSec}s)`;
            }
        }, 1000);

        // Initialise live progress UI (stages + log feed).
        const progress = createProgressTracker();
        progress.show();

        return new Promise((resolve) => {
            setStatus('Connecting to event stream…');
            const es = new EventSource(`${API}/menu-analyses/${uuid}/events`, { withCredentials: true });
            let chunkTotal = 0;
            let finished = false;

            const finish = async (terminalAttrs, errorMessage) => {
                if (finished) return;
                finished = true;
                clearInterval(tickInterval);
                es.close();
                if (errorMessage) {
                    err.textContent = errorMessage;
                    resolve();
                    return;
                }
                if (terminalAttrs) {
                    renderResults(terminalAttrs);
                    resolve();
                    return;
                }
                // Pull final attributes (menu tree, saved_menu_id, item_count, …)
                try {
                    const res = await fetch(`${API}/menu-analyses/${uuid}`, {
                        credentials: 'include',
                        headers: authHeaders({ 'Accept': 'application/vnd.api+json' }),
                    });
                    const data = await res.json();
                    const attrs = data.data?.attributes ?? {};
                    if (attrs.status === 'completed') {
                        renderResults(attrs);
                    } else if (attrs.status === 'failed') {
                        err.textContent = attrs.error_message ?? 'Analysis failed.';
                    } else {
                        err.textContent = 'Analysis completed but final fetch returned an unexpected status.';
                    }
                } catch (e) {
                    err.textContent = 'Failed to fetch final result: ' + e.message;
                }
                resolve();
            };

            es.onmessage = (e) => {
                let parsed;
                try {
                    parsed = JSON.parse(e.data);
                } catch (_) {
                    return;
                }
                const event = parsed.event;
                const data = parsed.data ?? {};
                const ts = parsed.ts;

                if (event === 'analysis.started') {
                    chunkTotal = Number(data.chunk_total) || 0;
                    progress.setStage('queued', 'done', `${data.image_count || ''} image${data.image_count == 1 ? '' : 's'}`);
                    const chunkInfo = chunkTotal > 1 ? ` · ${chunkTotal} chunks` : '';
                    progress.appendLog('info', `Analysis queued · ${data.image_count} image${data.image_count == 1 ? '' : 's'}${chunkInfo}`, ts);
                    setStatus(chunkTotal > 1 ? `Queued · ${chunkTotal} chunks` : 'Queued for analysis…');
                } else if (event === 'analysis.preflight-start') {
                    progress.advance('prep');
                    progress.preflightTotal = Number(data.image_count) || 0;
                    progress.preflightDone = 0;
                    progress.setStage('prep', 'in_progress', `Preflight 0/${progress.preflightTotal}`);
                    progress.appendLog('info', `Preflight starting · ${data.image_count} image${data.image_count == 1 ? '' : 's'}`, ts);
                } else if (event === 'analysis.preflight-image') {
                    progress.preflightDone += 1;
                    progress.setStage('prep', 'in_progress', `Preflight ${progress.preflightDone}/${progress.preflightTotal}`);
                    const rot = Number(data.rotation_cw) || 0;
                    const bbox = Array.isArray(data.content_bbox) ? ` · bbox ${data.content_bbox.map(n => n.toFixed(2)).join(',')}` : '';
                    const rotMsg = rot ? `rotate ${rot}° CW` : 'no rotation';
                    progress.appendLog('ok', `Preflight image #${data.index} · ${rotMsg}${bbox} · quality=${data.quality}`, ts);
                } else if (event === 'analysis.preprocess-start') {
                    progress.preprocessTotal = Number(data.image_count) || 0;
                    progress.preprocessDone = 0;
                    progress.setStage('prep', 'in_progress', `Preprocessing 0/${progress.preprocessTotal}`);
                    progress.appendLog('info', `Preprocess starting · trim · deskew · WebP`, ts);
                } else if (event === 'analysis.preprocess-image') {
                    progress.preprocessDone += 1;
                    progress.setStage('prep', 'in_progress', `Preprocessing ${progress.preprocessDone}/${progress.preprocessTotal}`);
                    progress.appendLog('ok', `Preprocessed image #${data.index} · ${data.original_dims} → ${data.final_dims} · ${data.final_size_kb} KB`, ts);
                } else if (event === 'analysis.vision-start') {
                    progress.advance('vision');
                    const providers = (data.providers || []).join(' → ');
                    progress.setStage('vision', 'in_progress', providers ? `Trying ${providers}` : '');
                    progress.appendLog('info', `Vision LLM starting · provider chain: ${providers || 'n/a'}`, ts);
                    setStatus('Analyzing menu with vision LLM…');
                } else if (event === 'analysis.cascade-attempt') {
                    progress.appendLog('info', `Trying tier ${data.tier} · ${data.provider}:${data.model}`, ts);
                } else if (event === 'analysis.cascade-fallback') {
                    const sec = ((Number(data.duration_ms) || 0) / 1000).toFixed(1);
                    const remaining = Number(data.remaining_providers);
                    const tail = remaining > 0 ? ` · ${remaining} provider${remaining == 1 ? '' : 's'} remaining` : ' · no fallback left';
                    progress.appendLog('warn', `Tier ${data.tier} ${data.provider}:${data.model} failed after ${sec}s — ${data.error}${tail}`, ts);
                } else if (event === 'analysis.chunk-complete') {
                    progress.advance('vision');
                    const done = Number(data.chunk_index) || 0;
                    const total = Number(data.chunk_total) || chunkTotal;
                    chunkTotal = total;
                    progress.setStage('vision', 'in_progress', `${done}/${total} chunks done · ${data.provider || ''}`);
                    progress.appendLog('ok', `Chunk ${done}/${total} done · ${data.provider || ''}:${data.model || ''} · tier ${data.tier ?? '?'}`, ts);
                    setStatus(`Chunk ${done}/${total} done`);
                } else if (event === 'analysis.vision-complete') {
                    const sec = ((Number(data.duration_ms) || 0) / 1000).toFixed(1);
                    progress.setStage('vision', 'done', `${data.provider || ''}:${data.model || ''} · ${sec}s · ${data.item_count || 0} items`);
                    progress.advance('save');
                    const tokens = (data.input_tokens || data.output_tokens)
                        ? ` · ${data.input_tokens || '?'} in / ${data.output_tokens || '?'} out tokens`
                        : '';
                    progress.appendLog('ok', `Vision LLM done · ${data.provider}:${data.model} · ${sec}s${tokens} · parsed ${data.item_count || 0} items`, ts);
                } else if (event === 'analysis.menu-saved') {
                    progress.setStage('save', 'done', `${data.section_count || 0} sections · ${data.item_count || 0} items`);
                    progress.advance('crops');
                    progress.appendLog('ok', `Menu saved · id=${data.menu_id} · ${data.section_count || 0} sections · ${data.item_count || 0} items`, ts);
                } else if (event === 'analysis.crops-start') {
                    progress.advance('crops');
                    progress.setStage('crops', 'in_progress', `${data.items_with_bbox || 0} items to crop`);
                    progress.appendLog('info', `Cropping ${data.items_with_bbox || 0} items with bbox …`, ts);
                } else if (event === 'analysis.crops-complete') {
                    progress.setStage('crops', 'done', `${data.items_cropped || 0} items cropped`);
                    progress.appendLog('ok', `Cropping done · ${data.items_cropped || 0} items cropped`, ts);
                } else if (event === 'analysis.completed') {
                    for (const s of progress.stages) {
                        if (s.status === 'in_progress') s.status = 'done';
                        if (s.status === 'pending') s.status = 'skipped';
                    }
                    progress.setStage('done', 'done', '');
                    progress.appendLog('ok', `Analysis complete · menu_id=${data.menu_id || 'n/a'} · ${data.item_count || 0} items`, ts);
                    progress.render();
                    setStatus('Analysis complete, fetching menu…');
                    finish(null, null);
                } else if (event === 'analysis.failed') {
                    const failing = progress.stages.find(s => s.status === 'in_progress')
                                 ?? progress.stages.find(s => s.status === 'pending');
                    if (failing) failing.status = 'failed';
                    progress.appendLog('error', `Analysis failed · ${data.error || 'unknown'}`, ts);
                    progress.render();
                    finish(null, data.error || 'Analysis failed.');
                }
            };

            es.onerror = () => {
                if (finished) return;
                if (es.readyState === EventSource.CLOSED) {
                    finish(null, 'Connection to event stream lost.');
                }
            };
        });
    }

    /**
     * Live progress tracker — manages the stages list + log feed DOM,
     * shared between the spinner and the SSE event handlers.
     */
    function createProgressTracker() {
        const panel = document.getElementById('progress-panel');
        const stagesEl = document.getElementById('stages-list');
        const logEl = document.getElementById('live-log');
        const logCountEl = document.getElementById('live-log-count');
        const stages = [
            { key: 'queued', label: 'Queued for processing',                 status: 'pending', detail: '' },
            { key: 'prep',   label: 'Preparing images (rotate · crop · deskew)', status: 'pending', detail: '' },
            { key: 'vision', label: 'Analyzing menu with vision LLM',         status: 'pending', detail: '' },
            { key: 'save',   label: 'Saving menu data',                       status: 'pending', detail: '' },
            { key: 'crops',  label: 'Cropping dish photos',                   status: 'pending', detail: '' },
            { key: 'done',   label: 'Done',                                   status: 'pending', detail: '' },
        ];
        let logCount = 0;

        const iconHtml = (status) => {
            switch (status) {
                case 'in_progress': return '<span class="stage-icon-spinner"></span>';
                case 'done':        return '<span class="stage-icon-check">✓</span>';
                case 'failed':      return '<span class="stage-icon-x">✕</span>';
                case 'skipped':     return '<span class="stage-icon-skip"></span>';
                default:            return '<span class="stage-icon-dot"></span>';
            }
        };

        const tracker = {
            stages,
            preflightDone: 0, preflightTotal: 0,
            preprocessDone: 0, preprocessTotal: 0,
            show() { panel.classList.add('active'); stagesEl.innerHTML = ''; logEl.innerHTML = ''; logCount = 0; logCountEl.textContent = '(0)'; this.render(); },
            hide() { panel.classList.remove('active'); },
            render() {
                stagesEl.innerHTML = stages.map(s => `
                    <li>
                        <span class="stage-icon">${iconHtml(s.status)}</span>
                        <span class="stage-body">
                            <span class="stage-label ${s.status}">${escapeHtml(s.label)}</span>
                            ${s.detail ? `<span class="stage-detail">${escapeHtml(s.detail)}</span>` : ''}
                        </span>
                    </li>
                `).join('');
            },
            setStage(key, status, detail) {
                const s = stages.find(x => x.key === key);
                if (!s) return;
                s.status = status;
                if (detail !== undefined) s.detail = detail;
                this.render();
            },
            advance(key) {
                for (const s of stages) {
                    if (s.key === key) { s.status = 'in_progress'; break; }
                    if (s.status === 'pending' || s.status === 'in_progress') s.status = 'done';
                }
                this.render();
            },
            appendLog(level, message, ts) {
                const date = ts ? new Date(ts * 1000) : new Date();
                const hh = String(date.getHours()).padStart(2, '0');
                const mm = String(date.getMinutes()).padStart(2, '0');
                const ss = String(date.getSeconds()).padStart(2, '0');
                const row = document.createElement('div');
                row.className = 'row';
                row.innerHTML = `<span class="ts">${hh}:${mm}:${ss}</span><span class="lvl ${level}">${level}</span><span class="msg">${escapeHtml(message)}</span>`;
                logEl.appendChild(row);
                while (logEl.children.length > 200) logEl.removeChild(logEl.firstChild);
                logCount++;
                logCountEl.textContent = `(${logCount})`;
                logEl.scrollTop = logEl.scrollHeight;
            },
        };

        return tracker;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    }

    // ── JSON:API attributes (snake_case or camelCase) ───────────────────────────
    function apiAttr(attrs, snake, camel) {
        if (attrs[snake] !== undefined && attrs[snake] !== null) {
            return attrs[snake];
        }
        if (camel && attrs[camel] !== undefined && attrs[camel] !== null) {
            return attrs[camel];
        }

        return undefined;
    }

    function extractBalancedClient(text, open, close) {
        const start = text.indexOf(open);
        if (start === -1) {
            return null;
        }
        let depth = 0;
        let inString = false;
        let escape = false;
        for (let i = start; i < text.length; i++) {
            const c = text[i];
            if (inString) {
                if (escape) {
                    escape = false;
                    continue;
                }
                if (c === '\\') {
                    escape = true;
                    continue;
                }
                if (c === '"') {
                    inString = false;
                }
                continue;
            }
            if (c === '"') {
                inString = true;
                continue;
            }
            if (c === open) {
                depth++;
            } else if (c === close) {
                depth--;
                if (depth === 0) {
                    return text.slice(start, i + 1);
                }
            }
        }

        return null;
    }

    function decodeMenuFromLlmTextClient(raw) {
        if (typeof raw !== 'string') {
            return null;
        }
        let t = raw.trim();
        if (t.startsWith('\uFEFF')) {
            t = t.slice(1).trim();
        }
        t = t.replace(/^```(?:json)?\s*/i, '').replace(/\s*```\s*$/, '').trim();

        const normalizeListRoot = decoded => {
            if (decoded == null || typeof decoded !== 'object') {
                return null;
            }
            if (Array.isArray(decoded)) {
                if (decoded.length === 0) {
                    return null;
                }

                return {
                    sections: [{ category_name: { vi: '', en: '' }, sort_order: 0, items: decoded }],
                };
            }

            return decoded;
        };

        try {
            return normalizeListRoot(JSON.parse(t));
        } catch (_) { /* continue */ }
        let slice = extractBalancedClient(t, '{', '}');
        if (slice) {
            try {
                return normalizeListRoot(JSON.parse(slice));
            } catch (_) { /* continue */ }
        }
        slice = extractBalancedClient(t, '[', ']');
        if (slice) {
            try {
                return normalizeListRoot(JSON.parse(slice));
            } catch (_) { /* continue */ }
        }

        return null;
    }

    function effectiveMenu(attrs) {
        const direct = apiAttr(attrs, 'menu', 'menu');
        if (direct && typeof direct === 'object' && !Array.isArray(direct)) {
            const hasSec = Array.isArray(direct.sections) && direct.sections.length > 0;
            const hasCat = Array.isArray(direct.categories) && direct.categories.length > 0;
            if (direct.restaurant != null || hasSec || hasCat) {
                return direct;
            }
        }
        const raw = apiAttr(attrs, 'llm_raw_text', 'llmRawText');
        if (typeof raw === 'string' && raw.trim()) {
            const parsed = decodeMenuFromLlmTextClient(raw);
            if (parsed) {
                return parsed;
            }
        }

        return (direct && typeof direct === 'object' && !Array.isArray(direct)) ? direct : {};
    }

    function formatRawJsonBlock(attrs) {
        const raw = apiAttr(attrs, 'llm_raw_text', 'llmRawText');
        if (typeof raw === 'string' && raw.trim().length) {
            try {
                return JSON.stringify(JSON.parse(raw.trim()), null, 2);
            } catch (_) {
                const slice = extractBalancedClient(raw, '{', '}');
                if (slice) {
                    try {
                        return JSON.stringify(JSON.parse(slice), null, 2);
                    } catch (_) { /* continue */ }
                }
            }

            return raw;
        }
        const menu = apiAttr(attrs, 'menu', 'menu');

        try {
            return JSON.stringify(menu ?? {}, null, 2);
        } catch (_) {
            return String(menu);
        }
    }

    // ── Render results (restaurant + sections/categories + items, bilingual + price) ─
    function menuSections(menu) {
        if (!menu || typeof menu !== 'object') return [];
        const s = menu.sections ?? menu.categories ?? [];
        return Array.isArray(s) ? s : [];
    }

    function countDishes(menu) {
        return menuSections(menu).reduce((n, s) => n + (Array.isArray(s.items) ? s.items.length : 0), 0);
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
        const menu = effectiveMenu(attrs);
        const parsedCount = countDishes(menu);
        const itemCount = parsedCount > 0 ? parsedCount : (attrs.item_count ?? attrs.itemCount ?? 0);
        const imageCount = attrs.image_count ?? attrs.imageCount ?? 0;
        const llmMs = attrs.llm_duration_ms ?? attrs.llmDurationMs ?? 0;
        const timing = llmMs > 0 ? ` · LLM ${(llmMs / 1000).toFixed(1)}s` : '';
        document.getElementById('results-title').textContent =
            `${itemCount} dish(es) from ${imageCount} image(s)${timing}`;

        const grid = document.getElementById('menu-grid');
        grid.innerHTML = '';

        const hasRestaurant = menu.restaurant != null && typeof menu.restaurant === 'object';
        const hasDishes = menuSections(menu).some(s => Array.isArray(s.items) && s.items.length > 0);

        if (!hasRestaurant && !hasDishes) {
            const fallback = document.createElement('div');
            fallback.className = 'card';
            fallback.style.gridColumn = '1 / -1';
            fallback.textContent = 'No structured menu to show as cards — open «Raw JSON» below for the full LLM text.';
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

        document.getElementById('raw-json').textContent = formatRawJsonBlock(attrs);
        if (attrs.saved_menu_id) {
            const note = document.createElement('p');
            note.style.cssText = 'color:#16a34a; font-size:.875rem; margin-bottom:.75rem;';
            const linkId = attrs.saved_restaurant_id ?? attrs.saved_menu_id;
            note.innerHTML = `✓ Saved as Menu #${attrs.saved_menu_id} — <a href="/${linkId}" target="_blank" style="color:#16a34a; font-weight:600;">View public page →</a>`;
            document.querySelector('.results-header').appendChild(note);
        }
        document.getElementById('upload-section').style.display = 'none';
        document.getElementById('results-section').style.display = 'block';
    }

    function resetResults() {
        selectedFiles = [];
        renderPreviews();
        document.getElementById('file-input').value = '';
        document.getElementById('results-section').style.display = 'none';
        document.getElementById('upload-section').style.display = 'block';
        document.getElementById('progress-panel').classList.remove('active');
    }

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
</script>

</body>
</html>
