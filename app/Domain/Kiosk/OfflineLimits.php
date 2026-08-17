<?php

declare(strict_types=1);

namespace App\Domain\Kiosk;

use App\Domain\Organizations\Models\Organization;

/**
 * Cuanto puede aguantar una tableta sin conexion. Decision del area usuaria,
 * 18 ago 2026.
 *
 * NO se acumulan respuestas indefinidamente. Una cola de meses en un
 * dispositivo que puede perderse, romperse o reinstalarse es una perdida de
 * datos esperando a ocurrir, y ademas oculta que algo lleva semanas mal.
 *
 * Los limites se pueden ajustar por organizacion pero NO desactivar: quitar
 * el limite del todo convertiria un problema de conexion en una perdida
 * silenciosa.
 */
final class OfflineLimits
{
    /** Siete dias sin sincronizar. */
    public const DEFAULT_DAYS = 7;

    /** Cinco mil respuestas pendientes. */
    public const DEFAULT_COUNT = 5000;

    /**
     * A partir de aqui se avisa al colaborador.
     *
     * Al 80% queda margen para llamar a alguien; al 95% ya no. El aviso es
     * SOLO para el colaborador: quien contesta no puede hacer nada con esa
     * informacion.
     */
    public const WARN_AT = 0.8;

    /** Ni un dia ni una respuesta: eso seria desactivarlo. */
    private const MIN_DAYS = 1;

    private const MIN_COUNT = 100;

    /** @return array{days: int, count: int} */
    public function of(Organization $organization): array
    {
        $ajustes = $organization->settings['offline'] ?? [];

        return [
            'days' => max(self::MIN_DAYS, (int) ($ajustes['days'] ?? self::DEFAULT_DAYS)),
            'count' => max(self::MIN_COUNT, (int) ($ajustes['count'] ?? self::DEFAULT_COUNT)),
        ];
    }

    /**
     * El estado de la cola, para la pantalla de preparacion.
     *
     * Devuelve lo que el colaborador necesita para decidir: cuantas hay, cual
     * es la mas antigua, cuando se sincronizo por ultima vez y cuanto margen
     * queda. Un "hay problemas" sin numeros no permite decidir nada.
     *
     * @return array{state: string, pending: int, oldest_days: int, limit_days: int, limit_count: int, capacity: float}
     */
    public function assess(Organization $organization, int $pending, ?int $oldestAgeDays): array
    {
        $limites = $this->of($organization);

        $porCantidad = $limites['count'] > 0 ? $pending / $limites['count'] : 0.0;
        $porTiempo = $limites['days'] > 0 ? ($oldestAgeDays ?? 0) / $limites['days'] : 0.0;

        // Lo que ocurra PRIMERO: se toma el peor de los dos.
        $ocupacion = max($porCantidad, $porTiempo);

        $estado = match (true) {
            $ocupacion >= 1.0 => 'blocked',
            $ocupacion >= self::WARN_AT => 'warning',
            default => 'ok',
        };

        return [
            'state' => $estado,
            'pending' => $pending,
            'oldest_days' => $oldestAgeDays ?? 0,
            'limit_days' => $limites['days'],
            'limit_count' => $limites['count'],
            'capacity' => round(max(0.0, 1.0 - $ocupacion), 3),
        ];
    }
}
