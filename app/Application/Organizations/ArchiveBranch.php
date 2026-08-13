<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Application\Organizations\Exceptions\HasActiveReferences;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Enums\BranchStatus;
use App\Domain\Organizations\Models\Branch;

final class ArchiveBranch
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @throws HasActiveReferences
     */
    public function execute(Branch $branch): void
    {
        $references = $branch->activeReferences();

        if ($references !== []) {
            throw new HasActiveReferences($references);
        }

        $branch->forceFill([
            'status' => BranchStatus::Archived,
            'archived_at' => now(),
        ])->save();

        // No se borra nada. RF-AO-BRA-004: archivar impide asignaciones
        // nuevas y conserva el historial. Las respuestas que apuntan a esta
        // sucursal siguen apuntando a ella.
        $this->audit->record('branch.archived', $branch, [
            'code' => $branch->code,
        ]);
    }
}
