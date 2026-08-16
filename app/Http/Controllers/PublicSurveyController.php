<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Deployments\PublicToken;
use App\Domain\Deployments\Models\Deployment;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * La puerta publica de una encuesta. RF-AO-DEP-008.
 *
 * De momento solo comprueba el token y avisa de que el renderizador llega en
 * la Fase 7. Existe ahora para que el QR pueda escanearse y verificarse: un
 * codigo que lleva a un 404 no se puede dar por bueno.
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
         * columna no encontraria nada nunca. Y buscar por hash permite un
         * indice, que con un bcrypt no seria posible.
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
         * se sabria cuales existen. RNF-AO-DEP-002 pide que no revelen nada.
         */
        $aplicando = $deployment !== null && $deployment->isApplying();

        return Inertia::render('Public/Survey', [
            'available' => $aplicando,
            'surveyName' => $aplicando ? $deployment->version->survey->name : null,
        ]);
    }
}
