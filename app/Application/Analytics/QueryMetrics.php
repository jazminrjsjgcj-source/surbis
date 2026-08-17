<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Analytics\MetricSummary;
use App\Domain\Analytics\Models\ResponseMetric;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Responses\AnonymityThreshold;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consultar indicadores. RNF-AO-RES-003 aplicado a la analitica.
 *
 * TODA salida pasa por aqui: tarjetas, promedios, graficos, comparaciones,
 * exportaciones y API. Decision del area usuaria, 18 ago 2026.
 *
 * Que este centralizado es lo que hace que el umbral funcione. Si cada
 * pantalla consultara la tabla por su cuenta, la primera que se olvidara de
 * comprobarlo abriria el agujero para todas.
 */
final class QueryMetrics
{
    public function __construct(private readonly AnonymityThreshold $threshold) {}

    /**
     * El total del periodo, con los filtros dados.
     *
     * @param  array<string, mixed>  $filters
     */
    public function summary(Organization $organization, array $filters): MetricSummary
    {
        $fila = $this->base($organization, $filters)
            ->selectRaw('
                coalesce(sum(responses), 0) as responses,
                coalesce(sum(invalidated), 0) as invalidated,
                coalesce(sum(score_sum), 0) as score_sum,
                coalesce(sum(max_score_sum), 0) as max_score_sum,
                coalesce(sum(scored_responses), 0) as scored
            ')
            ->first();

        return $this->summarize($organization, $fila);
    }

    /**
     * La serie por dia, para el grafico.
     *
     * CADA punto pasa por el umbral por separado: un mes con mil respuestas
     * puede tener un martes con dos, y ese punto identificaria a alguien
     * aunque el total no lo haga.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function daily(Organization $organization, array $filters): array
    {
        return $this->base($organization, $filters)
            ->selectRaw('
                day,
                sum(responses) as responses,
                sum(invalidated) as invalidated,
                sum(score_sum) as score_sum,
                sum(max_score_sum) as max_score_sum,
                sum(scored_responses) as scored
            ')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($fila): array => [
                'day' => CarbonImmutable::parse($fila->day)->toDateString(),
                ...$this->summarize($organization, $fila)->toArray(),
            ])
            ->all();
    }

    /**
     * Agrupado por sucursal, area o persona.
     *
     * Aqui el umbral importa MAS que en ningun sitio: comparar ventanillas es
     * exactamente donde una con tres respuestas señala a quien la atendia.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function groupedBy(Organization $organization, string $dimension, array $filters): array
    {
        $columna = match ($dimension) {
            'branch' => 'branch_id',
            'area' => 'area_id',
            'staff' => 'staff_member_id',
            'channel' => 'channel',
            default => 'branch_id',
        };

        return $this->base($organization, $filters)
            ->selectRaw("
                {$columna} as grupo,
                sum(responses) as responses,
                sum(invalidated) as invalidated,
                sum(score_sum) as score_sum,
                sum(max_score_sum) as max_score_sum,
                sum(scored_responses) as scored
            ")
            ->groupBy($columna)
            ->get()
            ->map(fn ($fila): array => [
                'group' => $fila->grupo,
                ...$this->summarize($organization, $fila)->toArray(),
            ])
            ->all();
    }

    /** Cuando se actualizaron por ultima vez. Decision del area usuaria. */
    public function lastUpdatedAt(Organization $organization): ?string
    {
        return ResponseMetric::query()
            ->forOrganization($organization->id)
            ->max('updated_at');
    }

    /**
     * El umbral se aplica AQUI, en un solo sitio.
     *
     * Por debajo: ni valores ni cantidades exactas. Decir "datos
     * insuficientes: hay 3" ya es informacion.
     */
    private function summarize(Organization $organization, ?object $fila): MetricSummary
    {
        $respuestas = (int) ($fila->responses ?? 0);
        $invalidadas = (int) ($fila->invalidated ?? 0);

        if (! $this->threshold->allows($organization, $respuestas)) {
            return MetricSummary::insufficient($invalidadas);
        }

        return MetricSummary::of(
            $respuestas,
            (int) ($fila->score_sum ?? 0),
            (int) ($fila->max_score_sum ?? 0),
            (int) ($fila->scored ?? 0),
            $invalidadas,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ResponseMetric>
     */
    private function base(Organization $organization, array $filters): Builder
    {
        return ResponseMetric::query()
            ->forOrganization($organization->id)
            ->when($filters['from'] ?? null, fn (Builder $q, string $d) => $q->where('day', '>=', $d))
            ->when($filters['to'] ?? null, fn (Builder $q, string $d) => $q->where('day', '<=', $d))
            ->when($filters['branch'] ?? null, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($filters['area'] ?? null, fn (Builder $q, int $id) => $q->where('area_id', $id))
            ->when($filters['staff'] ?? null, fn (Builder $q, int $id) => $q->where('staff_member_id', $id))
            ->when($filters['channel'] ?? null, fn (Builder $q, string $c) => $q->where('channel', $c))
            ->when($filters['version'] ?? null, fn (Builder $q, int $id) => $q->where('survey_version_id', $id));
    }
}
