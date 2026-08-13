<?php

declare(strict_types=1);

namespace App\Domain\Shared\Localization;

/**
 * Direccion de escritura del idioma activo.
 *
 * El sistema debe poder funcionar en arabe (ANEXO 1 seccion 50). Que el
 * atributo dir del html salga de aqui, y no este escrito a mano como "ltr",
 * es lo que hace que el dia que se anada un idioma RTL no haya que tocar cada
 * plantilla.
 */
final class TextDirection
{
    /** @var list<string> */
    private const RTL_LANGUAGES = ['ar', 'he', 'fa', 'ur', 'yi', 'dv', 'ps'];

    public static function forLocale(string $locale): string
    {
        $language = str_contains($locale, '_')
            ? strstr($locale, '_', true)
            : strstr($locale.'-', '-', true);

        return in_array(strtolower($language), self::RTL_LANGUAGES, true) ? 'rtl' : 'ltr';
    }

    public static function current(): string
    {
        return self::forLocale(app()->getLocale());
    }
}
