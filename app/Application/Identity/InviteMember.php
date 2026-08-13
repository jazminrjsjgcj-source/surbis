<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invitar a alguien a la organizacion. RF-AO-COL-002.
 *
 * P-015: la invitacion es una liga de un solo uso para definir contrasena.
 * Reutiliza el broker de TASK-011 en lugar de inventar un sistema de tokens
 * propio, que seria una segunda cosa que caduca, se reenvia y se revoca.
 *
 * La membresia nace SUSPENDIDA y se activa al usar la liga. Asi, entre la
 * invitacion y su aceptacion, esa cuenta no puede entrar: sin ello, alguien
 * que adivinara la contrasena inicial —que no existe, pero da igual— tendria
 * una puerta abierta durante dias.
 */
final class InviteMember
{
    public function __construct(
        private readonly PasswordBroker $broker,
        private readonly RecordAuditLog $audit,
    ) {}

    /** @param array{name: string, email: string, role: MembershipRole, branch_id: ?int, area_id: ?int} $data */
    public function execute(Organization $organization, array $data): Membership
    {
        $membership = DB::transaction(function () use ($organization, $data): Membership {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    // Una contrasena aleatoria que nadie conoce ni conocera.
                    // El acceso llega por la liga; esto solo evita dejar la
                    // columna vacia.
                    'password' => Str::random(64),
                    'status' => UserStatus::Active,
                ],
            );

            $membership = new Membership([
                'role' => $data['role'],
                'status' => MembershipStatus::Suspended,
                'branch_id' => $data['branch_id'],
                'area_id' => $data['area_id'],
                'invited_at' => now(),
            ]);

            $membership->organization()->associate($organization);
            $membership->user()->associate($user);
            $membership->save();

            $this->audit->record('membership.invited', $membership, [
                'role' => $data['role']->value,
            ]);

            return $membership;
        });

        $this->broker->sendResetLink(['email' => $data['email']]);

        return $membership;
    }
}
