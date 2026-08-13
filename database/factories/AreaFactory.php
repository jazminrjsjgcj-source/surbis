<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizations\Enums\AreaStatus;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Area> */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'branch_id' => $branch,

            // El area hereda la organizacion de su sucursal. Dejar que la
            // factory invente otra crearia datos imposibles: un area cuya
            // organizacion no coincide con la de su branch.
            'organization_id' => fn (array $attributes): int => Branch::query()
                ->whereKey($attributes['branch_id'])
                ->value('organization_id'),

            'name' => fake()->word(),
            'code' => Str::upper(Str::random(6)),
            'status' => AreaStatus::Active,
        ];
    }
}
