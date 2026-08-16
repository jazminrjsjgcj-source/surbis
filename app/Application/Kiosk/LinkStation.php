<?php

declare(strict_types=1);

namespace App\Application\Kiosk;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Kiosk\Models\KioskCredential;
use App\Domain\Kiosk\StationKey;
use App\Domain\Organizations\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vincular una tableta con su clave temporal.
 *
 * La clave sirve UNA vez y caduca a las 24 horas; lo que queda despues es una
 * credencial propia que dura un ano y se renueva sola mientras se use.
 * Decision del area usuaria, 18 ago 2026.
 */
final class LinkStation
{
    /** Un ano. Lo que deja de usarse se apaga solo. */
    private const LIFETIME_DAYS = 365;

    /**
     * Se renueva si le queda menos de un mes.
     *
     * Renovarla en cada peticion escribiria en la base miles de veces al dia
     * sin ganar nada; esperar al ultimo dia dejaria fuera una tableta que
     * estuvo apagada una semana.
     */
    private const RENEW_WITHIN_DAYS = 30;

    public function __construct(
        private readonly StationKey $keys,
        private readonly ManageStationKey $stationKeys,
        private readonly ResolveStation $stations,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * Canjea la clave por una credencial. Devuelve el token EN CLARO.
     *
     * @return array{0: Device, 1: string}
     *
     * @throws StationNotReady
     */
    public function link(string $stationKey): array
    {
        $device = $this->stations->device($stationKey);

        /*
         * La clave tiene que seguir siendo USABLE, no solo existir.
         *
         * ResolveStation ya rechaza las revocadas, pero no mira la caducidad:
         * ese es el limite de las 24 horas, y comprobarlo aqui evita que una
         * clave vieja apuntada en un papel vincule una tableta meses despues.
         */
        if (! $this->stationKeys->isUsable($device)) {
            throw StationNotReady::unknownDevice();
        }

        $token = Str::random(64);

        DB::transaction(function () use ($device, $token): void {
            /*
             * Las credenciales anteriores se revocan.
             *
             * Vincular de nuevo significa que la tableta anterior se perdio,
             * se cambio o se reinstalo. Dejar la vieja viva permitiria que
             * las dos enviaran respuestas.
             */
            KioskCredential::query()
                ->where('device_id', $device->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            KioskCredential::query()->create([
                'organization_id' => $device->organization_id,
                'device_id' => $device->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(self::LIFETIME_DAYS),
            ]);

            /*
             * Y la clave temporal se consume: ya cumplio su funcion.
             *
             * Sin esto, la misma clave vincularia otra tableta —y las dos
             * enviarian respuestas de la misma ventanilla—.
             */
            $device->forceFill(['station_key_revoked_at' => now()])->save();

            $this->audit->record('device.linked', $device, [
                'device' => $device->name,
            ]);
        });

        return [$device, $token];
    }

    /**
     * De un token guardado en la cookie a su dispositivo.
     *
     * @throws StationNotReady
     */
    public function resolve(string $token): Device
    {
        $credential = KioskCredential::query()
            ->where('token_hash', hash('sha256', $token))
            ->with('device')
            ->first();

        /*
         * Revocada, caducada o inexistente dan el MISMO error.
         *
         * Distinguirlas diria si ese token existio, y eso es informacion para
         * quien pruebe tokens. RNF-COL-004.
         */
        if ($credential === null || ! $credential->isUsable()) {
            throw StationNotReady::unknownDevice();
        }

        $this->touch($credential);

        return $credential->device;
    }

    /**
     * Marca uso y renueva si toca.
     *
     * Escribe solo cuando hace falta: en una tableta con doscientas
     * respuestas al dia, actualizar last_used_at en cada peticion son
     * doscientas escrituras por nada.
     */
    private function touch(KioskCredential $credential): void
    {
        $cambios = [];

        if ($credential->last_used_at === null || $credential->last_used_at->isYesterday()
            || $credential->last_used_at->lt(now()->startOfDay())) {
            $cambios['last_used_at'] = now();
        }

        if ($credential->expires_at->lt(now()->addDays(self::RENEW_WITHIN_DAYS))) {
            $cambios['expires_at'] = now()->addDays(self::LIFETIME_DAYS);
        }

        if ($cambios !== []) {
            $credential->forceFill($cambios)->save();
        }
    }
}
