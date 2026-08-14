<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Surveys\Models\Survey;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;

final class SurveyPolicy
{
    public function __construct(private readonly Request $request) {}

    public function viewAny(User $user): bool
    {
        return $this->activeMembership()?->isAdmin() === true;
    }

    public function view(User $user, Survey $survey): bool
    {
        return $this->belongsToActiveOrganization($survey);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Una encuesta archivada no se edita: RF-AO-PUB-008 dice que archivar
     * impide nuevas aplicaciones, y editarla mientras esta archivada seria
     * preparar una aplicacion que no puede ocurrir.
     */
    public function update(User $user, Survey $survey): bool
    {
        return $this->belongsToActiveOrganization($survey) && ! $survey->isArchived();
    }

    public function archive(User $user, Survey $survey): bool
    {
        return $this->belongsToActiveOrganization($survey);
    }

    /**
     * RF-AO-SUR-004: sin eliminacion fisica si hay versiones publicadas o
     * respuestas.
     *
     * Se expresa como permiso y no como comprobacion suelta en el
     * controlador, para que la pantalla pueda preguntar lo mismo que decide
     * el servidor y no ofrecer un boton que va a fallar.
     */
    public function delete(User $user, Survey $survey): bool
    {
        return $this->belongsToActiveOrganization($survey) && ! $survey->hasPublishedHistory();
    }

    private function belongsToActiveOrganization(Survey $survey): bool
    {
        $membership = $this->activeMembership();

        return $membership !== null
            && $membership->isAdmin()
            && $membership->organization_id === $survey->organization_id;
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
