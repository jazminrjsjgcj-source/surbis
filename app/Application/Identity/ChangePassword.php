<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Cambiar la propia contrasena estando dentro.
 *
 * Hasta ahora la unica forma era el enlace por correo, y sin correo
 * configurado eso deja a la gente sin ninguna via.
 */
final class ChangePassword
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $user, string $plain): void
    {
        DB::transaction(function () use ($user, $plain): void {
            $user->forceFill([
                'password' => Hash::make($plain),

                /*
                 * La marca se retira: a partir de aqui solo la conoce su
                 * dueño, que es la situacion normal.
                 */
                'password_set_by_other_at' => null,
            ])->save();

            /*
             * Se auditan TODAS las sesiones cerradas menos la actual.
             *
             * Cambiar la contrasena suele ser la reaccion a sospechar que
             * alguien mas la tiene. Si las demas sesiones siguieran vivas, el
             * cambio no serviria de nada.
             */
            $this->audit->record('user.password_changed', $user, [], actor: $user);
        });
    }

    /**
     * Ponerla al crear la cuenta, cuando no hay correo que invitar.
     *
     * Se marca QUIEN la sabe: mientras no se cambie, quien dio de alta puede
     * entrar como esa persona, y la auditoria registraria sus acciones con el
     * nombre del titular.
     */
    public function setOnCreation(User $user, string $plain, User $actor): void
    {
        DB::transaction(function () use ($user, $plain, $actor): void {
            $user->forceFill([
                'password' => Hash::make($plain),
                'password_set_by_other_at' => now(),
            ])->save();

            $this->audit->record('user.password_set_by_other', $user, [
                'reason' => 'mail_not_configured',
            ], actor: $actor);
        });
    }
}
