<?php

declare(strict_types=1);

namespace App\Domain\Surveys;

/**
 * Un motivo por el que una version no se puede publicar.
 *
 * RF-AO-PUB-006 pide indicar UBICACION y CORRECCION, no solo que algo esta
 * mal. Por eso lleva la posicion de la pregunta y una clave de traduccion en
 * lugar de un texto suelto: "hay un error en la encuesta" obliga a buscarlo a
 * mano entre veinte preguntas.
 */
final class PublicationProblem
{
    /** @param array<string, mixed> $replacements */
    public function __construct(
        public readonly string $key,
        public readonly ?int $questionPosition = null,
        public readonly array $replacements = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'question_position' => $this->questionPosition,
            'replacements' => $this->replacements,
        ];
    }
}
