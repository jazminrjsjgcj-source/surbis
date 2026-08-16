<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Responses\Models\Response;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

final class ResponsePolicy
{
    public function __construct(private readonly Request $request) {}

    public function viewAny(User $user): bool
    {
        return $this->activeMembership()?->isAdmin() === true;
    }

    public function view(User $user, Response $response): bool
    {
        return $this->belongsToActiveOrganization($response);
    }

    /**
     * RF-AO-RES-005: las respuestas originales NO se editan desde el panel.
     *
     * No hay metodo update a proposito. Lo unico que se puede hacer es
     * invalidarlas, que anade una marca al lado sin tocar lo contestado.
     */
    public function invalidate(User $user, Response $response): bool
    {
        return $this->belongsToActiveOrganization($response);
    }

    /**
     * Ver los datos de quien contesto.
     *
     * En modo identificado u opcional basta con pertenecer a la organizacion.
     * En CONFIDENCIAL hace falta una autorizacion temporal vigente: quien la
     * pide no es quien la aprueba, caduca sola y queda auditada
     * (confidential_access_grants, Fase 1).
     */
    public function viewIdentity(User $user, Response $response): bool
    {
        if (! $this->belongsToActiveOrganization($response)) {
            return false;
        }

        if (! $response->isConfidential()) {
            return true;
        }

        $membership = $this->activeMembership();

        return $user->confidentialAccessGrants()
            ->where('organization_id', $membership?->organization_id)
            ->effective()
            ->exists();
    }

    private function belongsToActiveOrganization(Response $response): bool
    {
        $membership = $this->activeMembership();

        return $membership !== null
            && $membership->isAdmin()
            && $membership->organization_id === $response->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
