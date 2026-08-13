<?php

declare(strict_types=1);

namespace App\Application\Identity\Exceptions;

use RuntimeException;

/**
 * RF-AO-COL-006: no se puede retirar ni suspender al ultimo administrador
 * activo sin sustitucion.
 *
 * Es la regla mas facil de romper de toda la fase, y la que peor se rompe: la
 * organizacion se queda sin nadie que pueda administrarla, y no hay pantalla
 * desde la que arreglarlo. Solo el administrador de plataforma, con un
 * permiso de soporte auditado, podria intervenir.
 */
final class LastAdministrator extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Es el ultimo administrador activo.');
    }
}
