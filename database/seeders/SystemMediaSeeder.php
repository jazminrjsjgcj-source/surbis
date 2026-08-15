<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Media\Models\MediaItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * El juego basico de caritas. RF-COL-015.
 *
 * Son recursos del PRODUCTO, no de ninguna organizacion: organization_id
 * nulo. Las caritas de una escala de satisfaccion son las mismas en cualquier
 * ayuntamiento, y obligar a cada uno a subirlas seria trabajo repetido.
 *
 * A diferencia de DevelopmentSeeder, este SI se ejecuta en produccion: sin
 * el, ninguna organizacion podria montar una pregunta de caritas.
 */
final class SystemMediaSeeder extends Seeder
{
    /**
     * De peor a mejor, que es el orden en que se muestran.
     *
     * @var array<string, string>
     */
    private const SMILEYS = [
        'muy-mal' => 'Cara muy triste',
        'mal' => 'Cara triste',
        'normal' => 'Cara neutra',
        'bien' => 'Cara contenta',
        'muy-bien' => 'Cara muy contenta',
    ];

    public function run(): void
    {
        $origen = resource_path('media/system/smiley');
        $disk = config('filesystems.default');

        foreach (self::SMILEYS as $nombre => $alt) {
            $archivo = "{$origen}/{$nombre}.svg";

            if (! File::exists($archivo)) {
                $this->command?->warn("Falta {$archivo}");

                continue;
            }

            $path = "media/system/smiley/{$nombre}.svg";

            /*
             * Se copia al disco en cada ejecucion.
             *
             * Si el diseno definitivo sustituye un SVG, volver a sembrar lo
             * actualiza sin tener que borrar la fila y perder las referencias
             * que las encuestas ya tengan.
             */
            Storage::disk($disk)->put($path, File::get($archivo));

            MediaItem::query()->updateOrCreate(
                ['organization_id' => null, 'path' => $path],
                [
                    'name' => $alt,
                    'disk' => $disk,
                    'mime_type' => 'image/svg+xml',
                    'size_bytes' => File::size($archivo),
                    'width' => 64,
                    'height' => 64,

                    // El texto alternativo viene puesto: una carita sin nombre
                    // no se puede elegir con lector de pantalla, y estas son
                    // las que veran los ciudadanos en el quiosco.
                    'alt_text' => $alt,
                ],
            );
        }

        $this->command?->info('Caritas del sistema: '.count(self::SMILEYS).' disponibles para todas las organizaciones.');
    }
}
