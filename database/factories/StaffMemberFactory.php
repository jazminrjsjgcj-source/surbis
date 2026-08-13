<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizations\Enums\StaffMemberStatus;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffMember> */
class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),

            // Por defecto SIN cuenta: es el caso que el modelo tiene que
            // soportar y el que se olvida al probar. P-007.
            'membership_id' => null,

            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'employee_code' => null,
            'status' => StaffMemberStatus::Active,
        ];
    }
}
