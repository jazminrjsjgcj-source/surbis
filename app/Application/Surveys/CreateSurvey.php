<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Surveys\Enums\SurveyVersionStatus;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Support\Facades\DB;

/**
 * RF-AO-SUR-006: al crear se genera una primera version en borrador.
 * RNF-AO-SUR-003: encuesta y primera version en una transaccion.
 *
 * La transaccion no es formalidad. Una encuesta sin ninguna version es un
 * registro que no se puede editar ni publicar ni borrar: aparece en la lista
 * y no lleva a ningun sitio. Si la segunda insercion falla, la primera no
 * debe quedarse.
 */
final class CreateSurvey
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array{name: string, description: ?string} $attributes */
    public function execute(Organization $organization, User $author, array $attributes): Survey
    {
        return DB::transaction(function () use ($organization, $author, $attributes): Survey {
            $survey = new Survey($attributes);
            $survey->organization()->associate($organization);
            $survey->creator()->associate($author);
            $survey->save();

            SurveyVersion::query()->create([
                'survey_id' => $survey->id,
                'organization_id' => $organization->id,
                'version_number' => 1,

                // Explicito por el mismo motivo que en OpenDraft: el modelo
                // que devuelve create() no carga lo que no se le paso (T-027).
                'status' => SurveyVersionStatus::Draft,
            ]);

            $this->audit->record('survey.created', $survey, [
                'name' => $survey->name,
            ]);

            return $survey;
        });
    }
}
