<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Analyzer</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f5f5f5; padding: 2rem; }
        h1 { margin-bottom: 1.5rem; font-size: 1.5rem; color: #222; }
        form { display: flex; gap: .75rem; margin-bottom: 2rem; }
        input[type="url"] {
            flex: 1; padding: .6rem 1rem; border: 1px solid #ccc;
            border-radius: 6px; font-size: 1rem;
        }
        button {
            padding: .6rem 1.5rem; background: #1a56db; color: #fff;
            border: none; border-radius: 6px; font-size: 1rem; cursor: pointer;
        }
        button:hover { background: #1e429f; }
        button:disabled { background: #93c5fd; cursor: not-allowed; }
        .spinner {
            display: none; align-items: center; gap: .6rem;
            color: #555; margin-bottom: 1.5rem; font-size: .95rem;
        }
        .spinner.active { display: flex; }
        .spinner svg { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .error { color: #c00; margin-bottom: 1rem; }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
        }
        .card {
            background: #fff; border-radius: 8px; padding: 1.25rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.1);
        }
        .card .category {
            font-size: .75rem; font-weight: 600; text-transform: uppercase;
            color: #888; margin-bottom: .4rem; letter-spacing: .05em;
        }
        .card .name { font-size: 1.1rem; font-weight: 700; color: #111; margin-bottom: .2rem; }
        .card .name-original { font-size: .85rem; color: #666; margin-bottom: .6rem; }
        .card .description { font-size: .9rem; color: #444; line-height: 1.5; margin-bottom: .75rem; }
        .card .price {
            display: inline-block; font-weight: 700; color: #1a56db;
            background: #eff6ff; padding: .25rem .75rem; border-radius: 99px; font-size: .95rem;
        }
        .raw { margin-top: 2rem; }
        .raw summary { cursor: pointer; color: #666; font-size: .85rem; margin-bottom: .5rem; }
        pre {
            background: #1e1e1e; color: #d4d4d4; padding: 1rem;
            border-radius: 6px; overflow-x: auto; font-size: .8rem; line-height: 1.5;
        }
    </style>
</head>
<body>

<h1>Menu Analyzer</h1>

<form method="POST" action="{{ route('test-menu') }}" id="form">
    @csrf
    <input type="url" name="image_url" placeholder="https://example.com/menu.jpg"
           value="{{ old('image_url') }}" required autofocus>
    <button type="submit" id="btn">Analyze</button>
</form>

<div class="spinner" id="spinner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a56db" stroke-width="2.5">
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
    </svg>
    Analyzing menu, please wait…
</div>

<script>
    document.getElementById('form').addEventListener('submit', function () {
        document.getElementById('spinner').classList.add('active');
        document.getElementById('btn').disabled = true;
        document.getElementById('btn').textContent = 'Analyzing…';
    });
</script>

@if ($errors->any())
    <p class="error">{{ $errors->first() }}</p>
@endif

@if (isset($error))
    <p class="error">{{ $error }}</p>
@endif

@if (isset($items) && count($items))
    <div class="menu-grid">
        @foreach ($items as $item)
            <div class="card">
                @if (!empty($item['category']))
                    <div class="category">{{ $item['category'] }}</div>
                @endif
                <div class="name">{{ $item['name_en'] ?? $item['original_name'] ?? '—' }}</div>
                @if (!empty($item['original_name']))
                    <div class="name-original">{{ $item['original_name'] }}</div>
                @endif
                @if (!empty($item['description_en']))
                    <div class="description">{{ $item['description_en'] }}</div>
                @endif
                @if (!empty($item['price']))
                    <span class="price">{{ $item['price'] }} {{ $item['currency'] ?? '' }}</span>
                @endif
            </div>
        @endforeach
    </div>

    <details class="raw">
        <summary>Raw JSON</summary>
        <pre>{{ json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </details>
@endif

</body>
</html>
