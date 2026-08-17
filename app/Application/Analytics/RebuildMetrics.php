<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Analytics\Models\ResponseMetric;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Responses\Models\Response;
use Illuminate\Support\Facades\DB;

/**
 * Rehace los indicadores desde las respuestas. Decision del area usuaria.
 *
 * "Periodicamente se reconstruiran para corregir posibles desfases": un
 * resumen que solo se actualiza con incrementos acumula errores —una
 * respuesta que se guarda mientras el proceso falla, un despliegue a mitad—
 * y sin forma de rehacerlo esos errores son permanentes.
 *
 * Que exista esto es lo que permite decir que las respuestas son la fuente
 * oficial. Sin reconstruccion, la tabla de indicadores seria una segunda
 * verdad imposible de contrastar.
 */
final class RebuildMetrics
{
    public function __construct(private readonly RecordResponseMetric $recorder) {}

    /**
     * @return array{days: int, responses: int}
     */
    public function forOrganization(Organization $organization, ?string $from = null): array
    {
        return DB::transaction(function () use ($organization, $from): array {
            /*
             * Se borra el periodo entero antes de rehacerlo.
             *
             * Actualizar fila a fila dejaria las combinaciones que ya no
             * existen —una sucursal archivada, un turno que se cerro— con sus
             * viejos numeros dentro.
             */
            ResponseMetric::query()
                ->forOrganization($organization->id)
                ->when($from, fn ($q, string $d) => $q->where('day', '>=', $d))
                ->delete();

            $respuestas = 0;

            Response::query()
                ->forOrganization($organization->id)
                ->when($from, fn ($q, string $d) => $q->where('submitted_at', '>=', $d))
                ->with('organization')
                /*
                 * Por tandas: cien mil respuestas en memoria tumbarian el
                 * proceso, y esto corre en segundo plano sin nadie mirando.
                 */
                ->chunkById(500, function ($tanda) use (&$respuestas): void {
                    foreach ($tanda as $response) {
                        /*
                         * Las invalidadas cuentan como EXCLUIDAS, no como
                         * validas: entran en el recuento de descartadas y no
                         * suman a la puntuacion.
                         */
                        if ($response->invalidated_at !== null) {
                            $this->recorder->recordInvalidated($response);

                            continue;
                        }

                        $this->recorder->record($response);
                        $respuestas++;
                    }
                });

            return [
                'days' => ResponseMetric::query()->forOrganization($organization->id)->count(),
                'responses' => $respuestas,
            ];
        });
    }
}
