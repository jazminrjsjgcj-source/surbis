<?php

declare(strict_types=1);

namespace App\Application\Identity;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia la liga de restablecimiento.
 *
 * No devuelve nada, y es deliberado: RF-AUT-009 y RNF-AUT-007 exigen que la
 * respuesta sea identica exista o no la cuenta. Un metodo que devolviera
 * "enviado" o "no encontrado" invitaria a que alguien, en algun controlador,
 * lo mostrara y convirtiera la pantalla en un comprobador de correos
 * registrados.
 *
 * El fallo del proveedor de correo se registra y no llega al usuario.
 * RNF-AUT-008.
 */
final class SendPasswordResetLink
{
    public function __construct(private readonly PasswordBroker $broker) {}

    public function execute(string $email): void
    {
        try {
            $status = $this->broker->sendResetLink(['email' => $email]);

            if ($status !== PasswordBroker::RESET_LINK_SENT) {
                // Correo desconocido o peticion demasiado seguida. Se registra
                // para poder diagnosticar, sin decir nada fuera.
                Log::info('password_reset.link_not_sent', ['status' => $status]);
            }
        } catch (Throwable $failure) {
            Log::error('password_reset.delivery_failed', [
                'exception' => $failure::class,
                'message' => $failure->getMessage(),
            ]);
        }
    }
}
