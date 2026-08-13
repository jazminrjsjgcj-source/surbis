<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Como se entrega el segundo factor.
 *
 * Hoy solo hay correo, por decision del area usuaria (13 ago 2026). El enum
 * existe igualmente porque la eleccion entre correo, TOTP y SMS se tomo
 * sabiendo que podia cambiar: tener el canal nombrado en la base desde el
 * principio evita una migracion sobre datos el dia que se anada otro.
 *
 * Lo unico que cambia entre canales es como se genera y se comprueba el
 * codigo. El resto del flujo —sesion parcial, limites, codigos de
 * recuperacion, auditoria— es identico.
 */
enum SecondFactorChannel: string
{
    case Email = 'email';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
