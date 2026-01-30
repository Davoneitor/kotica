<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\SalidaController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\RetornablesController;

Route::get('/', function () {
    return redirect()->route('inventario.index');
});

Route::middleware('auth')->group(function () {

    // =========================
    // INVENTARIO
    // =========================
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/create', [InventarioController::class, 'create'])->name('inventario.create');
    Route::post('/inventario', [InventarioController::class, 'store'])->name('inventario.store');
    Route::get('/inventario/{inventario}/edit', [InventarioController::class, 'edit'])->name('inventario.edit');
    Route::put('/inventario/{inventario}', [InventarioController::class, 'update'])->name('inventario.update');
    Route::delete('/inventario/{inventario}', [InventarioController::class, 'destroy'])->name('inventario.destroy');
    Route::get('/inventario/buscar', [InventarioController::class, 'buscar'])->name('inventario.buscar');

    // =========================
    // SALIDAS
    // =========================
    Route::get('/salidas/destinos', [SalidaController::class, 'destinos'])->name('salidas.destinos');
    Route::get('/salidas/buscar-productos', [SalidaController::class, 'buscarProductos'])->name('salidas.buscar');
    Route::post('/salidas', [SalidaController::class, 'store'])->name('salidas.store');

    // ✅ PDF DE SALIDA (RUTA QUE FALTABA)
    Route::get('/salidas/{movimiento}/pdf', [SalidaController::class, 'pdf'])
        ->name('salidas.pdf');

    // 🔁 Compatibilidad si ya existía en código viejo
    Route::get('/movimientos/{movimiento}/pdf', [SalidaController::class, 'pdf'])
        ->name('movimientos.pdf');

    // =========================
    // ÓRDENES DE COMPRA (ERP)
    // =========================
    Route::get('/ordenes-compra', [OrdenCompraController::class, 'index'])
        ->name('ordenes-compra.index');

    Route::post('/ordenes-compra/recibir', [OrdenCompraController::class, 'recibir'])
        ->name('ordenes-compra.recibir');

    // =========================
    // EXPLORE
    // =========================
    Route::get('/explore', [ExploreController::class, 'index'])->name('explore.index');
    Route::get('/explore/movimientos', [ExploreController::class, 'movimientos'])->name('explore.movimientos');

    Route::get('/explore/movimientos/{movimiento}/detalles', [ExploreController::class, 'movimientoDetalles'])
        ->name('explore.movimiento_detalles');

    Route::get('/explore/inventario', [ExploreController::class, 'inventario'])->name('explore.inventario');
    Route::get('/explore/ordenes-compra', [ExploreController::class, 'ordenesCompra'])->name('explore.ordenes_compra');
    Route::get('/explore/graficas', [ExploreController::class, 'graficas'])->name('explore.graficas');
    Route::get('/explore/retornables', [ExploreController::class, 'retornables'])->name('explore.retornables');

    Route::get('/explore/ordenes-compra/reporte-pdf', [ExploreController::class, 'ordenesCompraReportePdf'])
        ->name('explore.ordenes_compra_reporte_pdf');

    // =========================
    // RETORNABLES
    // =========================
    Route::get('/retornables', [RetornablesController::class, 'index'])
        ->name('retornables.index');

    Route::post('/retornables/{detalle}/recuperar', [RetornablesController::class, 'recuperar'])
        ->name('retornables.recuperar');

    // =========================
    // PROFILE
    // =========================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
