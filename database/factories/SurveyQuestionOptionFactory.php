<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Surveys\Enums\OptionDisplay;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SurveyQuestionOption> */
class SurveyQuestionOptionFactory extends Factory
{
    protected $model = SurveyQuestionOption::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'survey_question_id' => SurveyQuestion::factory(),

            'organization_id' => fn (array $attributes): int => SurveyQuestion::query()
                ->whereKey($attributes['survey_question_id'])
                ->value('organization_id'),

            'label' => 'Buena',
            'value' => 'buena',
            'score' => 5,
            'display' => OptionDisplay::Text,
            'appearance' => null,
            'position' => 1,
        ];
    }
}
