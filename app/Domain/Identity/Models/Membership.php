<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'status',
        'branch_id',
        'area_id',
        'invited_at',
        'joined_at',
        'suspended_at',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * La persona evaluable vinculada a esta cuenta, si la hay.
     *
     * Es hasOne y no belongsTo porque la clave vive en staff_members: una
     * cuenta puede existir sin que nadie la evalue.
     *
     * @return HasOne<StaffMember, $this>
     */
    public function staffMember(): HasOne
    {
        return $this->hasOne(StaffMember::class);
    }

    /**
     * ILIKE: en PostgreSQL LIKE distingue mayusculas.
     *
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->whereHas('user', function (Builder $builder) use ($term): void {
            $builder->where('name', 'ilike', '%'.$term.'%')
                ->orWhere('email', 'ilike', '%'.$term.'%');
        });
    }

    /**
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    public function isAdmin(): bool
    {
        return $this->role === MembershipRole::Admin;
    }

    public function isCollaborator(): bool
    {
        return $this->role === MembershipRole::Collaborator;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => MembershipStatus::class,
            'invited_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return MembershipFactory::new();
    }
}
