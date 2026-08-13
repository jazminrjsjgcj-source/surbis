<?php

declare(strict_types=1);

return [
    /*
     * Antes de verificar la contrasena, el mensaje es generico: no debe
     * confirmar si la cuenta existe. RNF-AUT-003.
     */
    'failed' => 'Las credenciales no son correctas.',

    'throttle' => 'Demasiados intentos. Vuelve a probar en :seconds segundos.',

    /*
     * Despues de verificarla, quien esta al otro lado ya demostro tener la
     * cuenta. Decirle que esta suspendida no revela nada nuevo y le ahorra
     * media hora de no entender por que no entra.
     */
    'user_suspended' => 'Tu cuenta esta suspendida. Contacta al administrador de tu organizacion.',
    'organization_suspended' => 'Tu organizacion esta suspendida. Contacta al administrador de la plataforma.',
    'membership_suspended' => 'Tu acceso a esta organizacion esta suspendido. Contacta al administrador de tu organizacion.',
    'without_membership' => 'Tu cuenta no esta asociada a ninguna organizacion. Contacta al administrador.',

    'second_factor' => [
        'mail_subject' => 'Tu codigo de verificacion',
        'mail_greeting' => 'Hola',
        'mail_intro' => 'Usa este codigo para terminar de iniciar sesion:',
        'mail_validity' => 'El codigo vence en :minutes minutos y solo sirve una vez.',
        'mail_warning' => 'Si no intentaste iniciar sesion, cambia tu contrasena.',

        /*
         * Un unico mensaje para codigo incorrecto, vencido, ya usado o con
         * demasiados intentos. Distinguirlos le diria a quien esta probando
         * codigos cuanto le queda por probar.
         */
        'invalid' => 'El codigo no es valido. Pide uno nuevo si ya venciste el anterior.',

        'resent' => 'Te enviamos un codigo nuevo.',
        'resend_too_soon' => 'Espera un momento antes de pedir otro codigo.',
    ],

    'context_lost' => 'Tu sesion ya no tiene una organizacion valida. Inicia sesion de nuevo.',
    'organization_not_available' => 'Esa organizacion no esta disponible para tu cuenta.',
];
