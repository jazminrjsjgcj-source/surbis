<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deployment> */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'survey_version_id' => SurveyVersion::factory()->published(1),

            'organization_id' => fn (array $attributes): int => SurveyVersion::query()
                ->whereKey($attributes['survey_version_id'])
                ->value('organization_id'),

            // Enlace publico con alcance de organizacion: es la combinacion
            // que menos entidades necesita, asi que sirve de base.
            'channel' => DeploymentChannel::PublicLink,
            'scope' => DeploymentScope::Organization,
            'status' => DeploymentStatus::Active,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
