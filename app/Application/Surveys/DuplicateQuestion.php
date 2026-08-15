<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Models\SurveyQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Duplicar una pregunta con sus opciones. RF-AO-BLD-001.
 */
final class DuplicateQuestion
{
    public function __construct(
        private readonly BuilderGuard $guard,
        private readonly RecordAuditLog $audit,
    ) {}

    public function execute(SurveyQuestion $question): SurveyQuestion
    {
        $this->guard->ensureQuestionEditable($question);

        return DB::transaction(function () use ($question): SurveyQuestion {
            $existentes = SurveyQuestion::query()
                ->where('survey_version_id', $question->survey_version_id)
                ->lockForUpdate()
                ->get();

            $copia = SurveyQuestion::query()->create([
                'survey_version_id' => $question->survey_version_id,
                'organization_id' => $question->organization_id,
                'type' => $question->type,
                'text' => $question->text,
                'help' => $question->help,
                'is_required' => $question->is_required,
                'limits' => $question->limits,
                'position' => (int) ($existentes->max('position') ?? 0) + 1,
            ]);

            foreach ($question->options as $option) {
                $copia->options()->create([
                    'organization_id' => $option->organization_id,
                    'label' => $option->label,

                    /*
                     * El valor se copia tal cual.
                     *
                     * La restriccion de unicidad es POR PREGUNTA, asi que dos
                     * preguntas distintas pueden tener opciones con el mismo
                     * valor, y deben: una copia con valores cambiados no
                     * seria una copia, y quien duplica una pregunta para
                     * ajustarla espera encontrar lo mismo.
                     */
                    'value' => $option->value,
                    'score' => $option->score,
                    'display' => $option->display,
                    'appearance' => $option->appearance,
                    'position' => $option->position,
                ]);
            }

            $this->audit->record('survey_question.duplicated', $copia, [
                'from' => $question->ulid,
            ]);

            return $copia;
        });
    }
}
