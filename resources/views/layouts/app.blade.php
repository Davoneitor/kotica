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
             style="background:linear-gradient(90deg,#fef9c3 0%,#fef3c7 50%,#fef9c3 100%);
                    border-bottom:1px solid #f59e0b88;
                    color:#92400e;padding:7px 20px;
                    font-size:12px;font-weight:700;
                    align-items:center;gap:10px;
                    letter-spacing:.08em;text-transform:uppercase;">
            <span class="mh-dot"></span>
            🔧 Modo herramientas activo
            <span style="color:#b45309;font-weight:400;font-size:11px;letter-spacing:.02em;text-transform:none;">
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
