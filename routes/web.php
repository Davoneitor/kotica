<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\SalidaController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\RetornablesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TransferenciaController;
use App\Http\Controllers\CamionEscombroController;

Route::get('/', function () {
    return redirect()->route('inventario.index');
});

Route::middleware(['auth', 'solo_explore'])->group(function () {

    // =========================
    // INVENTARIO
    // =========================

Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
Route::post('/inventario/cambiar-obra', [InventarioController::class, 'cambiarObra'])->name('inventario.cambiarObra');
Route::get('/inventario/create', [InventarioController::class, 'create'])->name('inventario.create');
Route::post('/inventario', [InventarioController::class, 'store'])->name('inventario.store');
Route::get('/inventario/{inventario}/edit', [InventarioController::class, 'edit'])->name('inventario.edit');

Route::get('/inventario/buscar-por-insumo', [InventarioController::class, 'buscarPorInsumo'])
    ->name('inventario.buscarPorInsumo');

// ✅ SOLO esta debe llamarse inventario.update
Route::put('/inventario/{inventario}', [InventarioController::class, 'update'])
    ->name('inventario.update');

// ✅ Si quieres PATCH por compatibilidad, CAMBIA EL NOMBRE
Route::patch('/inventario/{inventario}', [InventarioController::class, 'update'])
    ->name('inventario.patch');

Route::delete('/inventario/{inventario}', [InventarioController::class, 'destroy'])->name('inventario.destroy');
Route::get('/inventario/buscar', [InventarioController::class, 'buscar'])->name('inventario.buscar');

    // =========================
    // SALIDAS
    // =========================
    Route::get('/salidas', [SalidaController::class, 'index'])->name('salidas.index');
    Route::get('/salidas/destinos', [SalidaController::class, 'destinos'])->name('salidas.destinos');
    Route::get('/salidas/responsables', [SalidaController::class, 'responsables'])->name('salidas.responsables');
    Route::get('/salidas/buscar-productos', [SalidaController::class, 'buscarProductos'])->name('salidas.buscar');
    Route::post('/salidas', [SalidaController::class, 'store'])->name('salidas.store');

    // ✅ PDF DE SALIDA
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

    Route::get('/explore/salidas/tabla', [ExploreController::class, 'salidasTabla'])->name('explore.salidas_tabla');

    Route::get('/explore/movimientos/{movimiento}/detalles', [ExploreController::class, 'movimientoDetalles'])
        ->name('explore.movimientos.detalles');

    Route::get('/explore/movimientos/{movimiento}/ajuste-detalles', [ExploreController::class, 'detallesParaAjuste'])
        ->name('explore.movimientos.ajuste_detalles');
    Route::post('/explore/movimientos/{movimiento}/ajustar', [ExploreController::class, 'ajustarSalida'])
        ->name('explore.movimientos.ajustar');
    Route::get('/explore/ajustes', [ExploreController::class, 'historialAjustes'])
        ->name('explore.ajustes');

    Route::get('/explore/inventario', [ExploreController::class, 'inventario'])->name('explore.inventario');

    // ✅ Foto directa (si la estás usando)
    Route::get('/explore/entradas/{id}/foto', [ExploreController::class, 'entradaFoto'])
        ->name('explore.entradas.foto');

    // ✅ ENTRADAS (Recepciones de OC)
    Route::get('/explore/entradas', [ExploreController::class, 'entradas'])->name('explore.entradas');

    Route::get('/explore/entradas/{id}/detalles', [ExploreController::class, 'entradaDetalles'])
        ->name('explore.entradas.detalles');

    Route::get('/explore/ordenes-compra', [ExploreController::class, 'ordenesCompra'])
        ->name('explore.ordenes_compra');

    Route::get('/explore/graficas', [ExploreController::class, 'graficas'])
        ->name('explore.graficas');

    // ⚠️ Solo deja esta ruta si existe el método retornables() en ExploreController
    Route::get('/explore/retornables', [ExploreController::class, 'retornables'])
        ->name('explore.retornables');

    Route::get('/explore/ordenes-compra/reporte-pdf', [ExploreController::class, 'ordenesCompraReportePdf'])
        ->name('explore.ordenes_compra_reporte_pdf');

    // ── Exportaciones Excel ──────────────────────────────────────────────
    Route::get('/explore/exportar/entradas',        [ExploreController::class, 'exportarEntradas'])       ->name('explore.exportar.entradas');
    Route::get('/explore/exportar/salidas',         [ExploreController::class, 'exportarSalidas'])        ->name('explore.exportar.salidas');
    Route::get('/explore/exportar/inventario',      [ExploreController::class, 'exportarInventario'])     ->name('explore.exportar.inventario');
    Route::get('/explore/exportar/transferencias',  [ExploreController::class, 'exportarTransferencias']) ->name('explore.exportar.transferencias');

    // Transferencias en Explore
    Route::get('/explore/transferencias', [ExploreController::class, 'transferencias'])
        ->name('explore.transferencias');
    Route::get('/explore/transferencias/{id}/detalles', [ExploreController::class, 'transferenciaDetalles'])
        ->name('explore.transferencias.detalles');
    Route::get('/transferencias/{id}/pdf', [ExploreController::class, 'transferenciaPdf'])
        ->name('transferencias.pdf');

    // =========================
    // RETORNABLES
    // =========================
    Route::get('/retornables', [RetornablesController::class, 'index'])
        ->name('retornables.index');

    Route::post('/retornables/{detalle}/recuperar', [RetornablesController::class, 'recuperar'])
        ->name('retornables.recuperar');

    // =========================
    // USUARIOS (CRUD admin)
    // =========================
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('/usuarios/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // =========================
    // TRANSFERENCIA ENTRE OBRAS
    // =========================
    Route::get('/transferencias', [TransferenciaController::class, 'index'])->name('transferencias.index');
    Route::get('/transferencias/buscar', [TransferenciaController::class, 'buscar'])->name('transferencias.buscar');
    Route::get('/transferencias/stock-destino', [TransferenciaController::class, 'stockDestino'])->name('transferencias.stockDestino');
    Route::post('/transferencias', [TransferenciaController::class, 'store'])->name('transferencias.store');

    // =========================
    // CONTROL DE CAMIONES
    // =========================
    Route::get('/control-camiones',                 [CamionEscombroController::class, 'index'])    ->name('control-camiones.index');
    Route::post('/control-camiones',                [CamionEscombroController::class, 'store'])    ->name('control-camiones.store');
    Route::get('/control-camiones/catalogos',       [CamionEscombroController::class, 'catalogos'])->name('control-camiones.catalogos');
    Route::get('/control-camiones/chofer-info',     [CamionEscombroController::class, 'choferInfo'])->name('control-camiones.choferInfo');
    Route::get('/control-camiones/total-dia',       [CamionEscombroController::class, 'totalDia']) ->name('control-camiones.totalDia');
    Route::get('/control-camiones/explore',         [CamionEscombroController::class, 'explore'])  ->name('control-camiones.explore');
    Route::get('/control-camiones/exportar',        [CamionEscombroController::class, 'exportar']) ->name('control-camiones.exportar');
    Route::get('/control-camiones/pdf',             [CamionEscombroController::class, 'pdf'])      ->name('control-camiones.pdf');
    Route::get('/control-camiones/{id}/foto/{tipo}',[CamionEscombroController::class, 'foto'])     ->name('control-camiones.foto');

    // =========================
    // PROFILE
    // =========================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Ruta para renovar el token CSRF sin recargar la página
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web')->name('csrf.token');

require __DIR__ . '/auth.php';
