<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Enums\SurveyStatus;
use App\Domain\Surveys\Models\Survey;

final class ArchiveSurvey
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * RF-AO-PUB-008: archivar impide nuevas aplicaciones y no borra respuestas
     * historicas. No hay ningun delete aqui, y ese es el punto.
     */
    public function archive(Survey $survey): void
    {
        $survey->forceFill([
            'status' => SurveyStatus::Archived,
            'archived_at' => now(),
        ])->save();

        $this->audit->record('survey.archived', $survey);
    }

    public function activate(Survey $survey): void
    {
        // Vuelve al estado que le corresponde por sus versiones, no siempre a
        // borrador: una encuesta con version publicada sigue publicada.
        $survey->forceFill([
            'status' => $survey->publishedVersion()->exists()
                ? SurveyStatus::Published
                : SurveyStatus::Draft,
            'archived_at' => null,
        ])->save();

        $this->audit->record('survey.activated', $survey);
    }
}
