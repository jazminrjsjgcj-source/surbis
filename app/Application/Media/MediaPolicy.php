<?php

declare(strict_types=1);

namespace App\Application\Media;

/**
 * Que se admite al subir. RNF-AO-MED-001 y 002.
 *
 * Vive aqui y no repartido entre la validacion y el caso de uso porque son
 * las MISMAS reglas: el formulario las usa para avisar antes, y el caso de
 * uso para rechazar. Duplicarlas garantiza que un dia digan cosas distintas.
 */
final class MediaPolicy
{
    /** 2 MB. Una foto de movil sin comprimir pasa de 4; se pide que la reduzcan. */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Los tipos que se aceptan.
     *
     * SVG NO ESTA, y es deliberado: un SVG puede contener JavaScript, y una
     * imagen que se muestra en el quiosco a ciudadanos no puede ejecutar
     * codigo. Es un agujero clasico y silencioso —el archivo parece una
     * imagen, se ve como una imagen, y ejecuta lo que lleve dentro—.
     *
     * Los SVG del juego de SISTEMA si son SVG, y no es una contradiccion: los
     * escribimos nosotros, van en el repositorio y pasan por revision. Lo que
     * no se controla es lo que sube un usuario.
     *
     * @var list<string>
     */
    public const MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var list<string> */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Las reglas de validacion, para que el Form Request no las repita.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'file',
            'image',
            'mimetypes:'.implode(',', self::MIME_TYPES),
            'mimes:'.implode(',', self::EXTENSIONS),
            'max:'.(int) (self::MAX_BYTES / 1024),
        ];
    }
}
