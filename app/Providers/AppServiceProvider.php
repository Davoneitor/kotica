<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View; // ✅ ESTA ES LA BUENA
use Illuminate\Support\Facades\Auth;
use App\Models\Inventario;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.navigation', function ($view) {
            $inventarios = collect();

            if (Auth::check()) {
                $obraId = session('obra_id'); // ajusta a tu proyecto

                if ($obraId) {
                    $inventarios = Inventario::where('obra_id', $obraId)
                        ->orderByDesc('updated_at')
                        ->take(20)
                        ->get();
                }
            }

            $view->with('inventarios', $inventarios);
        });
    }
}
