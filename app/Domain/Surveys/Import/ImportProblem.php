<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Import;

/**
 * Un motivo por el que un texto no se puede importar.
 *
 * Lleva el NUMERO DE LINEA. Sin el, "hay un tipo desconocido" en un texto de
 * cuarenta lineas obliga a revisarlas todas.
 */
final class ImportProblem
{
    /** @param array<string, mixed> $replacements */
    public function __construct(
        public readonly string $key,
        public readonly int $line,
        public readonly array $replacements = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'line' => $this->line,
            'replacements' => $this->replacements,
        ];
    }
}
