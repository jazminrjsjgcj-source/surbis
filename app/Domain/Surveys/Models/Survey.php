<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\HasPublicUlid;
use App\Domain\Surveys\Enums\SurveyStatus;
use App\Domain\Surveys\Enums\SurveyVersionStatus;
use Database\Factories\SurveyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Survey extends Model
{
    /** @use HasFactory<SurveyFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'status',
        'created_by',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SurveyVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(SurveyVersion::class);
    }

    /**
     * El borrador vivo. Solo puede haber uno: lo garantiza un indice parcial.
     *
     * @return HasOne<SurveyVersion, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(SurveyVersion::class)
            ->where('status', SurveyVersionStatus::Draft);
    }

    /**
     * La ultima version publicada.
     *
     * Se consulta en lugar de guardarse en una columna. Una columna
     * `current_version_id` seria mas rapida de leer y se desincronizaria el
     * dia que alguien archive una version sin actualizarla: un dato que
     * miente es peor que una consulta de mas.
     *
     * @return HasOne<SurveyVersion, $this>
     */
    public function publishedVersion(): HasOne
    {
        return $this->hasOne(SurveyVersion::class)
            ->where('status', SurveyVersionStatus::Published)
            ->latest('version_number');
    }

    /**
     * @param  Builder<Survey>  $query
     * @return Builder<Survey>
     */
    public function scopeForOrganization(Builder $query, Organization|int $organization): Builder
    {
        return $query->where(
            'organization_id',
            $organization instanceof Organization ? $organization->id : $organization,
        );
    }

    /**
     * ILIKE: en PostgreSQL LIKE distingue mayusculas.
     *
     * @param  Builder<Survey>  $query
     * @return Builder<Survey>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term): void {
            $builder->where('name', 'ilike', '%'.$term.'%')
                ->orWhere('description', 'ilike', '%'.$term.'%');
        });
    }

    public function isDraft(): bool
    {
        return $this->status === SurveyStatus::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === SurveyStatus::Archived;
    }

    /**
     * RF-AO-SUR-004: sin eliminacion fisica si tiene versiones publicadas o
     * respuestas.
     *
     * Las respuestas todavia no existen —Fase 9— asi que hoy solo se
     * comprueban las versiones. Cuando existan, la condicion se amplia aqui y
     * no en cinco sitios.
     */
    public function hasPublishedHistory(): bool
    {
        return $this->versions()
            ->where('status', '!=', SurveyVersionStatus::Draft)
            ->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SurveyStatus::class,
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SurveyFactory::new();
    }
}
