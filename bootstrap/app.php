<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Middleware\EnsureMembershipRole;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * AuthenticateSession compara en cada peticion el hash de contrasena
         * guardado en la sesion con el actual del usuario. Cuando alguien
         * cambia su contrasena, las sesiones abiertas en otros dispositivos
         * dejan de coincidir y se cierran solas.
         *
         * Es lo que hace cumplible RF-AUT-013 sin escribir un mecanismo
         * propio para rastrear sesiones.
         */
        $middleware->web(append: [
            AuthenticateSession::class,
        ]);

        $middleware->alias([
            'organization' => EnsureActiveOrganization::class,
            'role' => EnsureMembershipRole::class,
            'platform' => EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
