<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Enums\StaffMemberStatus;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\StaffMemberFactory;
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
