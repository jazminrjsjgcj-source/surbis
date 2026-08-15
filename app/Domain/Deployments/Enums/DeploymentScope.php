<?php

declare(strict_types=1);

namespace App\Domain\Deployments\Enums;

/**
 * Donde se aplica. Decision del area usuaria, 16 ago 2026.
 *
 * UN SOLO alcance por deployment. Si una encuesta debe aplicarse en cinco
 * sucursales, son cinco deployments.
 *
 * Es mas repetitivo de crear y mucho mas claro de leer: cada deployment dice
 * exactamente donde aplica, y cerrar uno no afecta a los demas. La
 * alternativa —un deployment con varias sucursales— obligaria a decidir que
 * pasa al quitar una que ya tiene respuestas.
 */
enum DeploymentScope: string
{
    case Organization = 'organization';
    case Branch = 'branch';
    case Area = 'area';
    case Device = 'device';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
