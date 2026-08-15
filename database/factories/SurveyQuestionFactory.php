<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SurveyQuestion> */
class SurveyQuestionFactory extends Factory
{
    protected $model = SurveyQuestion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'survey_version_id' => SurveyVersion::factory(),

            // Hereda la organizacion de su version. Inventar otra crearia una
            // pregunta cuya organizacion no coincide con la de su encuesta.
            'organization_id' => fn (array $attributes): int => SurveyVersion::query()
                ->whereKey($attributes['survey_version_id'])
                ->value('organization_id'),

            'type' => QuestionType::SingleChoice,
            'text' => '¿Como calificarias la atencion?',
            'help' => null,
            'is_required' => false,
            'limits' => null,
            'position' => 1,
        ];
    }

    public function ofType(QuestionType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
