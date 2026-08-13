<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * La sesion parcial: entre la contrasena correcta y el codigo correcto.
 *
 * En este intervalo el usuario NO esta autenticado. Solo hay un identificador
 * guardado en la sesion. Es lo que pide RF-AUT-007 —verificar antes de crear
 * la sesion definitiva— y lo que impide que alguien con la contrasena pero
 * sin acceso al correo llegue a ninguna pantalla del sistema.
 */
final class PendingSecondFactor
{
    public const USER_KEY = 'identity.second_factor.user_id';

    public const STARTED_KEY = 'identity.second_factor.started_at';

    public const VERIFIED_KEY = 'identity.second_factor.verified_user_id';

    public function __construct(private readonly Session $session) {}

    public function start(User $user): void
    {
        $this->session->put(self::USER_KEY, $user->id);
        $this->session->put(self::STARTED_KEY, now()->toIso8601String());
    }

    public function user(): ?User
    {
        $id = $this->session->get(self::USER_KEY);

        if (! is_int($id)) {
            return null;
        }

        return User::query()->find($id);
    }

    public function forget(): void
    {
        $this->session->forget([self::USER_KEY, self::STARTED_KEY]);
    }

    /**
     * Marca que este usuario ya paso el segundo factor en esta sesion.
     *
     * Se guarda el id y no un booleano: si el mismo navegador cambia de
     * usuario, un `true` suelto le regalaria la verificacion al siguiente.
     */
    public function markVerified(User $user): void
    {
        $this->forget();
        $this->session->put(self::VERIFIED_KEY, $user->id);
    }

    public function wasVerifiedBy(User $user): bool
    {
        return $this->session->get(self::VERIFIED_KEY) === $user->id;
    }

    public function forgetVerification(): void
    {
        $this->session->forget(self::VERIFIED_KEY);
    }
}
