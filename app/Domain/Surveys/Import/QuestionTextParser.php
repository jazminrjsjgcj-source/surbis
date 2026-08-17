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

            /*
             * La condicion se comprueba contra la pregunta ANTERIOR.
             *
             * Aqui, no en parseHeader: la cabecera no sabe que hay antes. Y
             * comprobarlo al analizar —y no al guardar— permite decir la
             * linea exacta y que opciones si existen.
             */
            if ($header['condition'] !== null) {
                $problem = $this->checkCondition(
                    $header['condition'],
                    $questions->last(),
                    $line,
                );

                if ($problem !== null) {
                    $problems->push($problem);

                    continue;
                }
            }

            $questions->push(new ParsedQuestion(
                type: $header['type'],
                text: $content,
                isRequired: $header['required'],
                options: $header['options'],
                conditionOnPreviousOption: $header['condition'],
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
        $condition = null;

        foreach ($parts as $part) {
            if ($this->meansRequired($part)) {
                $required = true;

                continue;
            }

            if ($this->meansOptional($part)) {
                continue;
            }

            /*
             * La condicion: si "Etiqueta" en la anterior.
             *
             * Va entre las partes separadas por comas y ANTES del ':' de las
             * opciones, porque el troceado parte por el primer ':'. Escribirla
             * despues la metería dentro de la lista de opciones.
             */
            $etiqueta = $this->readCondition($part);

            if ($etiqueta !== null) {
                $condition = $etiqueta;

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

        return [[
            'type' => $type,
            'required' => $required,
            'options' => $options,
            'condition' => $condition,
        ], null];
    }

    /**
     * @return list<array{label: string, value: string, score: ?int}>
     */
    private function parseOptions(?string $part, QuestionType $type): array
    {
        /*
         * Los tipos con opciones FIJAS las traen del enum.
         *
         * Si/no no deja declararlas en el texto —son siempre las mismas— pero
         * el analizador las devuelve igual: la condicion las necesita para
         * comprobar que "Si" existe antes de guardar nada.
         */
        if ($type->hasFixedOptions()) {
            return array_map(
                fn (array $fija): array => [
                    'label' => $fija['label'],
                    'value' => $fija['value'],
                    'score' => $fija['score'],
                ],
                $type->fixedOptions(),
            );
        }

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

    /**
     * Lee `si "Etiqueta" en la anterior` y devuelve la etiqueta.
     *
     * Las comillas pueden ser rectas o tipograficas: quien escribe el texto
     * lo hace en un procesador que las cambia solo, y rechazarlo por eso
     * seria hacer perder el tiempo a alguien que lo escribio bien.
     *
     * Devuelve null si la parte no es una condicion, para que el bucle siga
     * probando si es un tipo.
     */
    private function readCondition(string $part): ?string
    {
        $normalizado = $this->normalize($part);

        if (! Str::startsWith($normalizado, 'si ')) {
            return null;
        }

        // Comillas rectas, tipograficas de apertura y de cierre.
        if (preg_match('/["\x{201C}\x{201D}\x{2018}\x{2019}\']([^"\x{201C}\x{201D}\x{2018}\x{2019}\']+)/u', $part, $coincidencias) !== 1) {
            return null;
        }

        return trim($coincidencias[1]);
    }

    /**
     * Comprueba que la condicion pueda cumplirse.
     *
     * Dos formas de que no: que no haya pregunta anterior, y que la anterior
     * no tenga esa opcion. Las dos dicen QUE opciones si existen, porque un
     * "no se puede" sin alternativas obliga a adivinar.
     */
    private function checkCondition(string $etiqueta, ?ParsedQuestion $anterior, int $line): ?ImportProblem
    {
        if ($anterior === null) {
            return new ImportProblem('condition_without_previous', $line);
        }

        if ($anterior->options === []) {
            /*
             * Una pregunta de texto libre no tiene opciones que elegir, asi
             * que no hay nada a lo que condicionar.
             */
            return new ImportProblem('condition_previous_has_no_options', $line, [
                'previous' => $anterior->text,
            ]);
        }

        $existe = collect($anterior->options)
            ->contains(fn (array $option): bool => $this->normalize($option['label']) === $this->normalize($etiqueta));

        if (! $existe) {
            return new ImportProblem('condition_option_not_found', $line, [
                'written' => $etiqueta,
                'known' => implode(', ', array_column($anterior->options, 'label')),
            ]);
        }

        return null;
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
