<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

/**
 * RNF-AO-COL-001 exige Policy para las operaciones de administracion.
 *
 * La Policy es la SEGUNDA barrera. La primera es que las consultas se acotan
 * a la organizacion activa, asi que una sucursal ajena ni siquiera se
 * encuentra: da 404 y no 403, que ademas no revela que ese identificador
 * exista.
 *
 * Dos barreras independientes y las dos probadas. Si una se cae por un
 * descuido, la otra sigue.
 */
final class BranchPolicy
{
    public function __construct(private readonly Request $request) {}

    public function viewAny(User $user): bool
    {
        return $this->activeMembership()?->isAdmin() === true;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->belongsToActiveOrganization($branch);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->belongsToActiveOrganization($branch);
    }

    public function archive(User $user, Branch $branch): bool
    {
        return $this->belongsToActiveOrganization($branch);
    }

    private function belongsToActiveOrganization(Branch $branch): bool
    {
        $membership = $this->activeMembership();

        return $membership !== null
            && $membership->isAdmin()
            && $membership->organization_id === $branch->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
