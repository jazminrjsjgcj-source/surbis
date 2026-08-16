<?php

declare(strict_types=1);

namespace App\Application\Responses;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Responses\Models\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Marcar una respuesta como invalida. RF-AO-RES-006 · RNF-AO-RES-004.
 *
 * NO la borra ni la edita: RF-AO-RES-005 prohibe modificar las respuestas
 * originales desde el panel. Lo que cambia es una marca al lado; lo que la
 * persona contesto sigue igual.
 */
final class InvalidateResponse
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(Response $response, User $actor, string $reason): Response
    {
        $motivo = trim($reason);

        /*
         * El motivo es obligatorio.
         *
         * "Invalidada" sin motivo no se puede revisar despues: nadie sabe si
         * fue una prueba del equipo, un duplicado o una manipulacion real. Y
         * quien la invalido puede no estar ya en la organizacion.
         */
        if ($motivo === '') {
            throw new InvalidArgumentException('El motivo de la invalidacion es obligatorio.');
        }

        return DB::transaction(function () use ($response, $actor, $motivo): Response {
            $response->forceFill([
                'invalidated_at' => now(),
                'invalidated_by' => $actor->id,
                'invalidation_reason' => $motivo,
            ])->save();

            // RNF-AO-RES-004: las invalidaciones quedan registradas.
            $this->audit->record('response.invalidated', $response, [
                'reason' => $motivo,
            ], actor: $actor);

            return $response;
        });
    }

    /**
     * Deshacer una invalidacion.
     *
     * Se conserva el motivo anterior en la auditoria, no en la fila: la
     * columna vuelve a null porque la respuesta ya no esta invalidada, y el
     * historial de lo que paso vive en audit_logs.
     */
    public function revert(Response $response, User $actor): Response
    {
        return DB::transaction(function () use ($response, $actor): Response {
            $anterior = $response->invalidation_reason;

            $response->forceFill([
                'invalidated_at' => null,
                'invalidated_by' => null,
                'invalidation_reason' => null,
            ])->save();

            $this->audit->record('response.revalidated', $response, [
                'previous_reason' => $anterior,
            ], actor: $actor);

            return $response;
        });
    }
}
