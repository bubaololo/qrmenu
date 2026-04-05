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

            renderResults(data.data.attributes);
        } catch (e) {
            err.textContent = e.message;
        } finally {
            btn.disabled = false;
            spinner.classList.remove('active');
        }
    }

    // ── Render results ────────────────────────────────────────────────────────
    function renderResults(attrs) {
        const items = attrs.items ?? [];
        document.getElementById('results-title').textContent =
            `${items.length} items found across ${attrs.image_count} image(s)`;

        const grid = document.getElementById('menu-grid');
        grid.innerHTML = '';
        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'card';
            card.innerHTML = `
                ${item.category ? `<div class="category">${esc(item.category)}</div>` : ''}
                <div class="name">${esc(item.name_en ?? item.original_name ?? '—')}</div>
                ${item.original_name && item.name_en ? `<div class="name-original">${esc(item.original_name)}</div>` : ''}
                ${item.description_en ? `<div class="description">${esc(item.description_en)}</div>` : ''}
                ${item.price ? `<span class="price">${esc(String(item.price))} ${esc(item.currency ?? '')}</span>` : ''}
            `;
            grid.appendChild(card);
        });

        document.getElementById('raw-json').textContent = JSON.stringify(items, null, 2);
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
