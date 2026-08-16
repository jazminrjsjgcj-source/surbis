<?php

declare(strict_types=1);

namespace App\Application\Kiosk\Exceptions;

use RuntimeException;

/**
 * La estacion no se puede preparar. RF-COL-007 y 008.
 *
 * Lleva una CLAVE y nada mas. RNF-COL-004 prohibe exponer IDs internos,
 * tokens o rutas en esa pantalla: quien la ve es un colaborador de ventanilla
 * al que hay que decirle a quien avisar, no que depurar.
 *
 * Y RF-COL-008: no puede corregir ni elegir la configuracion desde ahi. Por
 * eso la clave describe QUE falta, sin ofrecer arreglarlo.
 */
final class StationNotReady extends RuntimeException
{
    private function __construct(public readonly string $key)
    {
        parent::__construct("kiosk.{$key}");
    }

    /** La clave no vale, o se revoco. */
    public static function unknownDevice(): self
    {
        return new self('unknown_device');
    }

    /** El dispositivo existe pero esta archivado. */
    public static function deviceInactive(): self
    {
        return new self('device_inactive');
    }

    /** Nadie ha aplicado una encuesta a este dispositivo ni a su sucursal. */
    public static function noDeployment(): self
    {
        return new self('no_deployment');
    }

    /** Hay aplicacion, pero no esta recibiendo respuestas ahora. */
    public static function deploymentNotApplying(): self
    {
        return new self('deployment_not_applying');
    }
}
