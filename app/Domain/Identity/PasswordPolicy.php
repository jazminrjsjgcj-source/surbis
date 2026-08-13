<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use Illuminate\Validation\Rules\Password;

/**
 * La politica de contrasenas del sistema, en un solo sitio.
 *
 * RF-AUT-012 pide dos cosas: aplicarla y MOSTRAR sus reglas. Si el texto que
 * ve el usuario y la regla que valida el servidor viven en archivos
 * distintos, tarde o temprano dicen cosas distintas y nadie se entera hasta
 * que alguien no consigue registrar una contrasena que la pantalla decia
 * aceptar.
 *
 * Aqui el minimo se declara una vez y de el salen las dos.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public static function rules(): Password
    {
        return Password::min(self::MIN_LENGTH)
            ->letters()
            ->numbers();
    }

    /**
     * Texto que describe la politica al usuario, construido desde la misma
     * constante que la valida.
     */
    public static function describe(): string
    {
        return __('interface.password.policy', ['min' => self::MIN_LENGTH]);
    }
}
