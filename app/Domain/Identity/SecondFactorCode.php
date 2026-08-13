<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use Illuminate\Support\Str;

/**
 * El codigo de un solo uso que se envia por correo.
 *
 * Seis digitos y diez minutos. El codigo en claro existe una sola vez —para
 * meterlo en el correo— y de el solo se guarda el hash: RNF-AUT-012 prohibe
 * conservar el codigo, y un codigo de acceso guardado en claro es una
 * contrasena guardada en claro con otro nombre.
 */
final class SecondFactorCode
{
    public const LENGTH = 6;

    public const MINUTES_VALID = 10;

    private function __construct(public readonly string $plain) {}

    public static function generate(): self
    {
        // random_int y no rand(): el segundo es predecible y esto es una
        // credencial.
        $maximo = (10 ** self::LENGTH) - 1;

        return new self(str_pad(
            (string) random_int(0, $maximo),
            self::LENGTH,
            '0',
            STR_PAD_LEFT,
        ));
    }

    /**
     * Compara en tiempo constante. Una comparacion normal tarda mas cuanto
     * mas coinciden los primeros caracteres, y eso se puede medir.
     */
    public static function matches(string $entered, string $hashed): bool
    {
        return hash_equals($hashed, self::hashOf($entered));
    }

    public static function hashOf(string $code): string
    {
        return hash('sha256', $code);
    }

    public function hash(): string
    {
        return self::hashOf($this->plain);
    }

    /**
     * Formato legible en el correo: 123 456. Se lee y se teclea mejor.
     */
    public function forHumans(): string
    {
        return trim(chunk_split($this->plain, 3, ' '));
    }

    public static function normalize(string $entered): string
    {
        // Quien copia del correo se trae el espacio. Que eso invalide el
        // codigo seria un rechazo que el sistema puede evitar.
        return Str::of($entered)->replaceMatches('/\D/', '')->value();
    }
}
