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

    <body class="font-sans antialiased">
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
