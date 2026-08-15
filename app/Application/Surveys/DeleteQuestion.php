<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Models\SurveyQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Borrar una pregunta del BORRADOR. RF-AO-BLD-001.
 *
 * Aqui si se borra de verdad, y no contradice RF-GEN-010: una pregunta de un
 * borrador nunca ha sido contestada. Lo que no se puede borrar es una pregunta
 * de una version publicada, y eso lo impide BuilderGuard.
 */
final class DeleteQuestion
{
    public function __construct(
        private readonly BuilderGuard $guard,
        private readonly RecordAuditLog $audit,
    ) {}

    public function execute(SurveyQuestion $question): void
    {
        $this->guard->ensureQuestionEditable($question);

        DB::transaction(function () use ($question): void {
            $version = $question->version;
            $texto = $question->text;

            $question->delete();

            /*
             * Las posiciones se renumeran para que no queden huecos.
             *
             * Un hueco no rompe nada por si mismo, pero convierte la posicion
             * en un numero que ya no corresponde con el orden visible, y la
             * siguiente operacion que calcule "la ultima + 1" empieza a dejar
             * espacios crecientes.
             */
            $restantes = SurveyQuestion::query()
                ->where('survey_version_id', $version->id)
                ->orderBy('position')
                ->lockForUpdate()
                ->get();

            foreach ($restantes as $indice => $restante) {
                $restante->forceFill(['position' => $indice + 1])->save();
            }

            $this->audit->record('survey_question.deleted', $version, [
                'text' => $texto,
            ]);
        });
    }
}
