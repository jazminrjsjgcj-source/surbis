<?php

declare(strict_types=1);

namespace App\Application\Kiosk;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Kiosk\StationKey;
use App\Domain\Organizations\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * Generar y revocar la clave de una estacion. TASK-005 · RNF-AO-DEP-003.
 *
 * La clave es TEMPORAL: sirve para vincular la tableta una vez, y despues la
 * tableta se mantiene con una credencial persistente propia. Decision del
 * area usuaria, 18 ago 2026.
 *
 * Esa separacion tiene una razon concreta: revocar una tableta perdida no
 * obliga a reconfigurar las demas, y una clave caducada ya no sirve para
 * vincular otra.
 */
final class ManageStationKey
{
    /**
     * 24 horas. Decision del area usuaria.
     *
     * Quien configura diez ventanillas no puede volver al panel entre cada
     * una. Y mas alla de un dia, una clave apuntada en un papel deja de ser
     * temporal.
     */
    public const VALID_HOURS = 24;

    public function __construct(
        private readonly StationKey $keys,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * Genera una clave nueva y devuelve el texto EN CLARO.
     *
     * Es la unica vez que existe: en la base solo queda su hash. Si se
     * pierde, se genera otra.
     */
    public function generate(Device $device, User $actor): string
    {
        $clave = $this->keys->generate();

        DB::transaction(function () use ($device, $actor, $clave): void {
            $device->forceFill([
                'station_key_hash' => $this->keys->hash($clave),
                'station_key_set_at' => now(),

                // Generar una clave nueva LEVANTA una revocacion anterior:
                // si alguien revoca y luego regenera, es que quiere volver a
                // usar el dispositivo.
                'station_key_revoked_at' => null,
            ])->save();

            $this->audit->record('device.station_key_generated', $device, [
                'device' => $device->name,
            ], actor: $actor);
        });

        return $clave;
    }

    /**
     * Revocar. RF-AO-DEP-010 aplicado a la estacion.
     *
     * NO borra el hash: lo marca. Borrarlo dejaria un dispositivo
     * indistinguible de uno que nunca se configuro, y nadie sabria si la
     * clave se retiro a proposito o se perdio.
     */
    public function revoke(Device $device, User $actor): void
    {
        DB::transaction(function () use ($device, $actor): void {
            $device->forceFill(['station_key_revoked_at' => now()])->save();

            $this->audit->record('device.station_key_revoked', $device, [
                'device' => $device->name,
            ], actor: $actor);
        });
    }

    /**
     * Si la clave sigue sirviendo para vincular.
     *
     * Tres formas de no servir, y las tres importan: no existe, se revoco, o
     * caduco. La tableta ya vinculada NO depende de esto —tiene su propia
     * credencial— asi que una clave caducada no apaga las estaciones que ya
     * funcionan.
     */
    public function isUsable(Device $device): bool
    {
        if ($device->station_key_hash === null || $device->station_key_revoked_at !== null) {
            return false;
        }

        return $device->station_key_set_at !== null
            && $device->station_key_set_at->addHours(self::VALID_HOURS)->isFuture();
    }

    /** @return array{state: string, expires_at: ?string} */
    public function status(Device $device): array
    {
        $estado = match (true) {
            $device->station_key_hash === null => 'never_set',
            $device->station_key_revoked_at !== null => 'revoked',
            ! $this->isUsable($device) => 'expired',
            default => 'usable',
        };

        return [
            'state' => $estado,
            'expires_at' => $estado === 'usable'
                ? $device->station_key_set_at?->addHours(self::VALID_HOURS)->toIso8601String()
                : null,
        ];
    }
}
