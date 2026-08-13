<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\IsTimeBoundGrant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorizacion para consultar identidades capturadas en modo confidencial.
 * P-005.
 *
 * Tener la concesion vigente es condicion necesaria, no suficiente: cada
 * lectura del payload cifrado escribe ademas en audit_logs. RNF-AO-RES-004.
 */
class ConfidentialAccessGrant extends Model
{
    use IsTimeBoundGrant;

    protected $fillable = [
        'organization_id',
        'user_id',
        'reason',
        'granted_by',
        'granted_at',
        'expires_at',
        'revoked_at',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
