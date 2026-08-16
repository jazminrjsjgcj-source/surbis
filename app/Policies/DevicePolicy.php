<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Device;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

final class DevicePolicy
{
    public function __construct(private readonly Request $request) {}

    public function view(User $user, Device $device): bool
    {
        return $this->belongsToActiveOrganization($device);
    }

    /**
     * Generar y revocar la clave de estacion.
     *
     * Permiso propio y no `update`: quien puede renombrar una tableta no
     * tiene por que poder darle acceso al sistema. Hoy los dos exigen ser
     * administrador, pero separarlos deja la puerta abierta a un rol
     * intermedio sin tener que reescribir las comprobaciones.
     */
    public function manageKeys(User $user, Device $device): bool
    {
        return $this->belongsToActiveOrganization($device);
    }

    private function belongsToActiveOrganization(Device $device): bool
    {
        $membership = $this->activeMembership();

        return $membership !== null
            && $membership->isAdmin()
            && $membership->organization_id === $device->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
