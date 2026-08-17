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
use Illuminate\Support\Facades\Hash;
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
    /**
     * @param  array<string, mixed>  $data
     *
     * `password` opcional: solo llega cuando NO hay correo configurado y quien
     * da de alta la pone a mano. Decision del area usuaria, 19 ago 2026.
     */
    public function execute(Organization $organization, array $data): Membership
    {
        $membership = DB::transaction(function () use ($organization, $data): Membership {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    /*
                     * Sin contrasena dada, una aleatoria que nadie conoce ni
                     * conocera: el acceso llega por la liga y esto solo evita
                     * dejar la columna vacia.
                     *
                     * Con contrasena dada, la que puso quien da de alta —y se
                     * marca, porque a partir de ahi la conocen dos personas—.
                     */
                    'password' => isset($data['password'])
                        ? Hash::make($data['password'])
                        : Str::random(64),

                    'password_set_by_other_at' => isset($data['password']) ? now() : null,

                    'status' => UserStatus::Active,
                ],
            );

            $membership = new Membership([
                'role' => $data['role'],

                /*
                 * Con contrasena directa la membresia nace ACTIVA.
                 *
                 * La invitacion normal nace suspendida y se activa al
                 * aceptarla. Aqui no hay nada que aceptar —no se envio ningun
                 * correo— asi que dejarla suspendida impediria entrar a
                 * alguien que ya tiene sus credenciales.
                 */
                'status' => isset($data['password'])
                    ? MembershipStatus::Active
                    : MembershipStatus::Suspended,
                'branch_id' => $data['branch_id'],
                'area_id' => $data['area_id'],
                'invited_at' => now(),
            ]);

            $membership->organization()->associate($organization);
            $membership->user()->associate($user);
            $membership->save();

            $this->audit->record(
                isset($data['password']) ? 'membership.created_with_password' : 'membership.invited',
                $membership,
                ['role' => $data['role']->value],
            );

            return $membership;
        });

        $this->broker->sendResetLink(['email' => $data['email']]);

        return $membership;
    }
}
