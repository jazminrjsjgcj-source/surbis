<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Import;

use App\Domain\Surveys\Enums\QuestionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Convierte un texto en preguntas. TASK-025, D-027.
 *
 * El formato:
 *
 *     [obligatorias, una opcion: Si / Mas o menos / No]
 *     ¿Te atendieron con amabilidad?
 *     ¿El tiempo de espera fue razonable?
 *
 *     [opcionales, texto largo]
 *     ¿Algo que mejorarias?
 *
 * Todo lo que va bajo una cabecera hereda su tipo, su obligatoriedad y sus
 * opciones. Es lo que hace util la importacion: en una encuesta de
 * satisfaccion la misma escala se repite en ocho preguntas, y escribirla ocho
 * veces seria peor que usar el constructor.
 *
 * Si algo esta mal NO se importa nada. Importar a medias deja una encuesta
 * que hay que revisar entera para saber que entro y que no.
 */
final class QuestionTextParser
{
    public function __construct(private readonly QuestionTypeVocabulary $vocabulary) {}

    /** @return Collection<int, ImportProblem> */
    public function problems(string $text): Collection
    {
        return $this->analyze($text)['problems'];
    }

    /**
     * @return Collection<int, ParsedQuestion>
     *
     * @throws \InvalidArgumentException si el texto tiene problemas. Se
     *                                   comprueba antes con problems().
     */
    public function parse(string $text): Collection
    {
        $result = $this->analyze($text);

        if ($result['problems']->isNotEmpty()) {
            throw new \InvalidArgumentException('El texto tiene problemas. Usa problems() antes.');
        }

        return $result['questions'];
    }

    /**
     * @return array{questions: Collection<int, ParsedQuestion>, problems: Collection<int, ImportProblem>}
     */
    private function analyze(string $text): array
    {
        $questions = collect();
        $problems = collect();
        $header = null;

        foreach (preg_split('/\R/', $text) ?: [] as $index => $raw) {
            $line = $index + 1;
            $content = trim($raw);

            if ($content === '') {
                continue;
            }

            if (Str::startsWith($content, '[')) {
                [$header, $problem] = $this->parseHeader($content, $line);

                if ($problem !== null) {
                    $problems->push($problem);
                }

                continue;
            }

            /*
             * Una pregunta antes de la primera cabecera no tiene tipo.
             *
             * Se podria suponer uno por defecto, pero entonces el texto
             * entraria con un tipo que nadie pidio y habria que revisar cada
             * pregunta para descubrirlo.
             */
            if ($header === null) {
                $problems->push(new ImportProblem('question_without_block', $line));

                continue;
            }

            $questions->push(new ParsedQuestion(
                type: $header['type'],
                text: $content,
                isRequired: $header['required'],
                options: $header['options'],
            ));
        }

        if ($questions->isEmpty() && $problems->isEmpty()) {
            $problems->push(new ImportProblem('nothing_to_import', 1));
        }

        return ['questions' => $questions, 'problems' => $problems];
    }

    /**
     * @return array{0: array{type: QuestionType, required: bool, options: list<array{label: string, value: string, score: ?int}>}|null, 1: ImportProblem|null}
     */
    private function parseHeader(string $content, int $line): array
    {
        if (! Str::endsWith($content, ']')) {
            return [null, new ImportProblem('unclosed_block', $line)];
        }

        $inner = trim(Str::between($content, '[', ']'));

        // El separador ':' parte obligatoriedad y tipo de la lista de
        // opciones. Solo el PRIMERO: una opcion puede contener ':'.
        [$settings, $optionsPart] = array_pad(explode(':', $inner, 2), 2, null);

        $parts = collect(explode(',', (string) $settings))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        $required = false;
        $type = null;

        foreach ($parts as $part) {
            if ($this->meansRequired($part)) {
                $required = true;

                continue;
            }

            if ($this->meansOptional($part)) {
                continue;
            }

            $type = $this->vocabulary->resolve($part);

            if ($type === null) {
                return [null, new ImportProblem('unknown_type', $line, [
                    'written' => $part,
                    'known' => implode(', ', $this->vocabulary->canonicalNames()),
                ])];
            }
        }

        if ($type === null) {
            return [null, new ImportProblem('block_without_type', $line)];
        }

        $options = $this->parseOptions($optionsPart, $type);

        if ($type->hasOptions() && count($options) < 2) {
            // Menos de dos opciones no es una eleccion. Y sin ninguna, la
            // pregunta no se puede contestar.
            return [null, new ImportProblem('block_without_options', $line, [
                'type' => $type->value,
            ])];
        }

        return [['type' => $type, 'required' => $required, 'options' => $options], null];
    }

    /**
     * @return list<array{label: string, value: string, score: ?int}>
     */
    private function parseOptions(?string $part, QuestionType $type): array
    {
        if ($part === null || trim($part) === '' || ! $type->hasOptions()) {
            return [];
        }

        $labels = collect(explode('/', $part))
            ->map(fn (string $label): string => trim($label))
            ->filter()
            ->values();

        $total = $labels->count();

        return $labels->map(function (string $label, int $index) use ($type, $total): array {
            return [
                'label' => $label,
                // El valor se deriva de la etiqueta y despues permanece
                // estable: cambiar el texto de una opcion no debe cambiar lo
                // que quedo guardado en las respuestas.
                'value' => Str::slug($label) ?: 'opcion-'.($index + 1),

                /*
                 * Puntuacion DESCENDENTE. Decision del area usuaria: las
                 * opciones se declaran de mejor a peor.
                 *
                 * Con tres opciones son 3, 2, 1. El maximo depende de cuantas
                 * haya, asi que una escala de tres y otra de cinco no son
                 * comparables directamente: eso se normaliza en la analitica
                 * (Fase 12), no aqui.
                 */
                'score' => $type->isScored() ? $total - $index : null,
            ];
        })->all();
    }

    private function meansRequired(string $part): bool
    {
        return in_array($this->normalize($part), ['obligatorias', 'obligatoria', 'requeridas', 'requerida'], true);
    }

    private function meansOptional(string $part): bool
    {
        return in_array($this->normalize($part), ['opcionales', 'opcional'], true);
    }

    private function normalize(string $value): string
    {
        return trim(Str::lower(Str::ascii($value)));
    }
}
