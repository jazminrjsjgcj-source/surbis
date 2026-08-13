<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\MfaRecoveryCode;
use App\Domain\Identity\Models\SecondFactorChallenge;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SecondFactorChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Activar y desactivar el segundo factor. RF-AUT-016 exige que los cambios
 * queden auditados, y por eso las dos operaciones pasan por aqui y no por el
 * controlador.
 */
final class ManageSecondFactor
{
    public const RECOVERY_CODES = 8;

    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @return list<string> Los codigos de recuperacion en claro. Se muestran
     *                      una sola vez: en la base solo queda su hash.
     */
    public function enable(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $user->forceFill([
                'mfa_channel' => SecondFactorChannel::Email,
                'mfa_confirmed_at' => now(),
            ])->save();

            $codes = $this->regenerateRecoveryCodes($user);

            $this->audit->record('mfa.enabled', $user, [
                'channel' => SecondFactorChannel::Email->value,
            ]);

            return $codes;
        });
    }

    public function disable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'mfa_channel' => null,
                'mfa_confirmed_at' => null,
            ])->save();

            MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

            SecondFactorChallenge::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $this->audit->record('mfa.disabled', $user);
        });
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $code = Str::lower(Str::random(5).'-'.Str::random(5));
            $codes[] = $code;

            MfaRecoveryCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
            ]);
        }

        return $codes;
    }
}
