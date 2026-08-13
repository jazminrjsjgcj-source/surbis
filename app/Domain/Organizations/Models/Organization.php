<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'status',
        'settings',
    ];

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** @return HasMany<StaffMember, $this> */
    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'settings' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return OrganizationFactory::new();
    }
}
