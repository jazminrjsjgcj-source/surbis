<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Si el segundo factor se puede usar. P-013.
 *
 * El codigo llega por CORREO. Con MAIL_MAILER=log el correo se escribe en un
 * archivo del servidor, asi que activar el MFA dejaria a esa persona fuera de
 * su propia cuenta: la pantalla le pediria un codigo que nunca va a recibir.
 *
 * Se deduce de la configuracion en lugar de un interruptor aparte. Un
 * interruptor habria que acordarse de encenderlo el dia que se configure el
 * correo, y nadie se acuerda: el sistema se quedaria sin MFA para siempre sin
 * que nadie lo notara.
 */
final class SecondFactorAvailability
{
    /**
     * Los transportes que NO entregan correo de verdad.
     *
     * `log` lo escribe en storage/logs; `array` lo guarda en memoria para las
     * pruebas. Ninguno llega a un buzon.
     */
    /*
     * Solo `log`.
     *
     * `array` tambien deja el correo sin enviar, pero es el transporte de las
     * PRUEBAS: incluirlo aqui impediria probar el segundo factor, que es
     * justo lo que no se quiere —el mecanismo tiene que seguir teniendo sus
     * dieciocho pruebas aunque en produccion este apagado—.
     */
    private const FAKE_MAILERS = ['log'];

    public function isAvailable(): bool
    {
        $mailer = (string) config('mail.default');

        return ! in_array($mailer, self::FAKE_MAILERS, true);
    }

    /**
     * Por que no se puede, para poder decirlo.
     *
     * "No disponible" sin motivo hace que alguien abra una incidencia. Con el
     * motivo, quien administra sabe exactamente que falta.
     */
    public function unavailableReason(): ?string
    {
        return $this->isAvailable() ? null : 'mail_not_configured';
    }
}
