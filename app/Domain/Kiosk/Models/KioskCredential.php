<?php

declare(strict_types=1);

namespace App\Domain\Kiosk\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que mantiene vinculada una tableta.
 */
class KioskCredential extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'organization_id', 'device_id', 'token_hash',
        'expires_at', 'last_used_at', 'revoked_at', 'linked_by',
    ];

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<KioskCredential>  $query
     * @return Builder<KioskCredential>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
