<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Enums;

/**
 * Que se le pide a quien responde, y quien puede verlo despues.
 *
 * RF-ENC-008 nombra los cuatro. Las consecuencias de cada uno no son de
 * pantalla: deciden si existe una fila en survey_response_identities y quien
 * puede leerla (D-008).
 */
enum IdentityMode: string
{
    /** Nunca se guarda identidad. RNF-DAT-008 y RNF-ENC-008. */
    case Anonymous = 'anonymous';

    /** Se guarda cifrada y el administrador ordinario NO la ve. P-005. */
    case Confidential = 'confidential';

    /** Se pide y la persona decide. Visible para el administrador. */
    case Optional = 'optional';

    /** Se exige. Visible para el administrador. */
    case Identified = 'identified';

    public function capturesIdentity(): bool
    {
        return $this !== self::Anonymous;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
