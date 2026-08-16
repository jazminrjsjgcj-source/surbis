<?php

declare(strict_types=1);

namespace App\Domain\Responses;

use App\Domain\Responses\Models\Response;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los filtros del listado de respuestas. RF-AO-RES-002 · RNF-AO-RES-001.
 *
 * Se filtra en PostgreSQL, no en PHP: una organizacion con cien mil
 * respuestas no cabe en memoria, y traerlas todas para descartar la mayoria
 * es la forma mas rapida de tumbar el servidor.
 *
 * Los indices compuestos que esto necesita ya existen en la migracion:
 * (organization_id, submitted_at), (deployment_id, submitted_at) y
 * (branch_id, submitted_at).
 */
final class ResponseFilters
{
    /**
     * @param  Builder<Response>  $query
     * @param  array<string, string|null>  $filters
     * @return Builder<Response>
     */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($this->date($filters['from'] ?? null), fn (Builder $q, CarbonImmutable $desde) => $q
                ->where('submitted_at', '>=', $desde->startOfDay()))

            ->when($this->date($filters['to'] ?? null), fn (Builder $q, CarbonImmutable $hasta) => $q
                // endOfDay y no la fecha a secas: sin esto, filtrar "hasta el
                // 17" descarta todo lo contestado ese mismo dia.
                ->where('submitted_at', '<=', $hasta->endOfDay()))

            ->when($filters['survey'] ?? null, fn (Builder $q, string $ulid) => $q
                ->whereHas('version.survey', fn (Builder $s) => $s->where('ulid', $ulid)))

            ->when($filters['branch'] ?? null, fn (Builder $q, string $ulid) => $q
                ->whereHas('branch', fn (Builder $b) => $b->where('ulid', $ulid)))

            ->when($filters['channel'] ?? null, fn (Builder $q, string $canal) => $q
                ->where('channel', $canal))

            /*
             * Por defecto se ven TODAS, validas e invalidadas.
             *
             * Ocultar las invalidadas por defecto las haria invisibles justo
             * para quien tiene que revisarlas. Los indicadores oficiales si
             * las excluyen, pero eso es la Fase 12.
             */
            ->when(($filters['validity'] ?? null) === 'valid', fn (Builder $q) => $q
                ->whereNull('invalidated_at'))

            ->when(($filters['validity'] ?? null) === 'invalid', fn (Builder $q) => $q
                ->whereNotNull('invalidated_at'));
    }

    private function date(?string $valor): ?CarbonImmutable
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor);
        } catch (\Throwable) {
            // Una fecha ilegible se ignora en lugar de reventar: viene de la
            // URL, y cualquiera puede escribir cualquier cosa ahi.
            return null;
        }
    }
}
