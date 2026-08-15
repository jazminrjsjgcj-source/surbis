<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),

            // Hereda la organizacion de su sucursal: inventar otra crearia un
            // dispositivo cuya organizacion no coincide con donde esta.
            'organization_id' => fn (array $attributes): int => Branch::query()
                ->whereKey($attributes['branch_id'])
                ->value('organization_id'),

            'area_id' => null,
            'name' => 'Tableta '.fake()->numberBetween(1, 99),
            'code' => 'DEV-'.fake()->unique()->numberBetween(100, 999),
            'status' => 'active',
        ];
    }
}
