<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Si el usuario no tiene obra actual asignada, tomar la primera de obra_user
        if ($user && is_null($user->obra_actual_id)) {
            $primeraObra = $user->obras()->orderBy('obra_id')->first();
            if ($primeraObra) {
                $user->obra_actual_id = $primeraObra->id;
                $user->save();
            }
        }

        // Redirigir al módulo correspondiente según el rol
        if ($user?->isOperadorCamiones()) {
            return redirect()->route('control-camiones.index');
        }

        if ($user?->solo_explore) {
            return redirect()->route('explore.index');
        }

        return redirect()->intended(route('inventario.index'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
