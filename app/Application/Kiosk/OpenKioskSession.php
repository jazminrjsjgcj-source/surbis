<?php

declare(strict_types=1);

namespace App\Application\Kiosk;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use App\Domain\Kiosk\Models\KioskSession;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Abrir la sesion de una estacion. RF-COL-001 a 006 · RNF-COL-002.
 *
 * Una sola sesion activa por dispositivo. Al cambiar de colaborador se
 * SUSTITUYE, no se reanuda. Decision del area usuaria, 18 ago 2026.
 *
 * Reanudar atribuiria a la primera persona lo que evaluo la segunda: si el
 * turno cambio, las respuestas siguientes son del nuevo colaborador, y ese
 * error no se ve —los datos entran, solo que en la cuenta equivocada—.
 */
final class OpenKioskSession
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(
        Device $device,
        Deployment $deployment,
        ?StaffMember $staffMember = null,
        ?User $openedBy = null,
    ): KioskSession {
        return DB::transaction(function () use ($device, $deployment, $staffMember, $openedBy): KioskSession {
            /*
             * Se bloquea la sesion abierta ANTES de decidir. RNF-COL-002.
             *
             * Sin el bloqueo, dos preparaciones simultaneas en la misma
             * tableta —alguien pulsa dos veces, o dos pestanas— pasarian las
             * dos por la comprobacion y las dos intentarian crear. El indice
             * unico de la base lo impediria, pero con un error feo en lugar
             * de una sustitucion limpia.
             */
            $abierta = KioskSession::query()
                ->where('device_id', $device->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            /*
             * Si es la MISMA persona, se reanuda.
             *
             * Volver a preparar la estacion con el mismo colaborador no es un
             * cambio de turno: es alguien que recargo la pantalla. Cerrar y
             * abrir ahi partiria el turno en dos por nada.
             */
            if ($abierta !== null && $abierta->staff_member_id === $staffMember?->id) {
                $abierta->forceFill(['last_activity_at' => now()])->save();

                return $abierta;
            }

            if ($abierta !== null) {
                $abierta->forceFill([
                    'closed_at' => now(),
                    'closed_reason' => 'replaced',
                ])->save();

                $this->audit->record('kiosk_session.replaced', $abierta, [
                    'device' => $device->name,
                ], actor: $openedBy);
            }

            $sesion = KioskSession::query()->create([
                /*
                 * La organizacion sale del DISPOSITIVO, no de quien la abre.
                 *
                 * RNF-COL-001: organizacion, colaborador, encuesta y sucursal
                 * se determinan en el servidor.
                 */
                'organization_id' => $device->organization_id,
                'device_id' => $device->id,
                'deployment_id' => $deployment->id,
                'staff_member_id' => $staffMember?->id,
                'opened_by' => $openedBy?->id,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);

            $this->audit->record('kiosk_session.opened', $sesion, [
                'device' => $device->name,
                'staff_member' => $staffMember?->fullName(),
            ], actor: $openedBy);

            return $sesion;
        });
    }

    /**
     * Cerrar a proposito.
     *
     * NO borra la sesion: las respuestas que se dieron durante ese turno
     * apuntan a ella y tienen que seguir explicando de quien eran.
     */
    public function close(KioskSession $session, ?User $actor = null, string $reason = 'manual'): KioskSession
    {
        return DB::transaction(function () use ($session, $actor, $reason): KioskSession {
            $session->forceFill([
                'closed_at' => now(),
                'closed_reason' => $reason,
            ])->save();

            $this->audit->record('kiosk_session.closed', $session, [
                'reason' => $reason,
            ], actor: $actor);

            return $session;
        });
    }

    /**
     * Marcar actividad. RF-COL-013.
     *
     * Sin auditoria: ocurre en cada respuesta y llenaria el registro de ruido
     * hasta hacer imposible encontrar lo que importa.
     */
    public function touch(KioskSession $session): void
    {
        $session->forceFill(['last_activity_at' => now()])->save();
    }
}
