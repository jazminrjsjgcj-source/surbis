<?php

declare(strict_types=1);

namespace App\Application\Surveys\Exceptions;

use App\Domain\Surveys\Models\SurveyVersion;
use RuntimeException;

/**
 * Otra persona guardo este borrador mientras tu lo editabas.
 *
 * Lleva la version actual del servidor para que la pantalla pueda mostrar lo
 * que hay ahora sin una segunda peticion, y para que el cliente sepa contra
 * que numero reintentar.
 *
 * IMPORTANTE: no existe forma de saltarse esta comprobacion. "Sobrescribir lo
 * del otro" no es guardar ignorando lock_version: es releer y reintentar con
 * el numero nuevo. Si hubiera un parametro que se la saltara, ese parametro
 * acabaria usandose desde otro sitio "porque da menos problemas" y la
 * proteccion desapareceria sin que nadie lo notara.
 */
final class VersionConflict extends RuntimeException
{
    public function __construct(
        public readonly int $expected,
        public readonly int $actual,
        public readonly SurveyVersion $current,
    ) {
        parent::__construct(
            "El borrador cambio mientras lo editabas. Esperabas la version {$expected} y hay la {$actual}."
        );
    }
}
