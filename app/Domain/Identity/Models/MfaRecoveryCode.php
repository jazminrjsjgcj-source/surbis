<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaRecoveryCode extends Model
{
    protected $fillable = [
        'user_id',
        'code_hash',
        'used_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'code_hash',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSpent(): bool
    {
        return $this->used_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'used_at' => 'immutable_datetime',
        ];
    }
}
