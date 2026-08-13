<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Identity\PendingSecondFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege la pantalla de verificacion.
 *
 * Sin este middleware, /verificacion seria alcanzable por cualquiera: no
 * esta detras de `auth` —el usuario todavia no tiene sesion— asi que la
 * unica puerta es que exista un identificador pendiente en la sesion.
 */
final class EnsurePendingSecondFactor
{
    public function __construct(private readonly PendingSecondFactor $pending) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->pending->user() === null) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
