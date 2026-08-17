<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Analytics\Models\ResponseMetric;
use App\Domain\Responses\Models\Response;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene los indicadores al dia. Decision del area usuaria, 18 ago 2026.
 *
 * Cada respuesta nueva, invalidada o rehabilitada actualiza el resumen. Las
 * RESPUESTAS siguen siendo la fuente oficial: esto se puede reconstruir
 * entero, y si algun dia los numeros no coinciden, gana `responses`.
 */
final class RecordResponseMetric
{
    /** Una respuesta nueva. */
    public function record(Response $response): void
    {
        $this->apply($response, valid: +1, invalid: 0);
    }

    /**
     * Una respuesta invalidada: sale de los indicadores y entra en el
     * recuento de excluidas.
     *
     * Las dos cosas a la vez, porque el panel dice cuantas se descartaron —un
     * numero que baja sin explicacion genera desconfianza—.
     */
    public function invalidate(Response $response): void
    {
        $this->apply($response, valid: -1, invalid: +1);
    }

    /** Rehabilitada: vuelve a contar. */
    public function revalidate(Response $response): void
    {
        $this->apply($response, valid: +1, invalid: -1);
    }

    /**
     * Una respuesta que YA estaba invalidada, al reconstruir.
     *
     * Existe aparte porque no es lo mismo que invalidar: no hay que restarla
     * de las validas —nunca se conto— solo sumarla a las excluidas. Hacerlo
     * con invalidate() sobre una tabla recien borrada dejaria un -1 que
     * max(0, ...) esconderia sin arreglar.
     */
    public function recordInvalidated(Response $response): void
    {
        $this->apply($response, valid: 0, invalid: +1);
    }

    private function apply(Response $response, int $valid, int $invalid): void
    {
        $clave = $this->grainOf($response);

        DB::transaction(function () use ($response, $clave, $valid, $invalid): void {
            /*
             * firstOrCreate y luego increment, dentro de la transaccion.
             *
             * Dos respuestas simultaneas del mismo quiosco crearian dos filas
             * sin esto; el indice unico de la base lo impide, y el bloqueo
             * evita llegar a ese error.
             */
            $metric = ResponseMetric::query()->lockForUpdate()->firstOrCreate($clave);

            $puntua = $response->score !== null;

            $metric->forceFill([
                'responses' => max(0, $metric->responses + $valid),
                'invalidated' => max(0, $metric->invalidated + $invalid),

                /*
                 * Se suma o resta la puntuacion SOLO si la respuesta puntua.
                 *
                 * Una encuesta de texto libre no tiene puntuacion, y sumarle
                 * cero bajaria el promedio de las que si puntuan.
                 */
                'score_sum' => $puntua
                    ? max(0, $metric->score_sum + ($valid * (int) $response->score))
                    : $metric->score_sum,

                'max_score_sum' => $puntua
                    ? max(0, $metric->max_score_sum + ($valid * (int) $response->max_score))
                    : $metric->max_score_sum,

                'scored_responses' => $puntua
                    ? max(0, $metric->scored_responses + $valid)
                    : $metric->scored_responses,
            ])->save();
        });
    }

    /**
     * La combinacion a la que pertenece esta respuesta.
     *
     * El dia se calcula en la zona de la ORGANIZACION: en UTC, una respuesta
     * de las 23:30 en Mexico contaria como del dia siguiente y los informes
     * no cuadrarian con lo que vio quien estuvo alli.
     *
     * @return array<string, mixed>
     */
    private function grainOf(Response $response): array
    {
        $zona = $response->organization->timezone;

        return [
            'organization_id' => $response->organization_id,
            'day' => $response->submitted_at->setTimezone($zona)->toDateString(),
            'deployment_id' => $response->deployment_id,
            'survey_version_id' => $response->survey_version_id,
            'branch_id' => $response->branch_id,
            'area_id' => $response->area_id,
            'staff_member_id' => $response->staff_member_id,
            'channel' => $response->channel,
        ];
    }
}
