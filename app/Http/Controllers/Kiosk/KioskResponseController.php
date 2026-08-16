<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Application\Kiosk\OpenKioskSession;
use App\Application\Kiosk\ResolveStation;
use App\Application\Responses\Exceptions\ResponseRejected;
use App\Application\Responses\SubmitResponse;
use App\Domain\Kiosk\Models\KioskSession;
use App\Domain\Organizations\Models\Device;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveKioskStation;
use App\Http\Requests\SubmitResponseRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Recibir una respuesta del quiosco. RF-COL-020.
 *
 * Igual que el enlace publico, con una diferencia: aqui hay una SESION, y de
 * ella sale a quien se evalua. El navegador manda su ULID, pero se comprueba
 * que sea la sesion abierta de ESTA tableta (RNF-COL-013).
 */
final class KioskResponseController extends Controller
{
    public function __invoke(
        SubmitResponseRequest $request,
        ResolveStation $stations,
        SubmitResponse $submit,
        OpenKioskSession $sessions,
    ): RedirectResponse {
        $device = $request->attributes->get(ResolveKioskStation::REQUEST_ATTRIBUTE);

        if (! $device instanceof Device) {
            return redirect()->route('kiosk.link');
        }

        try {
            $deployment = $stations->deployment($device);
        } catch (StationNotReady) {
            return back()->withErrors(['response' => __('interface.kiosk.no_deployment')]);
        }

        /*
         * La sesion se busca por DISPOSITIVO, no por el ULID recibido.
         *
         * RNF-COL-013: el navegador no decide a quien se evalua. Si se
         * confiara en lo que manda, bastaria con cambiar el ULID para
         * atribuir respuestas al turno de otra persona.
         *
         * El ULID que llega solo sirve para comprobar que la pantalla no se
         * quedo con una sesion vieja tras un cambio de turno.
         */
        $session = KioskSession::query()
            ->where('device_id', $device->id)
            ->whereNull('closed_at')
            ->first();

        if ($session === null) {
            return redirect()->route('kiosk.prepare');
        }

        if ($request->string('session')->toString() !== $session->ulid) {
            /*
             * El turno cambio mientras alguien contestaba.
             *
             * Se descarta: atribuir esa respuesta al turno nuevo seria
             * falsear a quien evaluo, y al viejo tambien —ya no estaba—.
             */
            return redirect()->route('kiosk.welcome');
        }

        try {
            $submit->execute(
                $deployment,
                $this->answers($request),
                $request->string('idempotency_key')->toString(),
                $request->string('comment')->toString() ?: null,
                [],
                $session,
            );
        } catch (ResponseRejected $rechazo) {
            return back()->withErrors([
                'response' => __("interface.public.rejected.{$rechazo->key}", $rechazo->replacements),
            ]);
        }

        // RF-COL-013: la sesion sigue viva y se marca la actividad.
        $sessions->touch($session);

        return back()->with('kiosk_submitted', true);
    }

    /**
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

            $limpias[(string) $ulid] = is_array($valor)
                ? array_values(array_map('strval', $valor))
                : (string) $valor;
        }

        return $limpias;
    }
}
