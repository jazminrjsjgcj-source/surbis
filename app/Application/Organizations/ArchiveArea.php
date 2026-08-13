<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Application\Organizations\Exceptions\HasActiveReferences;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Enums\AreaStatus;
use App\Domain\Organizations\Models\Area;

final class ArchiveArea
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @throws HasActiveReferences
     */
    public function execute(Area $area): void
    {
        $references = $area->activeReferences();

        if ($references !== []) {
            throw new HasActiveReferences($references);
        }

        $area->forceFill([
            'status' => AreaStatus::Archived,
            'archived_at' => now(),
        ])->save();

        $this->audit->record('area.archived', $area, ['code' => $area->code]);
    }
}
