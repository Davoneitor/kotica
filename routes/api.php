<?php

use App\Http\Controllers\EntradaController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\SalidaController;
use Illuminate\Support\Facades\Route;

// ── Auth móvil (público) ────────────────────────────────────────────────────
Route::post('/mobile/login', [MobileAuthController::class, 'login']);

// ── Rutas protegidas ────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/mobile/logout', [MobileAuthController::class, 'logout']);
    Route::get('/mobile/me',     [MobileAuthController::class, 'me']);

    // Salidas
    Route::get('/salidas/destinos',         [SalidaController::class, 'destinos']);
    Route::get('/salidas/responsables',     [SalidaController::class, 'responsablesMovil']);
    Route::get('/salidas/buscar-productos',  [SalidaController::class, 'buscarProductos']);
    Route::get('/salidas/catalogo',          [SalidaController::class, 'catalogoProductos']);
    Route::post('/salidas',                 [SalidaController::class, 'store']);

    // Entradas (Órdenes de Compra)
    Route::get('/entradas/ordenes-compra',  [EntradaController::class, 'index']);
    Route::post('/entradas/recibir',        [EntradaController::class, 'recibir']);
});
