<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Models\Survey;

/**
 * Nombre y descripcion de la encuesta. RF-AO-SUR-005.
 *
 * Estos dos viven en `surveys` y no en la version: identifican a la encuesta
 * en las listas, y cambiarles el nombre no cambia lo que se pregunto. Lo que
 * SI afecta a lo preguntado —introduccion, agradecimiento, modo de identidad—
 * vive en `settings` de la version, y ahi manda RF-AO-SUR-007.
 */
final class UpdateSurveyGeneral
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array{name: string, description: ?string} $attributes */
    public function execute(Survey $survey, array $attributes): Survey
    {
        $before = ['name' => $survey->name, 'description' => $survey->description];

        $survey->fill($attributes)->save();

        $this->audit->record('survey.updated', $survey, [
            'name_before' => $before['name'],
            'name_after' => $survey->name,
        ]);

        return $survey;
    }
}
