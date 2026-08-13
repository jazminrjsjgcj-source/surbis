<?php

declare(strict_types=1);

use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OrganizationChoiceController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\SecondFactorController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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

/*
 * Verificacion del segundo factor. RF-AUT-007, 014 y 015.
 *
 * NO va detras de 'auth': en este punto el usuario todavia no tiene sesion.
 * La puerta es 'pending', que exige un identificador pendiente en la sesion
 * parcial.
 */
Route::middleware('pending')->group(function (): void {
    Route::get('/verificacion', [SecondFactorController::class, 'create'])
        ->name('auth.second-factor.challenge');
    Route::post('/verificacion', [SecondFactorController::class, 'store']);
    Route::post('/verificacion/reenviar', [SecondFactorController::class, 'resend'])
        ->name('auth.second-factor.resend');
    Route::post('/verificacion/cancelar', [SecondFactorController::class, 'destroy'])
        ->name('auth.second-factor.cancel');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    /*
     * Seguridad de la cuenta. Acordada en P-011: sin ella nadie puede activar
     * el segundo factor y la pantalla de verificacion seria inalcanzable.
     *
     * Es de la cuenta, no de la organizacion, asi que no lleva el middleware
     * 'organization': un administrador de plataforma tambien necesita entrar.
     */
    Route::get('/cuenta/seguridad', [SecurityController::class, 'show'])
        ->name('account.security');
    Route::post('/cuenta/seguridad/mfa', [SecurityController::class, 'enable'])
        ->name('account.security.enable');
    Route::delete('/cuenta/seguridad/mfa', [SecurityController::class, 'disable'])
        ->name('account.security.disable');
    Route::post('/cuenta/seguridad/codigos', [SecurityController::class, 'regenerate'])
        ->name('account.security.codes');

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
