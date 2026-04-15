<?php

use App\Http\Controllers\CamionMovilController;
use App\Http\Controllers\InventarioMovilController;
use App\Http\Controllers\RetornableMovilController;
use App\Http\Controllers\TransferenciaMovilController;
use App\Http\Controllers\EntradaController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\SalidaController;
use App\Http\Controllers\ExploreMovilController;
use App\Http\Controllers\GraficasMovilController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// ── Auth móvil (público) ────────────────────────────────────────────────────
Route::post('/mobile/login', [MobileAuthController::class, 'login']);

// ── Rutas protegidas ────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/mobile/logout',   [MobileAuthController::class, 'logout']);
    Route::get('/mobile/me',        [MobileAuthController::class, 'me']);
    Route::get('/mobile/obras',     [MobileAuthController::class, 'obras']);
    Route::put('/mobile/obra',      [MobileAuthController::class, 'cambiarObra']);

    // Salidas
    Route::get('/salidas/destinos',         [SalidaController::class, 'destinos']);
    Route::get('/salidas/responsables',     [SalidaController::class, 'responsablesMovil']);
    Route::get('/salidas/buscar-productos',  [SalidaController::class, 'buscarProductos']);
    Route::get('/salidas/catalogo',          [SalidaController::class, 'catalogoProductos']);
    Route::post('/salidas',                 [SalidaController::class, 'store']);

    // Entradas (Órdenes de Compra)
    Route::get('/entradas/ordenes-compra',  [EntradaController::class, 'index']);
    Route::post('/entradas/recibir',        [EntradaController::class, 'recibir']);

    // Inventario
    Route::get('/inventario',        [InventarioMovilController::class, 'index']);
    Route::put('/inventario/{id}',   [InventarioMovilController::class, 'update']);
    Route::delete('/inventario/{id}',[InventarioMovilController::class, 'destroy']);

    // Retornables
    Route::get('/retornables',                        [RetornableMovilController::class, 'index']);
    Route::post('/retornables/{detalle}/recuperar',   [RetornableMovilController::class, 'recuperar']);

    // Transferencias entre obras
    Route::get('/transferencias/obras',  [TransferenciaMovilController::class, 'obras']);
    Route::get('/transferencias/buscar', [TransferenciaMovilController::class, 'buscar']);
    Route::post('/transferencias',       [TransferenciaMovilController::class, 'store']);

    // Camiones escombro
    Route::get('/camiones/hoy',    [CamionMovilController::class, 'hoy']);
    Route::get('/camiones/placas', [CamionMovilController::class, 'placas']);
    Route::post('/camiones',       [CamionMovilController::class, 'store']);

    // Gráficas (legacy)
    Route::get('/graficas', [GraficasMovilController::class, 'index']);

    // Explore (módulo completo)
    Route::get('/explore/movimientos',                  [ExploreMovilController::class, 'movimientos']);
    Route::get('/explore/movimientos/{id}/detalles',    [ExploreMovilController::class, 'movimientoDetalles']);
    Route::get('/explore/entradas',                     [ExploreMovilController::class, 'entradas']);
    Route::get('/explore/entradas/{id}/detalles',       [ExploreMovilController::class, 'entradaDetalles']);
    Route::get('/explore/inventario',                   [ExploreMovilController::class, 'inventario']);
    Route::get('/explore/transferencias',               [ExploreMovilController::class, 'transferencias']);
    Route::get('/explore/transferencias/{id}/detalles', [ExploreMovilController::class, 'transferenciaDetalles']);
    Route::get('/explore/ordenes-compra',               [ExploreMovilController::class, 'ordenesCompra']);
    Route::get('/explore/graficas',                     [ExploreMovilController::class, 'graficas']);

    // Gestión de usuarios (admin / multiobra)
    Route::get('/mobile/obras-lista',              [UserManagementController::class, 'obrasList']);
    Route::get('/mobile/usuarios',                 [UserManagementController::class, 'index']);
    Route::post('/mobile/usuarios',                [UserManagementController::class, 'store']);
    Route::put('/mobile/usuarios/{id}',            [UserManagementController::class, 'update']);
    Route::put('/mobile/usuarios/{id}/estatus',    [UserManagementController::class, 'toggleEstatus']);
    Route::delete('/mobile/usuarios/{id}',         [UserManagementController::class, 'destroy']);
});
