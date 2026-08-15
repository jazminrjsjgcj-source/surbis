<?php

declare(strict_types=1);

namespace App\Application\Deployments;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Activar, suspender y cerrar. RF-AO-DEP-005 · RNF-AO-DEP-003.
 */
final class ChangeDeploymentStatus
{
    public function __construct(
        private readonly DeploymentGuard $guard,
        private readonly RecordAuditLog $audit,
    ) {}

    public function activate(Deployment $deployment, User $actor): Deployment
    {
        // Un cerrado no se reabre: mezclaria dos periodos distintos de
        // aplicacion en el mismo registro, y las respuestas no podrian
        // distinguirlos.
        $this->guard->ensureNotClosed($deployment);

        return $this->apply($deployment, DeploymentStatus::Active, $actor);
    }

    public function suspend(Deployment $deployment, User $actor): Deployment
    {
        $this->guard->ensureNotClosed($deployment);

        return $this->apply($deployment, DeploymentStatus::Suspended, $actor);
    }

    /**
     * Cerrar es definitivo.
     *
     * NO borra el deployment ni sus respuestas: RF-AO-PUB-008 y RF-GEN-010.
     * Un deployment cerrado sigue explicando de donde salieron las respuestas
     * que ya se dieron.
     */
    public function close(Deployment $deployment, User $actor): Deployment
    {
        $this->guard->ensureNotClosed($deployment);

        return DB::transaction(function () use ($deployment, $actor): Deployment {
            $deployment->forceFill([
                'status' => DeploymentStatus::Closed,
                'closed_at' => now(),
            ])->save();

            $this->audit->record('deployment.closed', $deployment, [], actor: $actor);

            return $deployment;
        });
    }

    private function apply(Deployment $deployment, DeploymentStatus $status, User $actor): Deployment
    {
        return DB::transaction(function () use ($deployment, $status, $actor): Deployment {
            $anterior = $deployment->status;

            $deployment->forceFill(['status' => $status])->save();

            $this->audit->record("deployment.{$status->value}", $deployment, [
                'from' => $anterior->value,
            ], actor: $actor);

            return $deployment;
        });
    }
}
