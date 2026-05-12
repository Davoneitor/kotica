<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Restringe el acceso según el rol del usuario:
 *
 *  - solo_explore      → solo rutas de Explore, Profile y endpoints de datos relacionados
 *  - operador_camiones → solo rutas de Control Camiones y Profile
 *
 * Cualquier otra ruta redirige al módulo correspondiente.
 */
class RestrictSoloExplore
{
    private const EXPLORE_PREFIXES = [
        'explore.',
        'profile.',
    ];

    private const EXPLORE_ROUTES = [
        'logout',
        'transferencias.pdf',
        'control-camiones.explore',
        'control-camiones.foto',
        'control-camiones.exportar',
        'control-camiones.pdf',
        'salidas.pdf',
        'movimientos.pdf',
    ];

    private const CAMIONES_PREFIXES = [
        'profile.',
    ];

    private const CAMIONES_ROUTES = [
        'logout',
        'control-camiones.index',
        'control-camiones.store',
        'control-camiones.catalogos',
        'control-camiones.choferInfo',
        'control-camiones.totalDia',
        'control-camiones.explore',
        'control-camiones.exportar',
        'control-camiones.pdf',
        'control-camiones.foto',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isOperadorCamiones()) {
            $routeName = $request->route()?->getName() ?? '';

            $allowed = Str::startsWith($routeName, self::CAMIONES_PREFIXES)
                    || in_array($routeName, self::CAMIONES_ROUTES, true);

            if (! $allowed) {
                return redirect()->route('control-camiones.index');
            }

            return $next($request);
        }

        if ($user->solo_explore) {
            $routeName = $request->route()?->getName() ?? '';

            $allowed = Str::startsWith($routeName, self::EXPLORE_PREFIXES)
                    || in_array($routeName, self::EXPLORE_ROUTES, true);

            if (! $allowed) {
                return redirect()->route('explore.index');
            }
        }

        return $next($request);
    }
}
