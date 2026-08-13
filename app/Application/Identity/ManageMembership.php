<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Application\Identity\Exceptions\LastAdministrator;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;

final class ManageMembership
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @throws LastAdministrator
     */
    public function suspend(Membership $membership): void
    {
        DB::transaction(function () use ($membership): void {
            // lockForUpdate dentro de la transaccion: sin el, dos peticiones
            // simultaneas suspendiendo a los dos ultimos administradores
            // verian cada una que "todavia queda otro" y la organizacion se
            // quedaria sin ninguno. La regla tiene que sobrevivir a la
            // concurrencia, no solo al uso normal.
            $this->guardLastAdministrator($membership);

            $membership->forceFill([
                'status' => MembershipStatus::Suspended,
                'suspended_at' => now(),
            ])->save();

            // RF-AO-COL-005: suspender impide nuevos inicios y no borra las
            // respuestas historicas. No hay ningun delete aqui, y es el
            // punto.
            $this->audit->record('membership.suspended', $membership, [
                'role' => $membership->role->value,
            ]);
        });
    }

    public function activate(Membership $membership): void
    {
        $membership->forceFill([
            'status' => MembershipStatus::Active,
            'suspended_at' => null,
        ])->save();

        $this->audit->record('membership.activated', $membership, [
            'role' => $membership->role->value,
        ]);
    }

    /**
     * @throws LastAdministrator
     */
    public function changeRole(Membership $membership, MembershipRole $role): void
    {
        if ($membership->role === $role) {
            return;
        }

        DB::transaction(function () use ($membership, $role): void {
            // Bajar de rol al ultimo administrador deja a la organizacion sin
            // administradores igual que suspenderlo. La regla es la misma.
            if ($role !== MembershipRole::Admin) {
                $this->guardLastAdministrator($membership);
            }

            $anterior = $membership->role;

            $membership->forceFill(['role' => $role])->save();

            $this->audit->record('membership.role_changed', $membership, [
                'role_before' => $anterior->value,
                'role_after' => $role->value,
            ]);
        });
    }

    /** @param array{branch_id: int|null, area_id: int|null} $assignment */
    public function assign(Membership $membership, array $assignment): void
    {
        $membership->forceFill($assignment)->save();

        // RNF-AO-COL-001: las asignaciones quedan auditadas.
        //
        // P-018: el historico NO se mueve. Las respuestas ya guardadas
        // siguen apuntando a la sucursal donde ocurrieron, porque ahi
        // ocurrieron. RNF-DAT-009.
        $this->audit->record('membership.assigned', $membership, [
            'branch_id' => $assignment['branch_id'],
            'area_id' => $assignment['area_id'],
        ]);
    }

    /**
     * @throws LastAdministrator
     */
    private function guardLastAdministrator(Membership $membership): void
    {
        if (! $membership->isAdmin() || ! $membership->isActive()) {
            return;
        }

        /*
         * Se traen las filas y se cuentan en PHP, no con count() en la base.
         *
         * PostgreSQL rechaza `SELECT count(*) ... FOR UPDATE`: no se puede
         * bloquear el resultado de una funcion de agregacion, porque no
         * corresponde a filas concretas. `lockForUpdate()->count()` produce
         * SQL invalido.
         *
         * MySQL lo acepta sin quejarse, asi que sobre MySQL o SQLite esto
         * habria pasado en verde. Es la razon por la que ANEXO 1 seccion 68
         * exige que la suite corra sobre PostgreSQL: el motor de las pruebas
         * tiene que ser el de produccion.
         *
         * Lo que se bloquea son las filas de los OTROS administradores
         * activos. Mientras esta transaccion vive, nadie mas puede
         * suspenderlos ni bajarles el rol, asi que el recuento no puede
         * quedarse obsoleto entre la comprobacion y el guardado.
         */
        $otros = Membership::query()
            ->select('id')
            ->where('organization_id', $membership->organization_id)
            ->where('id', '!=', $membership->id)
            ->where('role', MembershipRole::Admin)
            ->where('status', MembershipStatus::Active)
            ->lockForUpdate()
            ->get();

        if ($otros->isEmpty()) {
            throw new LastAdministrator;
        }
    }

    public function activeAdministrators(Organization $organization): int
    {
        return Membership::query()
            ->where('organization_id', $organization->id)
            ->where('role', MembershipRole::Admin)
            ->where('status', MembershipStatus::Active)
            ->count();
    }
}
