<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\Membership;
use App\Domain\Media\Models\MediaItem;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve las imagenes que sube una organizacion. RNF-GEN-005.
 *
 * NO estan en un disco publico a proposito: son fotos que alguien subio a su
 * encuesta, y publicarlas dejaria que cualquiera las viera adivinando la
 * ruta. Aqui se comprueba antes de servirlas.
 *
 * Las del SISTEMA no pasan por aqui: van directas desde el disco publico
 * porque son del producto y no hay nada que proteger.
 */
final class MediaController extends Controller
{
    public function __invoke(Request $request, string $ulid): StreamedResponse
    {
        $item = MediaItem::query()->where('ulid', $ulid)->firstOrFail();

        /*
         * Un recurso de sistema no deberia llegar aqui —su url() apunta al
         * disco publico— pero si alguien pide su ULID por esta via, se sirve
         * igual. Es publico de todos modos.
         */
        if (! $item->isSystem()) {
            $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

            abort_unless(
                $membership instanceof Membership
                    && $membership->organization_id === $item->organization_id,
                403,
            );
        }

        $disk = Storage::disk($item->disk);

        abort_unless($disk->exists($item->path), 404);

        /*
         * Se transmite en lugar de leerse entera en memoria: una imagen de
         * 2 MB por cada peticion concurrente sumaria rapido.
         */
        return $disk->response($item->path, null, [
            'Content-Type' => $item->mime_type,

            // Cacheable pero PRIVADA: el navegador la guarda, los proxies
            // intermedios no. Son datos de una organizacion.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
