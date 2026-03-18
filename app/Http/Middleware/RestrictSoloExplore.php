<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Si el usuario tiene solo_explore = true, solo puede acceder a:
 *   - Rutas que comienzan con "explore."
 *   - Rutas que comienzan con "profile."
 *   - Endpoints de datos que el módulo Explore consume directamente
 *   - logout
 *
 * Cualquier otra ruta redirige al inicio de Explore.
 */
class RestrictSoloExplore
{
    /**
     * Prefijos de rutas permitidos para usuarios Solo Explore.
     */
    private const ALLOWED_PREFIXES = [
        'explore.',
        'profile.',
    ];

    /**
     * Rutas exactas adicionales que Explore consume como endpoints de datos.
     */
    private const ALLOWED_ROUTES = [
        'logout',
        // PDF y datos de transferencias (usados desde Explore)
        'transferencias.pdf',
        // Endpoints de Control Salida Camiones usados por la pestaña Explore
        'control-camiones.explore',
        'control-camiones.foto',
        'control-camiones.exportar',
        'control-camiones.pdf',
        // PDF de salida (usado desde Explore)
        'salidas.pdf',
        'movimientos.pdf',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();

        if ($user?->solo_explore) {
            $routeName = $request->route()?->getName() ?? '';

            $isAllowed = Str::startsWith($routeName, self::ALLOWED_PREFIXES)
                      || in_array($routeName, self::ALLOWED_ROUTES, true);

            if (! $isAllowed) {
                return redirect()->route('explore.index');
            }
        }

        return $next($request);
    }
}
