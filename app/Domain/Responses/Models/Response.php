<?php

declare(strict_types=1);

namespace App\Domain\Responses\Models;

use App\Domain\Deployments\Models\Deployment;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;
use App\Domain\Shared\Concerns\HasPublicUlid;
use App\Domain\Surveys\Enums\IdentityMode;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una encuesta contestada.
 */
class Response extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'organization_id', 'deployment_id', 'survey_version_id',
        'branch_id', 'area_id', 'device_id', 'staff_member_id', 'kiosk_session_id',
        'organization_name', 'branch_name', 'area_name', 'device_name',
        'staff_member_name', 'survey_version_number', 'survey_name', 'channel',
        'score', 'max_score', 'comment',
        'respondent_name', 'respondent_email', 'respondent_phone',
        'respondent_email_index', 'respondent_phone_index',
        'identity_mode', 'consent_given_at', 'idempotency_key', 'submitted_at',
        'invalidated_at', 'invalidated_by', 'invalidation_reason',
    ];

    /** @return HasMany<ResponseAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(ResponseAnswer::class);
    }

    /** @return BelongsTo<Deployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
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

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * Si esta respuesta guarda datos de quien la contesto.
     *
     * Se mira el modo GUARDADO, no el de la encuesta hoy: si la encuesta pasa
     * despues a identificada, esta respuesta se dio en anonimo y sigue
     * siendolo. Cambiar la configuracion no puede desanonimizar lo recogido.
     */
    public function hasIdentity(): bool
    {
        return $this->respondent_email !== null
            || $this->respondent_name !== null
            || $this->respondent_phone !== null;
    }

    /**
     * Si la identidad esta protegida y necesita autorizacion para verse.
     *
     * RF-AUT-016 y la tabla confidential_access_grants de la Fase 1: quien
     * quiera verla la pide, otra persona la aprueba, caduca sola y queda
     * auditada.
     */
    public function isConfidential(): bool
    {
        return $this->identity_mode === IdentityMode::Confidential;
    }

    /**
     * Si esta respuesta cuenta para los indicadores. RF-AO-RES-006.
     *
     * Una invalidada NO se borra —sigue siendo lo que alguien contesto— pero
     * deja de sumar. La distincion importa: borrarla haria imposible revisar
     * despues por que se descarto.
     */
    public function isValid(): bool
    {
        return $this->invalidated_at === null;
    }

    /**
     * @param  Builder<Response>  $query
     * @return Builder<Response>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('invalidated_at');
    }

    /**
     * @param  Builder<Response>  $query
     * @return Builder<Response>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            /*
             * encrypted: Laravel cifra al guardar y descifra al leer.
             *
             * Y como cifra con sal distinta cada vez, NO se puede buscar por
             * estas columnas. Para eso estan los indices ciegos.
             */
            'respondent_name' => 'encrypted',
            'respondent_email' => 'encrypted',
            'respondent_phone' => 'encrypted',

            'identity_mode' => IdentityMode::class,
            'consent_given_at' => 'datetime',
            'submitted_at' => 'datetime',
            'invalidated_at' => 'datetime',
            'score' => 'integer',
            'max_score' => 'integer',
            'survey_version_number' => 'integer',
        ];
    }
}
