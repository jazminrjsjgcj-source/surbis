<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

final class DeploymentPolicy
{
    public function __construct(private readonly Request $request) {}

    public function viewAny(User $user): bool
    {
        return $this->activeMembership()?->isAdmin() === true;
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $this->belongsToActiveOrganization($deployment);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Cambiar el estado: activar, suspender.
     *
     * Un deployment cerrado no admite cambios. Se expresa como permiso y no
     * como comprobacion suelta en el controlador para que la pantalla pueda
     * preguntar lo MISMO que decide el servidor, y no ofrecer un boton que va
     * a fallar.
     */
    public function update(User $user, Deployment $deployment): bool
    {
        return $this->belongsToActiveOrganization($deployment)
            && $deployment->closed_at === null;
    }

    public function close(User $user, Deployment $deployment): bool
    {
        return $this->update($user, $deployment);
    }

    /**
     * Reasignar cierra el anterior y crea otro (RF-AO-DEP-006), asi que
     * necesita los dos permisos.
     */
    public function reassign(User $user, Deployment $deployment): bool
    {
        return $this->update($user, $deployment) && $this->create($user);
    }

    /**
     * Regenerar el token publico. RF-AO-DEP-010.
     *
     * Solo tiene sentido en canales que lo usan: el quiosco se identifica con
     * su clave de estacion, que es otro mecanismo.
     */
    public function regenerateToken(User $user, Deployment $deployment): bool
    {
        return $this->update($user, $deployment)
            && $deployment->channel->usesPublicToken();
    }

    private function belongsToActiveOrganization(Deployment $deployment): bool
    {
        $membership = $this->activeMembership();

        return $membership !== null
            && $membership->isAdmin()
            && $membership->organization_id === $deployment->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
