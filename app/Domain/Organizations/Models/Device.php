<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un dispositivo de quiosco. Entidad minima; la Fase 11 la amplia.
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'area_id',
        'name',
        'code',
        'status',
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

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @param  Builder<Device>  $query
     * @return Builder<Device>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** @param Builder<Device> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Donde esta, en palabras.
     *
     * Para que el listado de deployments diga "Palacio Municipal ·
     * Ventanilla 3" y no un identificador.
     */
    public function location(): string
    {
        return trim(($this->branch?->name ?? '').' · '.$this->name, ' ·');
    }

    protected static function newFactory(): Factory
    {
        return DeviceFactory::new();
    }
}
