<?php

declare(strict_types=1);

namespace App\Domain\Deployments\Models;

use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Responses\Models\Response;
use App\Domain\Shared\Concerns\HasPublicUlid;
use App\Domain\Surveys\Models\SurveyVersion;
use Carbon\CarbonImmutable;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Donde y como se aplica una version publicada. RF-AO-DEP-001 a 010.
 */
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'organization_id',
        'survey_version_id',
        'channel',
        'scope',
        'branch_id',
        'area_id',
        'device_id',
        'status',
        'starts_at',
        'ends_at',
        'public_token_hash',
        'created_by',
        'closed_at',
    ];

    /**
     * Las respuestas que se dieron por esta aplicacion.
     *
     * Existe para que DeploymentGuard pueda comprobar de verdad la regla de
     * "no borrar ni reasignar lo que ya tiene historial": hasta que existio
     * la tabla, ese metodo devolvia cero siempre.
     *
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /** @return BelongsTo<SurveyVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SurveyVersion::class, 'survey_version_id');
    }

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

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Si esta recibiendo respuestas AHORA.
     *
     * No es lo mismo que el estado declarado: uno "activo" con fecha de
     * inicio manana todavia no aplica, y uno cuya vigencia expiro anoche
     * tampoco aunque nadie lo haya cerrado.
     *
     * Vive aqui y no repetido en cada consulta porque la diferencia entre
     * "activo" y "aplicando" es facil de pasar por alto, y confundirlas haria
     * que el listado mintiera.
     */
    public function isApplying(?CarbonImmutable $at = null): bool
    {
        if ($this->status !== DeploymentStatus::Active) {
            return false;
        }

        $momento = $at ?? now();

        if ($this->starts_at !== null && $momento->lt($this->starts_at)) {
            return false;
        }

        return $this->ends_at === null || $momento->lte($this->ends_at);
    }

    /**
     * Por que no esta aplicando, para poder decirlo.
     *
     * "No activo" sin mas obliga a mirar tres campos para entenderlo.
     */
    public function notApplyingReason(?CarbonImmutable $at = null): ?string
    {
        if ($this->isApplying($at)) {
            return null;
        }

        $momento = $at ?? now();

        return match (true) {
            $this->status === DeploymentStatus::Closed => 'closed',
            $this->status === DeploymentStatus::Suspended => 'suspended',
            $this->starts_at !== null && $momento->lt($this->starts_at) => 'not_started',
            default => 'expired',
        };
    }

    /**
     * Donde aplica, en palabras.
     *
     * @return array{scope: string, name: string|null}
     */
    public function scopeLabel(): array
    {
        return [
            'scope' => $this->scope->value,
            'name' => match ($this->scope) {
                DeploymentScope::Organization => null,
                DeploymentScope::Branch => $this->branch?->name,
                DeploymentScope::Area => $this->area?->name,
                DeploymentScope::Device => $this->device?->location(),
            },
        ];
    }

    /**
     * @param  Builder<Deployment>  $query
     * @return Builder<Deployment>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => DeploymentChannel::class,
            'scope' => DeploymentScope::class,
            'status' => DeploymentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return DeploymentFactory::new();
    }
}
