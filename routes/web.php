<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OrganizationChoiceController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
 * Autenticacion. RF-AUT-001 a 006.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    /*
     * Recuperacion de contrasena. RF-AUT-008 a 013.
     *
     * Los nombres password.request, password.email, password.reset y
     * password.store son los que Laravel espera: la notificacion que envia
     * el broker construye la liga con route('password.reset'). Renombrarlos
     * romperia el correo sin que ninguna prueba de rutas lo notara.
     */
    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/organizaciones', [OrganizationChoiceController::class, 'create'])
        ->name('auth.organizations.choose');
    Route::post('/organizaciones', [OrganizationChoiceController::class, 'store']);
});

/*
 * Destinos de RF-AUT-003.
 *
 * Son marcadores deliberados: cada uno se sustituye por su modulo en la fase
 * que le toca. Existen ahora porque la redireccion por rol no se puede
 * probar contra rutas inexistentes, y una prueba que espera un 404 no prueba
 * lo que dice probar.
 */
Route::middleware(['auth', 'platform'])->group(function (): void {
    Route::view('/platform', 'placeholder.module', ['module' => 'Panel de plataforma'])
        ->name('platform.dashboard');
});

Route::middleware(['auth', 'organization'])->group(function (): void {
    Route::middleware('role:admin')->group(function (): void {
        Route::view('/admin', 'placeholder.module', ['module' => 'Panel de organizacion'])
            ->name('admin.dashboard');
    });

    Route::middleware('role:collaborator')->group(function (): void {
        Route::view('/kiosk/start', 'placeholder.module', ['module' => 'Preparacion de quiosco'])
            ->name('kiosk.start');
    });
});
