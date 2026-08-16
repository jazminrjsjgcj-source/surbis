<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Deployments\PublicToken;
use App\Application\Responses\Exceptions\ResponseRejected;
use App\Application\Responses\SubmitResponse;
use App\Domain\Deployments\Models\Deployment;
use App\Http\Requests\SubmitResponseRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Recibir una encuesta contestada. RF-COL-020.
 *
 * SIN sesion: quien escanea un cartel no ha iniciado sesion en nada. Lo que
 * autoriza es el token del enlace, no un usuario.
 */
final class PublicResponseController extends Controller
{
    public function __invoke(
        SubmitResponseRequest $request,
        string $token,
        PublicToken $tokens,
        SubmitResponse $submit,
    ): RedirectResponse {
        $deployment = Deployment::query()
            ->where('public_token_hash', $tokens->hash($token))
            ->with(['version.survey', 'organization', 'branch', 'area', 'device.branch'])
            ->first();

        /*
         * Un token que no existe y uno que ya no aplica dan la MISMA
         * respuesta.
         *
         * Distinguirlos convertiria la URL en un comprobador de que tokens
         * existen. RNF-AO-DEP-002.
         */
        if ($deployment === null || ! $deployment->isApplying()) {
            return back()->withErrors(['response' => __('interface.public.unavailable_body')]);
        }

        try {
            $submit->execute(
                $deployment,
                $this->answers($request),
                $request->string('idempotency_key')->toString(),
                $request->string('comment')->toString() ?: null,
                $this->identity($request),
            );
        } catch (ResponseRejected $rechazo) {
            /*
             * La clave se traduce AQUI: el dominio no habla espanol, para que
             * la API futura pueda interpretarla.
             */
            return back()->withErrors([
                'response' => __("interface.public.rejected.{$rechazo->key}", $rechazo->replacements),
            ])->withInput();
        }

        /*
         * Se responde con una redireccion y no con JSON.
         *
         * Con Inertia, back() vuelve a la misma pantalla con el flash puesto,
         * y el componente decide que enseñar. Devolver JSON obligaria a que
         * el cliente supiera montar la pantalla de gracias por su cuenta.
         */
        return back()->with('response_submitted', true);
    }

    /**
     * Las respuestas, con la forma que espera el dominio.
     *
     * @return array<string, string|list<string>>
     */
    private function answers(SubmitResponseRequest $request): array
    {
        /** @var array<string, mixed> $recibidas */
        $recibidas = $request->array('answers');
        $limpias = [];

        foreach ($recibidas as $ulid => $valor) {
            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }

            // Una lista se queda como lista; lo demas se fuerza a cadena.
            $limpias[(string) $ulid] = is_array($valor)
                ? array_values(array_map('strval', $valor))
                : (string) $valor;
        }

        return $limpias;
    }

    /**
     * @return array{name?: ?string, email?: ?string, phone?: ?string, consent?: bool}
     */
    private function identity(SubmitResponseRequest $request): array
    {
        /** @var array<string, mixed> $identity */
        $identity = $request->array('identity');

        return [
            'name' => $this->text($identity['name'] ?? null),
            'email' => $this->text($identity['email'] ?? null),
            'phone' => $this->text($identity['phone'] ?? null),
            'consent' => ($identity['consent'] ?? false) === true,
        ];
    }

    private function text(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return trim($valor);
    }
}
