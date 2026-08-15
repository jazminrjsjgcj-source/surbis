<?php

declare(strict_types=1);

namespace App\Application\Deployments;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Surveys\Models\SurveyVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Crear una aplicacion. RF-AO-DEP-002 y 003.
 *
 * Devuelve el deployment Y el token en claro cuando el canal lo usa: es la
 * unica vez que ese token existe fuera del QR impreso. En la base solo queda
 * su hash.
 */
final class CreateDeployment
{
    public function __construct(
        private readonly DeploymentGuard $guard,
        private readonly PublicToken $tokens,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  array{branch?: ?Branch, area?: ?Area, device?: ?Device}  $targets
     * @return array{0: Deployment, 1: ?string}
     */
    public function execute(
        Organization $organization,
        SurveyVersion $version,
        User $author,
        DeploymentChannel $channel,
        DeploymentScope $scope,
        array $targets = [],
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
    ): array {
        $this->guard->ensureCanDeploy(
            $organization, $version, $channel, $scope, $targets, $startsAt, $endsAt
        );

        return DB::transaction(function () use (
            $organization, $version, $author, $channel, $scope, $targets, $startsAt, $endsAt
        ): array {
            $token = $channel->usesPublicToken() ? $this->tokens->generate() : null;

            $deployment = Deployment::query()->create([
                'organization_id' => $organization->id,
                'survey_version_id' => $version->id,
                'channel' => $channel,
                'scope' => $scope,
                'branch_id' => ($targets['branch'] ?? null)?->id,
                'area_id' => ($targets['area'] ?? null)?->id,
                'device_id' => ($targets['device'] ?? null)?->id,

                // Explicito aunque la base lo ponga por defecto: create()
                // devuelve un modelo con solo lo que se le paso (T-027).
                'status' => DeploymentStatus::Active,

                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'public_token_hash' => $token === null ? null : $this->tokens->hash($token),
                'created_by' => $author->id,
            ]);

            $this->audit->record('deployment.created', $deployment, [
                'channel' => $channel->value,
                'scope' => $scope->value,
                'version_number' => $version->version_number,
            ], actor: $author);

            return [$deployment, $token];
        });
    }
}
