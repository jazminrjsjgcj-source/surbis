<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Enums;

/**
 * RF-AO-BLD-005: solo texto, solo imagen, o imagen con texto.
 *
 * "Solo imagen" no significa sin nombre accesible. La etiqueta se guarda
 * siempre y se usa como texto alternativo: una carita sin nombre es una
 * pregunta que no se puede contestar con lector de pantalla.
 */
enum OptionDisplay: string
{
    case Text = 'text';
    case Image = 'image';
    case ImageAndText = 'image_and_text';

    public function needsImage(): bool
    {
        return $this !== self::Text;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
