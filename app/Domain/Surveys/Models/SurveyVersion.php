<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\HasPublicUlid;
use App\Domain\Surveys\Casts\VersionSettingsCast;
use App\Domain\Surveys\Enums\SurveyVersionStatus;
use Database\Factories\SurveyVersionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyVersion extends Model
{
    /** @use HasFactory<SurveyVersionFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'survey_id',
        'organization_id',
        'version_number',
        'status',
        'settings',
        'lock_version',
    ];

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Las preguntas, siempre en orden.
     *
     * El orderBy va en la relacion y no en cada consulta: una lista de
     * preguntas sin ordenar no es un detalle de presentacion, es una encuesta
     * distinta. Dejarlo a criterio de quien consulta garantiza que algun sitio
     * lo olvide.
     *
     * @return HasMany<SurveyQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('position');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isDraft(): bool
    {
        return $this->status === SurveyVersionStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === SurveyVersionStatus::Published;
    }

    /**
     * Una version publicada no se modifica jamas. RF-AO-PUB-007.
     *
     * La inmutabilidad se aplica en la capa de aplicacion y con Policy, no
     * bloqueando la tabla: la base tiene que poder archivarla, y un disparador
     * que impida todo UPDATE tambien impediria eso.
     *
     * Este metodo existe para que la regla se pregunte con una frase y no
     * comparando estados en cada sitio, que es como se olvida en uno.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SurveyVersionStatus::class,
            'settings' => VersionSettingsCast::class,
            'version_number' => 'integer',
            'lock_version' => 'integer',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SurveyVersionFactory::new();
    }
}
