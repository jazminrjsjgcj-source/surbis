<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Identity\ActiveOrganizationContext;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la organizacion activa en el servidor y la deja disponible para el
 * resto de la peticion. RF-GEN-001 y RF-GEN-003.
 *
 * Se revalida en CADA peticion contra la base. Una membresia suspendida a las
 * 10:05 deja de servir a las 10:05, no cuando caduque la sesion. RA-006.
 */
final class EnsureActiveOrganization
{
    public const REQUEST_ATTRIBUTE = 'active_membership';

    public function __construct(
        private readonly ActiveOrganizationContext $context,
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $membership = $this->context->current($user);

        if ($membership === null) {
            // Sin organizacion utilizable no hay nada que mostrar. Se cierra
            // la sesion en lugar de dejar al usuario dentro sin contexto:
            // media aplicacion funcionando a medias es peor que una puerta
            // cerrada con su motivo.
            $this->auth->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('auth.context_lost')]);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $membership);

        return $next($request);
    }
}
