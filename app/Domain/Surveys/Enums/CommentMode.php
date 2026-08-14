<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Enums;

/**
 * RF-COL-021: la pantalla de comentario respeta el caracter configurado.
 */
enum CommentMode: string
{
    case Disabled = 'disabled';
    case Optional = 'optional';
    case Required = 'required';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
