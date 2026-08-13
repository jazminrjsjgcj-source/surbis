<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;

/**
 * P-015: al definir la contrasena, la cuenta se considera aceptada y activada.
 *
 * Se ejecuta desde el mismo flujo que un restablecimiento normal, asi que
 * tiene que distinguir los dos casos: activar membresias suspendidas por un
 * administrador seria deshacer una decision suya. Solo se activan las que
 * nunca llegaron a usarse, es decir, las que tienen invited_at y no joined_at.
 */
final class AcceptInvitation
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $user): void
    {
        $pendientes = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Suspended)
            ->whereNotNull('invited_at')
            ->whereNull('joined_at')
            ->get();

        foreach ($pendientes as $membership) {
            $membership->forceFill([
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
                'suspended_at' => null,
            ])->save();

            $this->audit->record('membership.accepted', $membership, [], actor: $user);
        }
    }
}
