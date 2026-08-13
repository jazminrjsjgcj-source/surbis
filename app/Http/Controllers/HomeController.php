<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Identity\ActiveOrganizationContext;
use App\Application\Identity\EstablishAuthenticatedContext;
use App\Application\Identity\PendingSecondFactor;
use App\Domain\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La raiz del sistema.
 *
 * Nadie escribe /login: la gente escribe la direccion y espera llegar a algun
 * sitio. Desde que se retiro la pagina de bienvenida de Laravel, la raiz
 * devolvia 404, que parece deliberado cuando lo ves tu y parece roto cuando
 * lo ve cualquier otro.
 *
 * No decide nada por su cuenta: pregunta a las clases que ya saben responder
 * donde va cada usuario.
 */
final class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        PendingSecondFactor $pending,
        ActiveOrganizationContext $context,
        EstablishAuthenticatedContext $establish,
    ): RedirectResponse {
        // La verificacion pendiente va primero: en ese punto no hay sesion,
        // asi que sin esta rama la raiz mandaria al login a alguien que ya
        // metio su contrasena correctamente.
        if ($pending->user() !== null) {
            return redirect()->route('auth.second-factor.challenge');
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isPlatformAdmin()) {
            return redirect()->route('platform.dashboard');
        }

        // Si ya hay una organizacion activa se respeta. Llamar a execute()
        // directamente la olvidaria, y quien pertenece a varias tendria que
        // volver a elegirla cada vez que escribe la direccion.
        $membership = $context->current($user);

        if ($membership !== null) {
            return redirect()->route($establish->homeFor($membership));
        }

        return redirect()->route($establish->execute($user));
    }
}
