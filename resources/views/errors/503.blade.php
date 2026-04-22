{{-- Standalone page: the app is down, so layouts and Vite assets are unavailable --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Manutenzione in corso — Fatturino</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            color: #1f2937;
            padding: 1.5rem;
        }
        .icon { margin-bottom: 2rem; }
        .code { font-size: 6rem; font-weight: 800; color: #f59e0b; line-height: 1; }
        h1 { margin-top: 1rem; font-size: 1.5rem; font-weight: 700; }
        p { margin-top: 0.75rem; color: #6b7280; max-width: 28rem; text-align: center; }
        .hint { margin-top: 0.5rem; font-size: 0.8125rem; color: #9ca3af; }
        button {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.625rem 1.5rem;
            background: #f59e0b;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.875rem;
        }
        button:hover { background: #d97706; }
    </style>
</head>
<body>
    {{-- Receipt-shaped icon with clock symbol --}}
    <div class="icon">
        <svg width="80" height="96" viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 3C2 1.34 3.34 0 5 0H23C24.66 0 26 1.34 26 3V29L23 27L20 29L17 27L14 29L11 27L8 29L5 27L2 29V3Z" fill="#e5e7eb"/>
            {{-- Clock icon (circle + hands) --}}
            <circle cx="14" cy="13" r="7" stroke="#f59e0b" stroke-width="1.8" fill="none"/>
            <line x1="14" y1="13" x2="14" y2="9" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round"/>
            <line x1="14" y1="13" x2="17" y2="15" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
    </div>

    <p class="code">Ouch!</p>
    <h1>Manutenzione in corso</h1>
    <p>Stiamo aggiornando il sistema. Torneremo operativi a breve.</p>
    <p class="hint">La pagina si aggiorna automaticamente ogni 30 secondi.</p>
    <button onclick="location.reload()">Riprova</button>
</body>
</html>
