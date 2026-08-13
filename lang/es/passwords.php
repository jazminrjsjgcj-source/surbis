<?php

declare(strict_types=1);

return [
    /*
     * Mensajes del broker de contrasenas de Laravel.
     *
     * `sent_if_registered` no es una clave del framework: es nuestra, y
     * sustituye a `sent`. La diferencia importa. El mensaje de Laravel afirma
     * que el correo se envio, lo que confirma que la cuenta existe; este dice
     * lo mismo tanto si existe como si no. RF-AUT-009 y RNF-AUT-007.
     */
    'sent_if_registered' => 'Si ese correo corresponde a una cuenta, te enviamos una liga para restablecer la contrasena.',

    'reset' => 'Tu contrasena se actualizo. Inicia sesion con la nueva.',

    /*
     * Un unico mensaje para token invalido, caducado, ya usado o que no
     * corresponde a ese correo. Distinguirlos diria si la cuenta existe.
     */
    'token' => 'Esa liga ya no es valida. Solicita una nueva.',

    'throttled' => 'Espera un momento antes de volver a intentarlo.',
    'user' => 'Si ese correo corresponde a una cuenta, te enviamos una liga para restablecer la contrasena.',
];
