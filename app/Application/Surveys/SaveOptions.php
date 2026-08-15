<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Enums\OptionDisplay;
use App\Domain\Surveys\Models\SurveyQuestion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Las opciones de una pregunta, de una vez. RF-AO-BLD-003 y 010.
 *
 * Se guarda la lista COMPLETA en lugar de operaciones sueltas de anadir,
 * editar y borrar. El motivo es el orden: con operaciones sueltas, reordenar
 * y editar serian dos peticiones que pueden cruzarse, y entre las dos las
 * posiciones quedan en un estado que ninguna de las dos previo.
 */
final class SaveOptions
{
    public function __construct(
        private readonly BuilderGuard $guard,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  list<array{ulid: ?string, label: string, value: string, score: ?int, display: OptionDisplay, appearance: ?array<string, mixed>}>  $options
     */
    public function execute(SurveyQuestion $question, array $options): void
    {
        $this->guard->ensureQuestionEditable($question);

        if (! $question->hasOptions()) {
            throw new InvalidArgumentException(
                "Una pregunta de tipo {$question->type->value} no admite opciones."
            );
        }

        /*
         * RF-AO-BLD-010, comprobado ANTES de tocar nada.
         *
         * La base tambien lo impide, y ahi esta la garantia de verdad. Aqui se
         * comprueba para poder decir cual es el valor repetido: una violacion
         * de restriccion solo dice que la hubo.
         */
        $valores = array_column($options, 'value');
        $repetidos = array_diff_assoc($valores, array_unique($valores));

        if ($repetidos !== []) {
            throw new InvalidArgumentException(
                'Hay valores de opcion repetidos: '.implode(', ', array_unique($repetidos))
            );
        }

        DB::transaction(function () use ($question, $options): void {
            $existentes = $question->options()->lockForUpdate()->get()->keyBy('ulid');
            $conservados = [];

            foreach ($options as $indice => $datos) {
                $ulid = $datos['ulid'] ?? null;
                $atributos = [
                    'organization_id' => $question->organization_id,
                    'label' => $datos['label'],
                    'value' => $datos['value'],
                    'score' => $datos['score'],
                    'display' => $datos['display'],
                    'appearance' => $datos['appearance'],
                    'position' => $indice + 1,
                ];

                if ($ulid !== null && $existentes->has($ulid)) {
                    /*
                     * Se actualiza la fila existente en lugar de borrar y
                     * recrear. Su ulid es lo que las respuestas guardadas
                     * usarian para referirse a ella: recrearla romperia ese
                     * vinculo aunque el contenido fuera identico.
                     */
                    $existentes[$ulid]->forceFill($atributos)->save();
                    $conservados[] = $ulid;

                    continue;
                }

                $question->options()->create($atributos);
            }

            // Las que ya no vienen en la lista se retiran.
            $question->options()
                ->whereNotIn('ulid', $conservados)
                ->whereIn('ulid', $existentes->keys())
                ->delete();

            $this->audit->record('survey_question.options_saved', $question, [
                'count' => count($options),
            ]);
        });
    }
}
