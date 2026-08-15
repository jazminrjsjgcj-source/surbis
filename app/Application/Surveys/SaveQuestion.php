<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\QuestionLimits;
use Illuminate\Support\Facades\DB;

/**
 * Anadir y editar preguntas. RF-AO-BLD-001 y 002.
 */
final class SaveQuestion
{
    public function __construct(
        private readonly BuilderGuard $guard,
        private readonly RecordAuditLog $audit,
    ) {}

    /** @param array{type: QuestionType, text: string, help: ?string, is_required: bool, limits: QuestionLimits} $data */
    public function create(SurveyVersion $version, array $data): SurveyQuestion
    {
        $this->guard->ensureEditable($version);

        return DB::transaction(function () use ($version, $data): SurveyQuestion {
            /*
             * La posicion se calcula bajo bloqueo. Sin el, dos peticiones
             * simultaneas leerian el mismo maximo y la segunda chocaria con
             * la restriccion de unicidad. RNF-AO-BLD-002.
             *
             * Se traen las filas y se calcula en PHP: PostgreSQL no permite
             * FOR UPDATE con funciones de agregacion (T-026).
             */
            $posiciones = SurveyQuestion::query()
                ->where('survey_version_id', $version->id)
                ->lockForUpdate()
                ->get();

            $question = SurveyQuestion::query()->create([
                'survey_version_id' => $version->id,
                'organization_id' => $version->organization_id,
                'type' => $data['type'],
                'text' => $data['text'],
                'help' => $data['help'],
                'is_required' => $data['is_required'],
                'limits' => $data['limits'],
                'position' => (int) ($posiciones->max('position') ?? 0) + 1,
            ]);

            $this->audit->record('survey_question.created', $question, [
                'type' => $data['type']->value,
            ]);

            return $question;
        });
    }

    /** @param array{type: QuestionType, text: string, help: ?string, is_required: bool, limits: QuestionLimits} $data */
    public function update(SurveyQuestion $question, array $data): SurveyQuestion
    {
        $this->guard->ensureQuestionEditable($question);

        return DB::transaction(function () use ($question, $data): SurveyQuestion {
            $tipoAnterior = $question->type;

            $question->fill([
                'type' => $data['type'],
                'text' => $data['text'],
                'help' => $data['help'],
                'is_required' => $data['is_required'],
                'limits' => $data['limits'],
            ])->save();

            /*
             * Cambiar a un tipo sin opciones borra las que hubiera.
             *
             * Un texto libre con cuatro opciones colgando es un registro que
             * nadie sabe interpretar: no se muestran, pero estan, y la
             * siguiente pantalla que las lea decidira por su cuenta que hacer
             * con ellas.
             */
            if ($tipoAnterior->hasOptions() && ! $data['type']->hasOptions()) {
                $question->options()->delete();
            }

            $this->audit->record('survey_question.updated', $question, [
                'type_before' => $tipoAnterior->value,
                'type_after' => $data['type']->value,
            ]);

            return $question;
        });
    }
}
