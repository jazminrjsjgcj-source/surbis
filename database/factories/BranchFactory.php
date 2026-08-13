<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizations\Enums\BranchStatus;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->city(),
            'code' => Str::upper(Str::random(6)),
            'status' => BranchStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => BranchStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
