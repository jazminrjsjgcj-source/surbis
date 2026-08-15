<?php

declare(strict_types=1);

namespace App\Domain\Deployments\Enums;

/**
 * Por donde se aplica una encuesta. RF-AO-DEP-002.
 *
 * El canal decide QUE alcance es obligatorio: un quiosco necesita un
 * dispositivo concreto —esa tableta, en esa ventanilla— mientras que un
 * enlace publico se comparte sin ubicacion.
 */
enum DeploymentChannel: string
{
    /** Tableta en ventanilla. Exige dispositivo. */
    case Kiosk = 'kiosk';

    /** Codigo impreso. La ubicacion la da donde se pegue el cartel. */
    case Qr = 'qr';

    /** Liga que se comparte por correo o mensaje. */
    case PublicLink = 'public_link';

    /** Incrustado en otra web. */
    case Widget = 'widget';

    /**
     * Si este canal necesita un dispositivo concreto.
     *
     * Decision del area usuaria: un deployment de quiosco DEBE exigir
     * dispositivo. Sin el, una respuesta no podria decir de que tableta vino,
     * y el historico perderia la ubicacion real.
     */
    public function requiresDevice(): bool
    {
        return $this === self::Kiosk;
    }

    /**
     * Si este canal se alcanza con un token publico.
     *
     * El quiosco no: su dispositivo se identifica con su propia clave de
     * estacion, que es otro mecanismo y tiene otras reglas de revocacion.
     */
    public function usesPublicToken(): bool
    {
        return in_array($this, [self::Qr, self::PublicLink, self::Widget], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
