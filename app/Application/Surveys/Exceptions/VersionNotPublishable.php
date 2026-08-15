<?php

declare(strict_types=1);

namespace App\Application\Surveys\Exceptions;

use App\Domain\Surveys\PublicationProblem;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * RF-AO-PUB-006: bloquear la publicacion e indicar ubicacion y correccion.
 *
 * Lleva los problemas dentro, no un mensaje. La pantalla necesita saber EN
 * QUE PREGUNTA esta cada uno; con un texto suelto habria que buscarlo a mano
 * entre veinte.
 */
final class VersionNotPublishable extends RuntimeException
{
    /** @param Collection<int, PublicationProblem> $problems */
    public function __construct(public readonly Collection $problems)
    {
        parent::__construct('Esta version todavia no se puede publicar.');
    }
}
