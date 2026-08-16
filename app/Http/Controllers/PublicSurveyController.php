<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Deployments\PublicToken;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Surveys\Enums\RenderLayout;
use App\Domain\Surveys\Rendering\RenderableSurvey;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * La puerta publica de una encuesta. RF-AO-DEP-008 · RF-ENC-006.
 *
 * SIN sesion y SIN organizacion activa: quien escanea un cartel no ha
 * iniciado sesion en nada.
 */
final class PublicSurveyController extends Controller
{
    public function __invoke(string $token, PublicToken $tokens): InertiaResponse
    {
        /*
         * Se busca por HASH, no por token.
         *
         * En la base solo esta el hash: comparar el token en claro contra la
         * columna no encontraria nada nunca.
         */
        $deployment = Deployment::query()
            ->where('public_token_hash', $tokens->hash($token))
            ->with('version.survey')
            ->first();

        /*
         * Un token que no existe y uno que no esta aplicando dan la MISMA
         * respuesta.
         *
         * Distinguirlos convertiria la URL en un comprobador: probando tokens
         * se sabria cuales existen. RNF-AO-DEP-002.
         */
        $aplicando = $deployment !== null && $deployment->isApplying();

        if (! $aplicando) {
            return Inertia::render('Public/Survey', [
                'available' => false,
                'survey' => null,
            ]);
        }

        /*
         * El MISMO RenderableSurvey que usan el quiosco y la vista previa.
         * RNF-COL-012 y RNF-AO-PUB-002.
         *
         * El modo sale del canal: un enlace publico se contesta sentado, asi
         * que se ven todas las preguntas a la vez.
         */
        $layout = RenderLayout::forChannel($deployment->channel);

        return Inertia::render('Public/Survey', [
            'available' => true,
            'survey' => (new RenderableSurvey($deployment->version, $layout))->toArray(),
        ]);
    }
}
