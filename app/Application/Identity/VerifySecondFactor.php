<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Models\MfaRecoveryCode;
use App\Domain\Identity\Models\SecondFactorChallenge;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Comprueba el codigo de un solo uso o un codigo de recuperacion.
 *
 * RF-AUT-014 admite los dos. El codigo introducido no se registra en ningun
 * sitio, ni siquiera al fallar: RNF-AUT-012.
 */
final class VerifySecondFactor
{
    public function execute(User $user, string $entered): bool
    {
        return $this->consumeChallenge($user, $entered)
            || $this->consumeRecoveryCode($user, $entered);
    }

    private function consumeChallenge(User $user, string $entered): bool
    {
        $challenge = SecondFactorChallenge::query()
            ->where('user_id', $user->id)
            ->pending()
            ->latest('id')
            ->first();

        if ($challenge === null) {
            return false;
        }

        // El intento se cuenta ANTES de comprobar. Si se contara despues y la
        // comprobacion lanzara, el contador no subiria y el limite de
        // RNF-AUT-012 dejaria de existir sin que nada fallara.
        $challenge->increment('attempts');

        if (! $challenge->accepts($entered)) {
            return false;
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    private function consumeRecoveryCode(User $user, string $entered): bool
    {
        $normalized = trim($entered);

        if ($normalized === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $normalized): bool {
            // lockForUpdate: dos peticiones simultaneas con el mismo codigo
            // podrian gastarlo dos veces. Es un codigo de un solo uso y "un
            // solo uso" tiene que sobrevivir a la concurrencia.
            $codes = MfaRecoveryCode::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->lockForUpdate()
                ->get();

            foreach ($codes as $code) {
                if (Hash::check($normalized, $code->code_hash)) {
                    $code->forceFill(['used_at' => now()])->save();

                    return true;
                }
            }

            return false;
        });
    }
}
