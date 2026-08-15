<?php

declare(strict_types=1);

namespace App\Application\Surveys\Exceptions;

use RuntimeException;

/**
 * RF-AO-PUB-007 y RF-AO-BLD-009: una version publicada no se edita, se abre
 * en solo lectura.
 *
 * Vive como excepcion y no como comprobacion suelta porque TODOS los casos de
 * uso del constructor tienen que respetarla, y se invocaran tambien desde la
 * API. Una regla que solo vive en la puerta HTTP deja de existir en cuanto
 * aparece otra puerta.
 */
final class VersionNotEditable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esa version esta publicada. Abre un borrador nuevo para cambiarla.');
    }
}
