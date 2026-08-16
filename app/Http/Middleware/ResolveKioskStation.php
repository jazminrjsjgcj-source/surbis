<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Application\Kiosk\LinkStation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la tableta desde su cookie en cada peticion.
 *
 * EN CADA PETICION, no solo al vincular. Es lo que hace que revocar una
 * credencial apague la tableta en el momento siguiente: si se comprobara
 * una vez y se guardara en sesion, una tableta perdida seguiria enviando
 * respuestas hasta que alguien la apagara a mano.
 */
final class ResolveKioskStation
{
    /** El nombre de la cookie. Un ano, cifrada por Laravel. */
    public const COOKIE = 'kiosk_station';

    /** Donde queda el dispositivo para quien lo necesite despues. */
    public const REQUEST_ATTRIBUTE = 'kiosk.device';

    public function __construct(private readonly LinkStation $stations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::COOKIE);

        if (is_string($token) && $token !== '') {
            try {
                $request->attributes->set(
                    self::REQUEST_ATTRIBUTE,
                    $this->stations->resolve($token)
                );
            } catch (StationNotReady) {
                /*
                 * Una credencial que ya no vale NO se trata como error aqui.
                 *
                 * Se deja el atributo vacio y el controlador manda a
                 * vincular. Reventar con un 403 en una tableta de ventanilla
                 * mostraria una pantalla de error a un ciudadano.
                 */
            }
        }

        return $next($request);
    }
}
