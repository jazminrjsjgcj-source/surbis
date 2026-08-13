<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Str;

/**
 * Cambia la contrasena a partir de un token valido.
 *
 * El broker de Laravel ya garantiza que el token esta hasheado en la base,
 * es de un solo uso y vence: RF-AUT-010 y RNF-AUT-009. Lo que se anade aqui
 * es la rotacion del remember_token, que junto con el middleware
 * AuthenticateSession cierra las sesiones abiertas en otros dispositivos.
 * RF-AUT-013.
 */
final class ResetPassword
{
    public function __construct(
        private readonly PasswordBroker $broker,
        private readonly AcceptInvitation $accept,
    ) {}

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     * @return string Estado del broker, para traducir en la presentacion.
     */
    public function execute(array $credentials): string
    {
        return $this->broker->reset(
            $credentials,
            function (CanResetPassword $user, string $password): void {
                /** @var User $user */
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // P-015: si la contrasena se definia desde una invitacion,
                // usarla la acepta y activa la membresia. Un restablecimiento
                // normal no toca ninguna membresia, porque AcceptInvitation
                // solo mira las invitadas y nunca usadas.
                $this->accept->execute($user);

                event(new PasswordResetEvent($user));
            }
        );
    }
}
