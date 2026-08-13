<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;

/**
 * Que pasa justo despues de autenticar: donde va el usuario y con que
 * organizacion activa.
 *
 * Existe como clase, y no repartido por el controlador, porque es el UNICO
 * punto donde se decide el destino tras autenticar. RF-AUT-003 lo describe
 * hoy; RF-AUT-007 anadira la verificacion MFA antes de la sesion definitiva
 * (TASK-010) y ese paso se inserta aqui, no en cinco sitios.
 */
final class EstablishAuthenticatedContext
{
    public function __construct(private readonly ActiveOrganizationContext $context) {}

    /**
     * Devuelve el nombre de la ruta a la que redirigir.
     */
    public function execute(User $user): string
    {
        $this->context->forget();

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
            MembershipRole::Collaborator => 'kiosk.start',
        };
    }
}
