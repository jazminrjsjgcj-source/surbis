<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Enums\BranchStatus;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'organization_id',
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

    /** @return HasMany<Area, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
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
     * Acota la consulta a una organizacion.
     *
     * Se escribe en cada consulta a proposito, en lugar de un global scope.
     * Un scope global aisla solo, pero tambien esconde: el dia que alguien
     * necesite saltarselo, `withoutGlobalScope` lo hace en silencio y desde
     * fuera no se distingue de una consulta normal. Escrito, se ve; olvidado,
     * lo detectan las pruebas de aislamiento.
     *
     * RF-GEN-003 y RNF-GEN-005.
     *
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeForOrganization(Builder $query, Organization|int $organization): Builder
    {
        return $query->where(
            'organization_id',
            $organization instanceof Organization ? $organization->id : $organization,
        );
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BranchStatus::Active);
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // ILIKE y no LIKE: en PostgreSQL LIKE distingue mayusculas, asi que
        // buscar "centro" no encontraria "CENTRO" y el usuario concluiria
        // que la sucursal no existe.
        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('name', 'ilike', '%'.$term.'%')
                ->orWhere('code', 'ilike', '%'.$term.'%');
        });
    }

    public function isActive(): bool
    {
        return $this->status === BranchStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === BranchStatus::Archived;
    }

    /**
     * Referencias que impiden archivar sin resolverlas antes.
     *
     * RNF-AO-BRA-001: no deben existir referencias activas a una sucursal
     * archivada sin advertencia y resolucion explicita.
     *
     * @return array<string, int>
     */
    public function activeReferences(): array
    {
        return array_filter([
            'memberships' => $this->memberships()->active()->count(),
            'staff_members' => $this->staffMembers()->where('status', 'active')->count(),
            'areas' => $this->areas()->where('status', 'active')->count(),
        ]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return BranchFactory::new();
    }
}
