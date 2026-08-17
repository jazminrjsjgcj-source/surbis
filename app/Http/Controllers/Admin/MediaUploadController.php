<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Media\StoreMediaItem;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Surveys\Models\Survey;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\MediaUploadRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Subir una imagen a la biblioteca. RF-AO-MED-001.
 *
 * La pieza que faltaba: StoreMediaItem y MediaUploadRequest estaban escritos
 * desde la Fase 5 pero nadie los llamaba, asi que no habia forma de subir
 * nada —solo elegir entre las caritas del sistema—.
 */
final class MediaUploadController extends Controller
{
    public function __invoke(
        MediaUploadRequest $request,
        Survey $survey,
        StoreMediaItem $store,
    ): RedirectResponse {
        /*
         * El permiso es el de EDITAR LA ENCUESTA, no uno propio.
         *
         * Subir una imagen solo tiene sentido para ponerla en una pregunta.
         * Un permiso aparte permitiria llenar la biblioteca de la
         * organizacion a quien no puede tocar ninguna encuesta.
         */
        $this->authorize('update', $survey);

        $membership = $this->activeMembership($request);

        /** @var User $user */
        $user = $request->user();

        $item = $store->execute(
            $membership->organization,
            $user,
            $request->file('file'),
            $request->string('alt_text')->toString() ?: null,
        );

        /*
         * Se vuelve al constructor con el ULID de lo subido.
         *
         * Sin el, quien acaba de subir una imagen tendria que buscarla en la
         * biblioteca para usarla, que es justo lo que venia a evitar.
         */
        return back()->with([
            'status' => __('interface.media.uploaded'),
            'uploaded_media' => $item->ulid,
        ]);
    }

    private function activeMembership(MediaUploadRequest $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
