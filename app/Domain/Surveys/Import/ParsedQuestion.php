<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Import;

use App\Domain\Surveys\Enums\QuestionType;

/**
 * Una pregunta lista para guardarse, salida del texto.
 *
 * Es una estructura del dominio y no un array porque el resultado de analizar
 * un texto tiene forma: quien la reciba no debe adivinar si la clave se llama
 * 'obligatoria' o 'is_required'.
 */
final class ParsedQuestion
{
    /** @param list<array{label: string, value: string, score: ?int}> $options */
    public function __construct(
        public readonly QuestionType $type,
        public readonly string $text,
        public readonly bool $isRequired,
        public readonly array $options,

        /*
         * La etiqueta de la opcion que tiene que estar elegida en la pregunta
         * ANTERIOR para que esta se muestre.
         *
         * Solo la anterior, no cualquiera. Una pregunta de seguimiento va
         * justo detras de la que la dispara, y permitir señalar preguntas
         * lejanas obligaria a inventar una forma de nombrarlas: numeros que
         * se rompen al reordenar, o etiquetas que hay que declarar antes.
         *
         * Condicionar a una pregunta que no es la inmediata se hace en el
         * constructor, que ya lo permite.
         */
        public readonly ?string $conditionOnPreviousOption = null,
    ) {}

    /** @return array<string, mixed> */
    public function toBuilderState(): array
    {
        return [
            'ulid' => null,
            'type' => $this->type->value,
            'text' => $this->text,
            'help' => null,
            'is_required' => $this->isRequired,
            'limits' => [],
            /*
             * La condicion NO se resuelve aqui.
             *
             * toBuilderState() devuelve una pregunta suelta, y la condicion
             * necesita el ULID de una opcion de OTRA pregunta que todavia no
             * existe. La resuelve QuestionTextParser cuando ya tiene la lista
             * entera.
             */
            'condition' => null,

            'options' => array_map(fn (array $option): array => [
                'ulid' => null,
                'label' => $option['label'],
                'value' => $option['value'],
                'score' => $option['score'],
                'display' => 'text',
                'appearance' => null,
            ], $this->options),
        ];
    }
}
