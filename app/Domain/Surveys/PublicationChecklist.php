<?php

declare(strict_types=1);

namespace App\Domain\Surveys;

use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Support\Collection;

/**
 * Que hace publicable a una version. RF-AO-PUB-005 y 006.
 *
 * Vive en el dominio y no en el caso de uso porque la pantalla necesita
 * ENSENAR los problemas antes de que nadie pulse publicar. Si la validacion
 * solo existiera dentro de Publish, la unica forma de saber si una encuesta
 * esta lista seria intentar publicarla y leer el error.
 *
 * LO QUE TODAVIA NO SE COMPRUEBA, y RF-AO-PUB-005 menciona:
 *
 *   logica condicional   no existe (TASK-021)
 *   imagenes             no existe la biblioteca (Fase 5)
 *
 * Cuando lleguen, sus comprobaciones se anaden aqui. Queda escrito para que
 * nadie de por hecho que "valida todo lo que dice el requisito".
 */
final class PublicationChecklist
{
    /** Menos de dos opciones no es una eleccion. */
    private const MIN_OPTIONS = 2;

    /** @return Collection<int, PublicationProblem> */
    public function problems(SurveyVersion $version): Collection
    {
        $version->loadMissing(['questions.options']);
        $problems = collect();

        if ($version->questions->isEmpty()) {
            // Sin preguntas no hay nada que contestar. Es el unico problema
            // que no tiene ubicacion, porque no hay donde ubicarlo.
            return $problems->push(new PublicationProblem('no_questions'));
        }

        foreach ($version->questions as $question) {
            $problems = $problems->concat($this->problemsIn($question));
        }

        return $problems;
    }

    public function isPublishable(SurveyVersion $version): bool
    {
        return $this->problems($version)->isEmpty();
    }

    /** @return Collection<int, PublicationProblem> */
    private function problemsIn(SurveyQuestion $question): Collection
    {
        $problems = collect();
        $position = $question->position;

        if (trim($question->text) === '') {
            $problems->push(new PublicationProblem('question_without_text', $position));
        }

        if (! $question->type->hasOptions()) {
            return $problems;
        }

        $options = $question->options;

        if ($options->count() < self::MIN_OPTIONS) {
            $problems->push(new PublicationProblem('too_few_options', $position, [
                'min' => self::MIN_OPTIONS,
                'count' => $options->count(),
            ]));
        }

        foreach ($options as $option) {
            if (trim($option->label) === '') {
                // RF-AO-BLD-005: la etiqueta es el nombre accesible. Una
                // opcion sin nombre no se puede elegir con lector de
                // pantalla, y eso no puede llegar a produccion.
                $problems->push(new PublicationProblem('option_without_label', $position));

                break;
            }
        }

        return $problems;
    }
}
