<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Deployments\PublicToken;
use App\Application\Deployments\RenderQrCode;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * QR de una aplicacion. RF-AO-DEP-008, 009 y 010.
 */
final class DeploymentQrController extends Controller
{
    public function show(Request $request, Deployment $deployment): InertiaResponse
    {
        $this->authorize('view', $deployment);

        /*
         * El token viene de un flash, no de la base.
         *
         * En la base solo esta su hash, asi que el enlace COMPLETO solo puede
         * mostrarse justo despues de crearlo o regenerarlo. Quien entre aqui
         * mas tarde ve el QR —que ya se genero— pero no el texto del enlace.
         *
         * Es incomodo a proposito: si el token se pudiera recuperar en
         * cualquier momento, quien accediera a la base tendria todos los
         * enlaces publicados.
         */
        $token = $request->session()->get('public_token');

        return Inertia::render('Admin/Deployments/Qr', [
            'deployment' => [
                'ulid' => $deployment->ulid,
                'survey_name' => $deployment->version->survey->name,
                'channel' => $deployment->channel->value,
                'is_applying' => $deployment->isApplying(),
            ],

            'token' => $token,
            'url' => $token === null ? null : route('public.survey', $token),

            'qrUrl' => route('admin.deployments.qr.svg', $deployment),
            'regenerateUrl' => route('admin.deployments.qr.regenerate', $deployment),
            'backUrl' => route('admin.deployments.index'),
        ]);
    }

    /**
     * El SVG para descargar e imprimir. RF-AO-DEP-009.
     *
     * Se sirve como descarga y no incrustado en la pagina porque quien monta
     * un cartel necesita el archivo, no una imagen en pantalla.
     */
    public function svg(Request $request, Deployment $deployment, RenderQrCode $qr): Response
    {
        $this->authorize('view', $deployment);

        $token = $request->session()->get('public_token');

        /*
         * Sin el token en claro no se puede dibujar el QR: codifica la URL
         * completa, y esa URL lleva el token.
         *
         * Se responde 410 y no 404: el recurso existe, pero ya no se puede
         * obtener. Para volver a tenerlo hay que regenerarlo.
         */
        if ($token === null) {
            return response(__('interface.qr.token_gone'), 410);
        }

        return response($qr->svg(route('public.survey', $token)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-'.$deployment->ulid.'.svg"',
        ]);
    }

    /**
     * Regenerar. RF-AO-DEP-010 · RNF-AO-DEP-003.
     *
     * Invalida el enlace y el QR anteriores: los carteles ya impresos dejan
     * de funcionar. Por eso la pantalla pide confirmacion antes.
     */
    public function regenerate(
        Request $request,
        Deployment $deployment,
        PublicToken $tokens,
        RecordAuditLog $audit,
    ): RedirectResponse {
        $this->authorize('regenerateToken', $deployment);

        /** @var User $user */
        $user = $request->user();

        $nuevo = $tokens->generate();

        $deployment->forceFill(['public_token_hash' => $tokens->hash($nuevo)])->save();

        $audit->record('deployment.token_regenerated', $deployment, [], actor: $user);

        return back()->with([
            'status' => __('interface.qr.regenerated'),
            'public_token' => $nuevo,
        ]);
    }
}
