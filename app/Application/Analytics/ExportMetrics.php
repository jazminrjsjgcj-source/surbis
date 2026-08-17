<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Exportar indicadores. Decision del area usuaria, 19 ago 2026.
 *
 * USA EL MISMO QueryMetrics QUE EL PANEL. No repite consultas ni filtros: una
 * exportacion que agregara por su cuenta seria la puerta de atras del umbral
 * —el mismo dato que la pantalla oculta saldria en un archivo—.
 *
 * SOLO indicadores agregados. Nunca respuestas individuales, comentarios,
 * nombres ni correos de quien contesto. Exportar eso sera una funcion
 * separada, con permiso especifico, justificacion y auditoria: no es "lo
 * mismo con mas detalle", es otra operacion con otro riesgo.
 */
final class ExportMetrics
{
    public function __construct(private readonly QueryMetrics $metrics) {}

    /**
     * Las filas del archivo, con cabecera.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, list<string>>
     */
    public function rows(Organization $organization, array $filters): Collection
    {
        $filas = collect([
            [
                __('interface.export.section'),
                __('interface.export.group'),
                __('interface.analytics.responses'),
                __('interface.analytics.average'),
                __('interface.analytics.percentage'),
                __('interface.export.excluded'),
            ],
        ]);

        $resumen = $this->metrics->summary($organization, $filters);

        $filas->push($this->line(
            __('interface.export.total'),
            __('interface.export.period'),
            $resumen->toArray(),
        ));

        foreach ($this->metrics->daily($organization, $filters) as $dia) {
            $filas->push($this->line(__('interface.export.by_day'), $dia['day'], $dia));
        }

        foreach (['branch', 'area', 'staff', 'channel'] as $dimension) {
            foreach ($this->metrics->groupedBy($organization, $dimension, $filters) as $grupo) {
                $filas->push($this->line(
                    __("interface.analytics.{$dimension}"),
                    (string) ($grupo['group'] ?? __('interface.analytics.unassigned')),
                    $grupo,
                ));
            }
        }

        return $filas;
    }

    /**
     * Una fila.
     *
     * Cuando no se alcanza el umbral, las tres columnas de valores dicen
     * "Datos insuficientes" y NO llevan numeros: ni el promedio ni cuantas
     * respuestas hay. Es la misma regla que en pantalla, y por eso sale del
     * mismo sitio.
     *
     * @param  array<string, mixed>  $metric
     * @return list<string>
     */
    private function line(string $seccion, string $grupo, array $metric): array
    {
        $insuficiente = __('interface.analytics.insufficient');

        if (($metric['available'] ?? false) !== true) {
            return [
                $seccion,
                $grupo,
                $insuficiente,
                $insuficiente,
                $insuficiente,

                // Las invalidadas SI se dicen: no revelan nada de quien
                // contesto, y ocultarlas haria pensar que no se excluyo nada.
                (string) ($metric['invalidated'] ?? 0),
            ];
        }

        return [
            $seccion,
            $grupo,
            (string) $metric['responses'],
            $metric['average'] === null ? '' : (string) $metric['average'],
            $metric['percentage'] === null ? '' : (string) $metric['percentage'],
            (string) $metric['invalidated'],
        ];
    }
}
