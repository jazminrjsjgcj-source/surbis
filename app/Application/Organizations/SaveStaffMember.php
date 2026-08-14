<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;

/**
 * Alta y edicion de una persona evaluable. RF-AO-COL-002.
 *
 * Hasta ahora la lista las mostraba y no habia forma de crearlas fuera del
 * seeder: una pantalla que enseña algo que nadie puede dar de alta.
 */
final class SaveStaffMember
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array{first_name: string, last_name: string, employee_code: ?string, branch_id: ?int, area_id: ?int} $attributes */
    public function create(Organization $organization, array $attributes): StaffMember
    {
        $staff = new StaffMember($attributes);
        $staff->organization()->associate($organization);
        $staff->save();

        $this->audit->record('staff_member.created', $staff, [
            'employee_code' => $staff->employee_code,
        ]);

        return $staff;
    }

    /** @param array{first_name: string, last_name: string, employee_code: ?string, branch_id: ?int, area_id: ?int} $attributes */
    public function update(StaffMember $staff, array $attributes): StaffMember
    {
        $staff->fill($attributes)->save();

        $this->audit->record('staff_member.updated', $staff, [
            'employee_code' => $staff->employee_code,
        ]);

        return $staff;
    }
}
