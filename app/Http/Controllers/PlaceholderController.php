<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Marcador de modulos que todavia no existen.
 *
 * Existe para que la redireccion por rol de RF-AUT-003 tenga un destino real
 * que probar: una prueba que espera un 404 no prueba lo que dice probar.
 *
 * Sustituye a Route::view(), que servia una plantilla Blade. Con la
 * conversion a React ya no queda ninguna vista que servir, y mantener una
 * sola para esto obligaba a conservar todo el layout de Blade.
 */
final class PlaceholderController extends Controller
{
    public function __invoke(string $module): InertiaResponse
    {
        return Inertia::render('Placeholder', [
            'module' => $module,
            'logoutUrl' => route('logout'),
        ]);
    }
}
