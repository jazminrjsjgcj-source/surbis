<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Organizations\Enums\BranchStatus;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Database\Factories\BranchFactory;
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
