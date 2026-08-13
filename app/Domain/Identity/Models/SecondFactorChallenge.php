<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\SecondFactorChannel;
use App\Domain\Identity\SecondFactorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SecondFactorChallenge extends Model
{
    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'user_id',
        'channel',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
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

    /**
     * @param  Builder<SecondFactorChallenge>  $query
     * @return Builder<SecondFactorChallenge>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at > Carbon::now()
            && $this->attempts < self::MAX_ATTEMPTS;
    }

    public function accepts(string $entered): bool
    {
        return SecondFactorCode::matches(
            SecondFactorCode::normalize($entered),
            $this->code_hash,
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => SecondFactorChannel::class,
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
