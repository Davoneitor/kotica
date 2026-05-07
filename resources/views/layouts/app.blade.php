{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'kotica v1.0') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <script>
        if (localStorage.getItem('modoHerramientas') === '1') {
            document.documentElement.classList.add('modo-herramientas');
        }
    </script>
    <style>
        /* ── BASE ───────────────────────────────────────────────────── */
        html.modo-herramientas,
        html.modo-herramientas body,
        html.modo-herramientas .min-h-screen,
        html.modo-herramientas .bg-gray-100 {
            background: #0d0d14 !important;
            color: #e8e8f0 !important;
        }

        /* ── NAV ────────────────────────────────────────────────────── */
        html.modo-herramientas nav {
            background: #12121c !important;
            border-bottom: 1px solid #f59e0b55 !important;
            box-shadow: 0 1px 20px rgba(245,158,11,.12) !important;
        }
        html.modo-herramientas nav a { color: #cbd5e1 !important; }
        html.modo-herramientas nav a:hover { color: #f59e0b !important; }
        html.modo-herramientas nav span { color: #94a3b8 !important; }

        /* Logo con anillo activo */
        html.modo-herramientas #logo-kotica {
            filter: drop-shadow(0 0 6px #f59e0b) drop-shadow(0 0 14px #f59e0b88);
            border-radius: 6px;
        }

        /* ── PAGE HEADER ────────────────────────────────────────────── */
        html.modo-herramientas header.bg-white,
        html.modo-herramientas .bg-white {
            background: #181824 !important;
            border-color: #2a2a3e !important;
        }
        html.modo-herramientas .shadow,
        html.modo-herramientas .shadow-sm {
            box-shadow: 0 1px 8px rgba(0,0,0,.6) !important;
        }

        /* ── TEXT ───────────────────────────────────────────────────── */
        html.modo-herramientas h1,
        html.modo-herramientas h2,
        html.modo-herramientas h3,
        html.modo-herramientas p,
        html.modo-herramientas label,
        html.modo-herramientas td,
        html.modo-herramientas th,
        html.modo-herramientas li { color: #dde1ef !important; }
        html.modo-herramientas .text-gray-500,
        html.modo-herramientas .text-gray-400 { color: #7878a0 !important; }
        html.modo-herramientas .text-gray-700,
        html.modo-herramientas .text-gray-800,
        html.modo-herramientas .text-gray-900 { color: #c8cce0 !important; }

        /* ── TABLES ─────────────────────────────────────────────────── */
        html.modo-herramientas table { border-color: #2a2a3e !important; }
        html.modo-herramientas thead th {
            background: #1e1e2e !important;
            color: #a0a8c8 !important;
            border-color: #2e2e46 !important;
        }
        html.modo-herramientas tbody tr { border-color: #22223a !important; }
        html.modo-herramientas tbody tr:hover { background: #1c1c2c !important; }

        /* ── INPUTS / SELECT ────────────────────────────────────────── */
        html.modo-herramientas input,
        html.modo-herramientas select,
        html.modo-herramientas textarea {
            background: #1e1e2e !important;
            border-color: #3a3a5a !important;
            color: #dde1ef !important;
        }
        html.modo-herramientas input::placeholder { color: #55557a !important; }

        /* ── BORDERS ────────────────────────────────────────────────── */
        html.modo-herramientas .border,
        html.modo-herramientas .border-gray-200,
        html.modo-herramientas .border-gray-300 { border-color: #2a2a42 !important; }
        html.modo-herramientas .divide-y > * { border-color: #22223a !important; }

        /* ── CARDS / PANELS ─────────────────────────────────────────── */
        html.modo-herramientas .rounded-lg,
        html.modo-herramientas .sm\\:rounded-lg { background: #181824; }

        /* ── FILAS ESPECIALES ───────────────────────────────────────── */
        html.modo-herramientas tr.row-obsoleto {
            background: #1e1500 !important;
            border-left: 3px solid #b45309 !important;
        }
        html.modo-herramientas tr.row-obsoleto td { color: #c0922a !important; }
        html.modo-herramientas tr.row-obsoleto td:first-child { opacity: .7; }

        html.modo-herramientas tr.row-highlight {
            background: #0a1f0e !important;
            border-left: 3px solid #16a34a !important;
        }
        html.modo-herramientas tr.row-highlight td { color: #4ade80 !important; }

        /* Badge OBSOLETO */
        html.modo-herramientas .bg-yellow-200 {
            background: #3d2600 !important;
            border-color: #7c4a00 !important;
        }
        html.modo-herramientas .text-yellow-800 { color: #fbbf24 !important; }
        html.modo-herramientas .border-yellow-300 { border-color: #7c4a00 !important; }

        /* Banner obsoleto */
        html.modo-herramientas .bg-yellow-50  { background: #1c1000 !important; }
        html.modo-herramientas .text-yellow-700 { color: #f59e0b !important; }
        html.modo-herramientas .bg-amber-100  { background: #1c1000 !important; }
        html.modo-herramientas .text-amber-900 { color: #fbbf24 !important; }

        /* ── BANNER ─────────────────────────────────────────────────── */
        .banner-modo-herramientas { display: none; }
        html.modo-herramientas .banner-modo-herramientas { display: flex; }

        @keyframes mh-pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .4; }
        }
        .mh-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #f59e0b;
            animation: mh-pulse 1.6s ease-in-out infinite;
            flex-shrink: 0;
        }
    </style>
    <body class="font-sans antialiased">
        <div class="banner-modo-herramientas"
             style="background:linear-gradient(90deg,#0f0f1a 0%,#1a1400 50%,#0f0f1a 100%);
                    border-bottom:1px solid #f59e0b44;
                    color:#fbbf24;padding:7px 20px;
                    font-size:12px;font-weight:700;
                    align-items:center;gap:10px;
                    letter-spacing:.08em;text-transform:uppercase;">
            <span class="mh-dot"></span>
            🔧 Modo herramientas activo
            <span style="color:#6b6b40;font-weight:400;font-size:11px;letter-spacing:.02em;text-transform:none;">
                — Presiona el logo para salir
            </span>
        </div>
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            {{-- Page Heading --}}
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            {{-- Page Content --}}
            <main>
                {{ $slot }}
            </main>
        </div>
    <script>
    // En modo herramientas: quitar inline background de filas para que CSS de clase aplique
    if (localStorage.getItem('modoHerramientas') === '1') {
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('tr.row-obsoleto, tr.row-highlight').forEach(function (tr) {
                tr.style.removeProperty('background-color');
                tr.style.removeProperty('color');
            });
        });
    }
    </script>
    <script>
    /**
     * fetchConCsrf(url, options)
     * Igual que fetch() pero si recibe 419 renueva el token automáticamente y reintenta una vez.
     */
    window.fetchConCsrf = async function(url, options = {}) {
        const getCsrf = () => document.querySelector('meta[name="csrf-token"]').content;

        // Asegura que el token viaje en headers y en body (FormData)
        const setToken = (opts, token) => {
            opts.headers = { ...(opts.headers || {}), 'X-CSRF-TOKEN': token };
            if (opts.body instanceof FormData) {
                opts.body.set('_token', token);
            }
            return opts;
        };

        let res = await fetch(url, setToken({ ...options }, getCsrf()));

        if (res.status === 419) {
            // Renovar token
            try {
                const r = await fetch('/csrf-token', { headers: { 'Accept': 'application/json' } });
                if (r.ok) {
                    const data = await r.json();
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.token);
                    // Reintentar con el token fresco
                    res = await fetch(url, setToken({ ...options }, data.token));
                }
            } catch (_) { /* red caída: devuelve el 419 original */ }
        }

        return res;
    };
    </script>
    </body>
</html>
