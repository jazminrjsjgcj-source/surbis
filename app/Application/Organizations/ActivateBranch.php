<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Enums\BranchStatus;
use App\Domain\Organizations\Models\Branch;

final class ActivateBranch
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(Branch $branch): void
    {
        $branch->forceFill([
            'status' => BranchStatus::Active,
            'archived_at' => null,
        ])->save();

        $this->audit->record('branch.activated', $branch, [
            'code' => $branch->code,
        ]);
    }
}
