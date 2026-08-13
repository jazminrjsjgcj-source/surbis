<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Enums\StaffMemberStatus;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona evaluable. Puede no tener cuenta en el sistema. P-007.
 */
class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'organization_id',
        'membership_id',
        'first_name',
        'last_name',
        'employee_code',
        'branch_id',
        'area_id',
        'status',
        'archived_at',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
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
     * @param  Builder<StaffMember>  $query
     * @return Builder<StaffMember>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Solo las que NO tienen cuenta.
     *
     * Las que la tienen ya aparecen en la lista a traves de su membresia; sin
     * este filtro saldrian dos veces, una como cuenta y otra como persona, y
     * el administrador creeria que hay dos Marias.
     *
     * @param  Builder<StaffMember>  $query
     * @return Builder<StaffMember>
     */
    public function scopeWithoutAccount(Builder $query): Builder
    {
        return $query->whereNull('membership_id');
    }

    /**
     * @param  Builder<StaffMember>  $query
     * @return Builder<StaffMember>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('first_name', 'ilike', '%'.$term.'%')
                ->orWhere('last_name', 'ilike', '%'.$term.'%')
                ->orWhere('employee_code', 'ilike', '%'.$term.'%');
        });
    }

    public function hasUserAccount(): bool
    {
        return $this->membership_id !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => StaffMemberStatus::class,
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return StaffMemberFactory::new();
    }
}
