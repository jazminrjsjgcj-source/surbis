<?php

declare(strict_types=1);

namespace App\Application\Deployments;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reasignar. RF-AO-DEP-006.
 *
 * Reasignar NO es editar: cierra el anterior y crea otro. Cambiarle el
 * alcance en sitio dejaria respuestas ya recibidas apuntando a un lugar donde
 * nunca se dieron, y el historico pasaria a mentir sin que nadie lo notara.
 *
 * Y por eso mismo NO se puede cambiar de version publicada por esta via: el
 * deployment nuevo apunta a la misma version que el anterior. Aplicar otra
 * version es crear un deployment nuevo a proposito.
 */
final class ReassignDeployment
{
    public function __construct(
        private readonly ChangeDeploymentStatus $status,
        private readonly CreateDeployment $create,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array{branch?: ?Branch, area?: ?Area, device?: ?Device}  $targets
     * @return array{0: Deployment, 1: ?string}
     */
    public function execute(
        Deployment $deployment,
        Organization $organization,
        User $actor,
        DeploymentScope $scope,
        array $targets = [],
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
    ): array {
        return DB::transaction(function () use (
            $deployment, $organization, $actor, $scope, $targets, $startsAt, $endsAt
        ): array {
            $this->status->close($deployment, $actor);

            [$nuevo, $token] = $this->create->execute(
                $organization,
                $deployment->version,
                $actor,
                // El canal NO cambia: reasignar mueve el sitio, no la via.
                // Cambiar de canal es otra cosa y se hace creando uno nuevo.
                $deployment->channel,
                $scope,
                $targets,
                $startsAt,
                $endsAt,
            );

            $this->audit->record('deployment.reassigned', $nuevo, [
                'from_ulid' => $deployment->ulid,
            ], actor: $actor);

            return [$nuevo, $token];
        });
    }
}
