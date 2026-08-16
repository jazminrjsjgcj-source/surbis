<?php

declare(strict_types=1);

namespace App\Application\Deployments;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Activar una encuesta en toda una sucursal. Decision del area usuaria,
 * 18 ago 2026.
 *
 * NO crea un deployment de sucursal: crea o reemplaza UNO POR DISPOSITIVO.
 *
 * La diferencia importa. Un deployment de sucursal habria sido una segunda
 * forma de estar aplicando, y al desactivar una tableta suelta habria que
 * decidir cual gana. Asi solo hay una verdad —el deployment de cada
 * dispositivo— y el interruptor de sucursal es una VISTA calculada sobre
 * ellos, no una entidad.
 *
 * Por debajo siguen existiendo K-001, K-002, K-003, cada uno con sus
 * sesiones, sus metricas y su revocacion.
 */
final class ActivateBranchKiosks
{
    public function __construct(
        private readonly DeploymentGuard $guard,
        private readonly ChangeDeploymentStatus $status,
        private readonly CreateDeployment $create,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @return array{created: int, replaced: int, devices: int}
     */
    public function activate(Branch $branch, SurveyVersion $version, User $actor): array
    {
        $devices = Device::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->get();

        return DB::transaction(function () use ($branch, $version, $actor, $devices): array {
            $creados = 0;
            $reemplazados = 0;

            foreach ($devices as $device) {
                $actual = $this->currentFor($device);

                /*
                 * Si ya aplica ESTA version, no se toca.
                 *
                 * Reemplazarlo cerraria una sesion viva y partiria el turno
                 * de alguien por una operacion que no cambia nada.
                 */
                if ($actual !== null && $actual->survey_version_id === $version->id) {
                    if ($actual->status !== DeploymentStatus::Active) {
                        $this->status->activate($actual, $actor);
                    }

                    continue;
                }

                /*
                 * Con OTRA encuesta se cierra y se crea uno nuevo. Decision
                 * del area usuaria.
                 *
                 * Activar "toda la sucursal" y que tres tabletas siguieran
                 * con otra encuesta seria un resultado que nadie espera. Y
                 * cerrar en vez de editar conserva las respuestas ya
                 * recibidas apuntando a donde se dieron (RF-AO-DEP-006).
                 */
                if ($actual !== null) {
                    $this->status->close($actual, $actor);
                    $reemplazados++;
                }

                $this->create->execute(
                    $branch->organization,
                    $version,
                    $actor,
                    DeploymentChannel::Kiosk,
                    DeploymentScope::Device,
                    ['device' => $device],
                );

                $creados++;
            }

            $this->audit->record('branch.kiosks_activated', $branch, [
                'version_number' => $version->version_number,
                'created' => $creados,
                'replaced' => $reemplazados,
            ], actor: $actor);

            return ['created' => $creados, 'replaced' => $reemplazados, 'devices' => $devices->count()];
        });
    }

    /**
     * Apagar toda la sucursal.
     *
     * Suspende en lugar de cerrar: cerrar es definitivo y obligaria a crear
     * deployments nuevos para volver a encender. Una sucursal se apaga por
     * obras o por vacaciones, y despues se enciende.
     */
    public function suspend(Branch $branch, User $actor): int
    {
        return DB::transaction(function () use ($branch, $actor): int {
            $afectados = 0;

            foreach ($this->activeDeployments($branch) as $deployment) {
                $this->status->suspend($deployment, $actor);
                $afectados++;
            }

            $this->audit->record('branch.kiosks_suspended', $branch, [
                'affected' => $afectados,
            ], actor: $actor);

            return $afectados;
        });
    }

    /**
     * El estado del interruptor. RF-AO-DEP-005 en lote.
     *
     * "parcial" NO es un estado que alguien ponga: es lo que queda al
     * desactivar una tableta suelta. Por eso se calcula y no se guarda —una
     * columna con este valor se desincronizaria en cuanto alguien tocara un
     * deployment por su cuenta—.
     *
     * @return array{state: string, total: int, active: int}
     */
    public function state(Branch $branch): array
    {
        $total = Device::query()->where('branch_id', $branch->id)->active()->count();

        if ($total === 0) {
            return ['state' => 'no_devices', 'total' => 0, 'active' => 0];
        }

        $activos = $this->activeDeployments($branch)
            ->filter(fn (Deployment $d): bool => $d->isApplying())
            ->count();

        $estado = match (true) {
            $activos === 0 => 'inactive',
            $activos === $total => 'active',
            default => 'partial',
        };

        return ['state' => $estado, 'total' => $total, 'active' => $activos];
    }

    private function currentFor(Device $device): ?Deployment
    {
        return Deployment::query()
            ->where('device_id', $device->id)
            ->where('channel', DeploymentChannel::Kiosk)
            ->whereNull('closed_at')
            ->first();
    }

    /** @return Collection<int, Deployment> */
    private function activeDeployments(Branch $branch)
    {
        return Deployment::query()
            ->where('channel', DeploymentChannel::Kiosk)
            ->whereNull('closed_at')
            ->whereIn('device_id', Device::query()
                ->where('branch_id', $branch->id)
                ->active()
                ->select('id'))
            ->get();
    }
}
