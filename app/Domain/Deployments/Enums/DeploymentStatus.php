<?php

declare(strict_types=1);

namespace App\Domain\Deployments\Enums;

/**
 * Estado declarado de un deployment. RF-AO-DEP-005.
 *
 * OJO: no es lo mismo que si esta aplicando AHORA. Uno "activo" con fecha de
 * inicio manana todavia no recibe respuestas. Eso lo resuelve
 * Deployment::isApplying(), y la distincion importa: confundirlas haria que
 * el listado mintiera.
 */
enum DeploymentStatus: string
{
    case Active = 'active';

    /** Pausado temporalmente. Puede volver a activarse. */
    case Suspended = 'suspended';

    /**
     * Terminado para siempre.
     *
     * Un deployment cerrado NO se reabre: si hace falta otra vez, se crea uno
     * nuevo. Reabrirlo mezclaria en el mismo registro dos periodos distintos
     * de aplicacion, y las respuestas no podrian distinguirlos.
     */
    case Closed = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
