<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

/**
 * La organizacion en la que el usuario esta operando ahora mismo.
 *
 * Guarda el id de la MEMBRESIA, no el de la organizacion. Asi cada peticion
 * vuelve a leer rol y estado: si a alguien le suspenden la membresia mientras
 * tiene la sesion abierta, la pierde en la siguiente peticion y no cuando
 * caduque la sesion. RA-006.
 *
 * Ese identificador no llega nunca del navegador. RF-GEN-001 y RNF-COL-001.
 */
final class ActiveOrganizationContext
{
    public const SESSION_KEY = 'identity.active_membership_id';

    public function __construct(private readonly Session $session) {}

    public function remember(Membership $membership): void
    {
        $this->session->put(self::SESSION_KEY, $membership->id);
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Devuelve la membresia activa guardada en sesion, revalidada contra la
     * base. Null si no hay ninguna, si ya no sirve, o si pertenece a otro
     * usuario.
     */
    public function current(User $user): ?Membership
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        $membership = $this->usableMemberships($user)->firstWhere('id', $id);

        if ($membership === null) {
            $this->forget();
        }

        return $membership;
    }

    /**
     * Membresias con las que el usuario puede operar: activas y en una
     * organizacion activa.
     *
     * @return Collection<int, Membership>
     */
    public function usableMemberships(User $user): Collection
    {
        return $user->memberships()
            ->active()
            ->with('organization')
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->organization->isActive())
            ->values();
    }
}
