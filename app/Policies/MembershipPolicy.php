<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

final class MembershipPolicy
{
    public function __construct(private readonly Request $request) {}

    public function viewAny(User $user): bool
    {
        return $this->activeMembership()?->isAdmin() === true;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Membership $membership): bool
    {
        return $this->belongsToActiveOrganization($membership);
    }

    /**
     * P-017: nadie se suspende ni se cambia el rol a si mismo desde esta
     * pantalla.
     *
     * RF-AO-COL-006 protege al ultimo administrador, pero no al penultimo que
     * se suspende por error: ese se queda fuera y necesita que otro lo
     * reactive. Sus propias acciones se hacen desde su perfil, donde el
     * contexto deja claro sobre quien se esta actuando.
     */
    public function suspend(User $user, Membership $membership): bool
    {
        return $this->belongsToActiveOrganization($membership)
            && $this->activeMembership()?->id !== $membership->id;
    }

    private function belongsToActiveOrganization(Membership $membership): bool
    {
        $active = $this->activeMembership();

        return $active !== null
            && $active->isAdmin()
            && $active->organization_id === $membership->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
