<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Models\SecondFactorChallenge;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SecondFactorChannel;
use App\Domain\Identity\SecondFactorCode;
use App\Notifications\SecondFactorCodeNotification;
use Illuminate\Support\Facades\DB;

final class SendSecondFactorCode
{
    public function execute(User $user): SecondFactorChallenge
    {
        $code = SecondFactorCode::generate();

        $challenge = DB::transaction(function () use ($user, $code): SecondFactorChallenge {
            // Los retos anteriores dejan de servir. Sin esto, pedir un codigo
            // nuevo dejaria vivos los viejos y cada peticion ampliaria la
            // ventana de un atacante en lugar de reducirla.
            SecondFactorChallenge::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return SecondFactorChallenge::query()->create([
                'user_id' => $user->id,
                'channel' => SecondFactorChannel::Email,
                'code_hash' => $code->hash(),
                'expires_at' => now()->addMinutes(SecondFactorCode::MINUTES_VALID),
            ]);
        });

        // El codigo en claro existe solo aqui, el tiempo de meterlo en el
        // correo. No se devuelve ni se registra en ningun sitio.
        $user->notify(new SecondFactorCodeNotification($code));

        return $challenge;
    }
}
