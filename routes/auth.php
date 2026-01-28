<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\InventarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas para invitados (NO logueados)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {

    // Login
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Recuperar contraseña
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Rutas para usuarios autenticados (TODOS)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Verificación de email (Breeze)
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Confirmar / cambiar password
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])
        ->name('password.update');

    // Logout (cualquier usuario logueado)
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    /*
    |--------------------------------------------------------------------------
    | MÓDULO INVENTARIO (usuarios autenticados)
    |--------------------------------------------------------------------------
    */
    Route::get('inventario', [\App\Http\Controllers\InventarioController::class, 'index'])
    ->name('inventario.index');

Route::patch('inventario/{inventario}', [\App\Http\Controllers\InventarioController::class, 'update'])
    ->name('inventario.update');

    //boton de crear producto
     Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::post('/inventario', [InventarioController::class, 'store'])->name('inventario.store');
    Route::patch('/inventario/{inventario}', [InventarioController::class, 'update'])->name('inventario.update');


    /*
    |--------------------------------------------------------------------------
    | Rutas SOLO para ADMIN
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {

        // Registrar usuarios
        Route::get('register', [RegisteredUserController::class, 'create'])
            ->name('register');

        Route::post('register', [RegisteredUserController::class, 'store']);
    });
});
