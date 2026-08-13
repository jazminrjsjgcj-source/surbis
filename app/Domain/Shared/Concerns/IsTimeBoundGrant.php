<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Vigencia de una concesion temporal.
 *
 * Una concesion sirve si ya empezo, no ha vencido y nadie la ha revocado. Las
 * tres condiciones juntas, siempre. Comprobar solo revoked_at —el olvido
 * habitual— deja pasar concesiones caducadas sin dar ningun error.
 *
 * Lo usan support_grants y confidential_access_grants. Son dos tablas
 * distintas porque el sujeto y la politica lo son, pero la vigencia se
 * calcula igual y no se escribe dos veces.
 */
trait IsTimeBoundGrant
{
    public function isEffective(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return $this->revoked_at === null
            && $this->granted_at <= $at
            && $this->expires_at > $at;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEffective(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query->whereNull('revoked_at')
            ->where('granted_at', '<=', $at)
            ->where('expires_at', '>', $at);
    }
}
