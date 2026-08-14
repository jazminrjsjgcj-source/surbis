<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Enums\StaffMemberStatus;
use App\Domain\Organizations\Models\StaffMember;

final class ArchiveStaffMember
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * Archivar, no borrar.
     *
     * Sus evaluaciones historicas siguen apuntando a ella: si se borrara, los
     * informes de meses pasados perderian a quien fue evaluado. RF-GEN-010 y
     * RNF-DAT-009.
     */
    public function archive(StaffMember $staff): void
    {
        $staff->forceFill([
            'status' => StaffMemberStatus::Archived,
            'archived_at' => now(),
        ])->save();

        $this->audit->record('staff_member.archived', $staff);
    }

    public function activate(StaffMember $staff): void
    {
        $staff->forceFill([
            'status' => StaffMemberStatus::Active,
            'archived_at' => null,
        ])->save();

        $this->audit->record('staff_member.activated', $staff);
    }
}
