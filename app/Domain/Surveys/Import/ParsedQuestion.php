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
