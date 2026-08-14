<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * P-016: darle cuenta a alguien que ya se evaluaba.
 *
 * Maria trabaja en ventanilla, la evaluan seis meses sin cuenta, y luego la
 * ascienden. Lo que NO se hace es crear un registro nuevo: su historial de
 * evaluaciones apunta a la persona que ya existe, y partirlo en dos haria que
 * los informes de esos seis meses dejaran de encontrarla.
 *
 * Se reutiliza la invitacion de RF-AO-COL-002 y despues se vincula: la cuenta
 * nace suspendida y se activa cuando la persona define su contrasena, igual
 * que cualquier otra invitacion.
 */
final class GrantAccountToStaffMember
{
    public function __construct(
        private readonly InviteMember $invite,
        private readonly RecordAuditLog $audit,
    ) {}

    /** @param array{email: string, role: MembershipRole} $data */
    public function execute(StaffMember $staff, array $data): Membership
    {
        return DB::transaction(function () use ($staff, $data): Membership {
            $membership = $this->invite->execute($staff->organization, [
                'name' => trim($staff->first_name.' '.$staff->last_name),
                'email' => $data['email'],
                'role' => $data['role'],

                // Hereda la asignacion que ya tenia como persona evaluable.
                // Reescribirla desde el formulario obligaria a recordarla, y
                // olvidarla la dejaria sin sucursal sin motivo.
                'branch_id' => $staff->branch_id,
                'area_id' => $staff->area_id,
            ]);

            $staff->forceFill(['membership_id' => $membership->id])->save();

            $this->audit->record('staff_member.account_granted', $staff, [
                'membership_id' => $membership->id,
            ]);

            return $membership;
        });
    }
}
