<?php

declare(strict_types=1);

namespace App\Application\Responses\Exceptions;

use RuntimeException;

/**
 * Una respuesta que no se acepta.
 *
 * Lleva una CLAVE, no un texto: la pantalla la traduce y la API la interpreta.
 */
final class ResponseRejected extends RuntimeException
{
    /** @param array<string, mixed> $replacements */
    private function __construct(
        public readonly string $key,
        public readonly array $replacements = [],
    ) {
        parent::__construct("response.{$key}");
    }

    /** El deployment no esta recibiendo respuestas ahora mismo. */
    public static function notApplying(): self
    {
        return new self('not_applying');
    }

    /** Falta una pregunta obligatoria, o un limite no se cumple. */
    public static function invalidAnswer(int $position, string $reason): self
    {
        return new self('invalid_answer', ['position' => $position, 'reason' => $reason]);
    }

    /** Se contesto algo que la logica condicional no mostraba. */
    public static function hiddenQuestion(int $position): self
    {
        return new self('hidden_question', ['position' => $position]);
    }

    /** RF-COL-024: hay datos personales sin consentimiento. */
    public static function consentMissing(): self
    {
        return new self('consent_missing');
    }

    /** RF-COL-023: en modo anonimo no se piden ni se aceptan datos. */
    public static function identityNotAllowed(): self
    {
        return new self('identity_not_allowed');
    }
}
