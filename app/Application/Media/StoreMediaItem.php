<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Subir una imagen a la biblioteca. RF-AO-MED-001.
 */
final class StoreMediaItem
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(
        Organization $organization,
        User $uploader,
        UploadedFile $file,
        ?string $altText = null,
    ): MediaItem {
        /*
         * El nombre del archivo se DESCARTA para la ruta.
         *
         * Lo que llega del navegador puede contener rutas, caracteres de
         * control o nombres que en Linux significan otra cosa. Se conserva
         * como etiqueta para que la persona reconozca su imagen, pero el
         * archivo se guarda con un nombre que generamos nosotros.
         *
         * ANEXO 1 seccion 55 y 25.
         */
        $hash = hash_file('sha256', $file->getRealPath());
        $extension = strtolower($file->extension() ?: 'bin');
        $path = "media/{$organization->id}/".substr($hash, 0, 2)."/{$hash}.{$extension}";

        /*
         * Disco PRIVADO para lo que sube una organizacion.
         *
         * Son fotos suyas: en un disco publico cualquiera podria verlas
         * adivinando la ruta, y el hash del nombre no es una proteccion —es
         * un identificador, no un secreto—. Se sirven por MediaController.
         */
        $disk = 'local';

        return DB::transaction(function () use (
            $organization, $uploader, $file, $altText, $path, $disk, $hash
        ): MediaItem {
            /*
             * Si ya existe, se devuelve la que hay.
             *
             * Subir dos veces la misma imagen produciria dos filas apuntando
             * al mismo archivo, y borrar una dejaria a la otra sin fichero.
             * El indice unico de la base tambien lo impide; esto lo resuelve
             * sin que el usuario vea un error por hacer algo razonable.
             */
            $existente = MediaItem::query()
                ->where('organization_id', $organization->id)
                ->where('path', $path)
                ->first();

            if ($existente !== null) {
                return $existente;
            }

            $file->storeAs(dirname($path), basename($path), ['disk' => $disk]);

            $dimensiones = @getimagesize($file->getRealPath());

            $item = MediaItem::query()->create([
                'organization_id' => $organization->id,
                'uploaded_by' => $uploader->id,
                'name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'width' => $dimensiones[0] ?? null,
                'height' => $dimensiones[1] ?? null,
                'alt_text' => $altText,
            ]);

            $this->audit->record('media.uploaded', $item, [
                'name' => $item->name,
                'size_bytes' => $item->size_bytes,
                'hash' => substr($hash, 0, 12),
            ], actor: $uploader);

            return $item;
        });
    }
}
