<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;

final class SaveArea
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array{name: string, code: string} $attributes */
    public function create(Branch $branch, array $attributes): Area
    {
        $area = new Area($attributes);
        $area->branch()->associate($branch);

        // La organizacion se hereda de la sucursal, no llega del formulario.
        // Si viniera de fuera, un area podria acabar apuntando a una
        // organizacion distinta de la de su sucursal. RF-GEN-003.
        $area->organization()->associate($branch->organization_id);
        $area->save();

        $this->audit->record('area.created', $area, [
            'branch' => $branch->code,
            'code' => $area->code,
        ]);

        return $area;
    }

    /** @param array{name: string, code: string} $attributes */
    public function update(Area $area, array $attributes): Area
    {
        $before = ['name' => $area->name, 'code' => $area->code];

        $area->fill($attributes)->save();

        $this->audit->record('area.updated', $area, [
            'code_before' => $before['code'],
            'code_after' => $area->code,
            'name_before' => $before['name'],
            'name_after' => $area->name,
        ]);

        return $area;
    }
}
