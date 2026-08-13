<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Enums\AreaStatus;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;

final class ActivateArea
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(Area $area): void
    {
        $area->forceFill([
            'status' => AreaStatus::Active,
            'archived_at' => null,
        ])->save();

        $this->audit->record('area.activated', $area, ['code' => $area->code]);
    }

    /**
     * Un area no puede estar activa dentro de una sucursal archivada: seria
     * un sitio al que asignar gente en una sede que ya no opera.
     */
    public function isAllowedFor(Branch $branch): bool
    {
        return $branch->isActive();
    }
}
