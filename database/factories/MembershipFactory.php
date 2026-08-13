<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Membership> */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => MembershipRole::Admin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function collaborator(): static
    {
        return $this->state(fn (): array => [
            'role' => MembershipRole::Collaborator,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
