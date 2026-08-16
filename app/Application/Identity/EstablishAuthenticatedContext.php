<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Que pasa justo despues de autenticar: donde va el usuario y con que
 * organizacion activa.
 *
 * Existe como clase, y no repartido por el controlador, porque es el UNICO
 * punto donde se decide el destino tras autenticar. RF-AUT-003 y RF-AUT-007.
 *
 * En TASK-009 se reservo como punto de insercion para el segundo factor. En
 * TASK-010 se cumplio: anadir MFA fueron nueve lineas aqui y ni una en el
 * controlador ni en las rutas.
 */
final class EstablishAuthenticatedContext
{
    public function __construct(
        private readonly ActiveOrganizationContext $context,
        private readonly PendingSecondFactor $pending,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * Devuelve el nombre de la ruta a la que redirigir.
     */
    public function execute(User $user): string
    {
        $this->context->forget();

        // RF-AUT-007: si el usuario tiene segundo factor, la sesion
        // definitiva NO se crea todavia. Se cierra la que abrio el guard al
        // validar la contrasena y queda solo un identificador pendiente.
        //
        // Este es el punto de insercion que se reservo en TASK-009. Un solo
        // sitio decide el destino tras autenticar, y por eso anadir MFA no
        // obligo a tocar el controlador ni las rutas.
        if ($user->hasMfaEnabled() && ! $this->pending->wasVerifiedBy($user)) {
            $this->auth->guard()->logout();
            $this->pending->start($user);

            return 'auth.second-factor.challenge';
        }

        if ($user->isPlatformAdmin()) {
            return 'platform.dashboard';
        }

        $memberships = $this->context->usableMemberships($user);

        // Con varias organizaciones, elige. No se asigna la primera ni la mas
        // reciente: un valor por defecto aqui taparia que la eleccion nunca
        // se hizo, y el usuario trabajaria sobre datos de otra dependencia
        // sin haberlo pedido.
        if ($memberships->count() > 1) {
            return 'auth.organizations.choose';
        }

        /** @var Membership $membership */
        $membership = $memberships->first();

        $this->context->remember($membership);

        return $this->homeFor($membership);
    }

    public function homeFor(Membership $membership): string
    {
        return match ($membership->role) {
            MembershipRole::Admin => 'admin.dashboard',
            MembershipRole::Collaborator => 'kiosk.welcome',
        };
    }
}
