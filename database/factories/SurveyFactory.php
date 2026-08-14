<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Surveys\Enums\SurveyStatus;
use App\Domain\Surveys\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Survey> */
class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Encuesta de '.fake()->unique()->word(),
            'description' => fake()->sentence(),
            'status' => SurveyStatus::Draft,

            // Declarados aunque sean null: create() solo carga lo que inserta,
            // y shouldBeStrict convierte en excepcion leer lo que no se cargo.
            'created_by' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => SurveyStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
