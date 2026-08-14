<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Surveys\Enums\SurveyVersionStatus;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SurveyVersion> */
class SurveyVersionFactory extends Factory
{
    protected $model = SurveyVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $survey = Survey::factory();

        return [
            'survey_id' => $survey,

            // Hereda la organizacion de su encuesta. Inventar otra crearia una
            // version cuya organizacion no coincide con la de su encuesta.
            'organization_id' => fn (array $attributes): int => Survey::query()
                ->whereKey($attributes['survey_id'])
                ->value('organization_id'),

            'version_number' => 1,
            'status' => SurveyVersionStatus::Draft,
            'settings' => null,
            'published_at' => null,
            'published_by' => null,
            'archived_at' => null,
        ];
    }

    public function published(int $number = 1): static
    {
        return $this->state(fn (): array => [
            'version_number' => $number,
            'status' => SurveyVersionStatus::Published,
            'published_at' => now(),
        ]);
    }
}
