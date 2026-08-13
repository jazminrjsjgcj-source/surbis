<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Enums\AreaStatus;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'name',
        'code',
        'status',
        'archived_at',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<StaffMember, $this> */
    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    /**
     * @param  Builder<Area>  $query
     * @return Builder<Area>
     */
    public function scopeForBranch(Builder $query, Branch|int $branch): Builder
    {
        return $query->where(
            'branch_id',
            $branch instanceof Branch ? $branch->id : $branch,
        );
    }

    /**
     * @param  Builder<Area>  $query
     * @return Builder<Area>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AreaStatus::Active);
    }

    /**
     * ILIKE y no LIKE: en PostgreSQL LIKE distingue mayusculas, asi que
     * buscar "ventanilla" no encontraria "VENTANILLA".
     *
     * @param  Builder<Area>  $query
     * @return Builder<Area>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('name', 'ilike', '%'.$term.'%')
                ->orWhere('code', 'ilike', '%'.$term.'%');
        });
    }

    public function isActive(): bool
    {
        return $this->status === AreaStatus::Active;
    }

    /**
     * Referencias que impiden archivar sin resolverlas antes.
     *
     * @return array<string, int>
     */
    public function activeReferences(): array
    {
        return array_filter([
            'memberships' => $this->memberships()->active()->count(),
            'staff_members' => $this->staffMembers()->where('status', 'active')->count(),
        ]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AreaStatus::class,
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AreaFactory::new();
    }
}
