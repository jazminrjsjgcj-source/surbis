<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Application\Identity\Exceptions\AuthenticationDenied;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Collection;

/**
 * Verifica credenciales y las tres condiciones que RF-AUT-005 exige por
 * separado: usuario suspendido, organizacion suspendida y membresia
 * suspendida.
 *
 * Distinguirlas importa. "No puedes entrar" no le dice a nadie a quien
 * llamar; "tu organizacion esta suspendida" si.
 */
final class AuthenticateUser
{
    private readonly StatefulGuard $guard;

    /**
     * El contenedor de Laravel NO tiene alias para StatefulGuard: 'auth'
     * apunta a Auth\Factory y 'auth.driver' a Auth\Guard, y ahi se acaba.
     * Inyectar StatefulGuard directamente reventaria al resolver la clase.
     * Se pide la fabrica y de ella sale el guard de sesion.
     */
    public function __construct(AuthFactory $auth)
    {
        /** @var StatefulGuard $guard */
        $guard = $auth->guard();

        $this->guard = $guard;
    }

    /**
     * @throws AuthenticationDenied
     */
    public function execute(string $email, string $password, bool $remember = false): User
    {
        // attempt() inicia sesion si las credenciales son correctas. Las
        // comprobaciones posteriores pueden revocarla con logout(). Se hace
        // asi, y no verificando el hash a mano, para no perder la proteccion
        // contra ataques de temporizacion que ya trae el guard.
        if (! $this->guard->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw AuthenticationDenied::invalidCredentials();
        }

        /** @var User $user */
        $user = $this->guard->user();

        if (! $user->isActive()) {
            $this->deny();

            throw AuthenticationDenied::userSuspended();
        }

        // El administrador de plataforma no pertenece a ninguna organizacion
        // cliente, asi que no se le exige membresia. RA-001.
        if ($user->isPlatformAdmin()) {
            return $user;
        }

        $this->guardAgainstUnusableMemberships($user);

        return $user;
    }

    /**
     * @throws AuthenticationDenied
     */
    private function guardAgainstUnusableMemberships(User $user): void
    {
        /** @var Collection<int, Membership> $memberships */
        $memberships = $user->memberships()->with('organization')->get();

        if ($memberships->isEmpty()) {
            $this->deny();

            throw AuthenticationDenied::withoutMembership();
        }

        $active = $memberships->filter(fn (Membership $membership): bool => $membership->isActive());

        if ($active->isEmpty()) {
            $this->deny();

            throw AuthenticationDenied::membershipSuspended();
        }

        $usable = $active->filter(
            fn (Membership $membership): bool => $membership->organization->isActive()
        );

        if ($usable->isEmpty()) {
            $this->deny();

            throw AuthenticationDenied::organizationSuspended();
        }
    }

    private function deny(): void
    {
        $this->guard->logout();
    }
}
