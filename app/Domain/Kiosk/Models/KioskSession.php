<?php

declare(strict_types=1);

namespace App\Domain\Kiosk\Models;

use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quien esta siendo evaluado ahora en un dispositivo. RF-COL-001 a 006.
 */
class KioskSession extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'organization_id', 'device_id', 'deployment_id', 'staff_member_id',
        'opened_by', 'started_at', 'last_activity_at', 'closed_at', 'closed_reason',
    ];

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<Deployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Cuanto lleva sin actividad.
     *
     * El quiosco reinicia la captura por inactividad (RF-COL-012), pero la
     * SESION dura mas: un turno de ocho horas tiene ratos sin gente. Son dos
     * relojes distintos y conviene no confundirlos.
     */
    public function idleSeconds(?CarbonImmutable $at = null): int
    {
        return (int) $this->last_activity_at->diffInSeconds($at ?? now());
    }

    /**
     * @param  Builder<KioskSession>  $query
     * @return Builder<KioskSession>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
