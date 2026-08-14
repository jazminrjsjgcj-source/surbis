<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\StaffMember;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

final class StaffMemberPolicy
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

    public function update(User $user, StaffMember $staff): bool
    {
        return $this->belongsToActiveOrganization($staff);
    }

    public function archive(User $user, StaffMember $staff): bool
    {
        return $this->belongsToActiveOrganization($staff);
    }

    /**
     * Solo se le da cuenta a quien no la tiene. Un segundo intento crearia
     * una membresia suelta y dejaria la primera huerfana.
     */
    public function grantAccount(User $user, StaffMember $staff): bool
    {
        return $this->belongsToActiveOrganization($staff)
            && ! $staff->hasUserAccount();
    }

    private function belongsToActiveOrganization(StaffMember $staff): bool
    {
        $membership = $this->activeMembership();

        return $membership !== null
            && $membership->isAdmin()
            && $membership->organization_id === $staff->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
