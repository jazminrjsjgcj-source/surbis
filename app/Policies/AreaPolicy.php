<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Area;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

/**
 * Misma forma que BranchPolicy y por el mismo motivo: la Policy es la segunda
 * barrera, no la primera. Las consultas ya se acotan a la organizacion activa.
 */
final class AreaPolicy
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

    public function update(User $user, Area $area): bool
    {
        return $this->belongsToActiveOrganization($area);
    }

    public function archive(User $user, Area $area): bool
    {
        return $this->belongsToActiveOrganization($area);
    }

    private function belongsToActiveOrganization(Area $area): bool
    {
        $membership = $this->activeMembership();

        return $membership !== null
            && $membership->isAdmin()
            && $membership->organization_id === $area->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
