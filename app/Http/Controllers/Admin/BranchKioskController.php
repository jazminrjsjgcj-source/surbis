<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Deployments\ActivateBranchKiosks;
use App\Application\Kiosk\ManageStationKey;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateBranchKiosksRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Los quioscos de una sucursal. Decision del area usuaria, 18 ago 2026.
 *
 * El interruptor NO es un deployment de sucursal: es una operacion en lote
 * sobre los deployments de sus dispositivos. Por debajo siguen existiendo
 * K-001, K-002, K-003, cada uno con sus sesiones, su clave y su revocacion.
 */
final class BranchKioskController extends Controller
{
    public function show(
        Branch $branch,
        ActivateBranchKiosks $lote,
        ManageStationKey $keys,
    ): InertiaResponse {
        $this->authorize('view', $branch);

        $devices = Device::query()
            ->where('branch_id', $branch->id)
            ->with('area')
            ->orderBy('name')
            ->get();

        // Los deployments de quiosco abiertos, por dispositivo.
        $deployments = Deployment::query()
            ->where('channel', DeploymentChannel::Kiosk)
            ->whereNull('closed_at')
            ->whereIn('device_id', $devices->pluck('id'))
            ->with('version.survey')
            ->get()
            ->keyBy('device_id');

        return Inertia::render('Admin/Branches/Kiosks', [
            'branch' => ['ulid' => $branch->ulid, 'name' => $branch->name],

            /*
             * El estado del interruptor se CALCULA, no se guarda.
             *
             * Una columna con este valor se desincronizaria en cuanto alguien
             * tocara un deployment por su cuenta. Y "parcial" no es un estado
             * que nadie ponga: es lo que queda al desactivar una tableta
             * suelta.
             */
            'switch' => $lote->state($branch),

            'devices' => $devices->map(function (Device $device) use ($deployments, $keys): array {
                $deployment = $deployments->get($device->id);

                return [
                    'ulid' => $device->ulid,
                    'name' => $device->name,
                    'code' => $device->code,
                    'areaName' => $device->area?->name,
                    'isActive' => $device->isActive(),

                    'survey' => $deployment?->version->survey->name,
                    'versionNumber' => $deployment?->version->version_number,
                    'isApplying' => $deployment?->isApplying() ?? false,
                    'notApplyingReason' => $deployment?->notApplyingReason(),

                    /*
                     * El ESTADO de la clave, nunca la clave.
                     *
                     * Solo existe en claro al generarla: en la base queda su
                     * hash. Aqui se dice si sirve, si caduco o si se revoco.
                     */
                    'key' => $keys->status($device),

                    'generateKeyUrl' => route('admin.devices.key.generate', $device),
                    'revokeKeyUrl' => route('admin.devices.key.revoke', $device),
                    'suspendUrl' => $deployment === null
                        ? null
                        : route('admin.deployments.suspend', $deployment),
                    'activateUrl' => $deployment === null
                        ? null
                        : route('admin.deployments.activate', $deployment),
                ];
            })->all(),

            /*
             * Solo versiones PUBLICADAS: un borrador cambia cada vez que
             * alguien escribe, y desplegarlo daria encuestas distintas a dos
             * personas que creen contestar la misma (RF-AO-DEP-003).
             */
            'versions' => $this->publishedVersions($branch->organization_id),

            'activateUrl' => route('admin.branches.kiosks.activate', $branch),
            'suspendUrl' => route('admin.branches.kiosks.suspend', $branch),
            'backUrl' => route('admin.branches.edit', $branch),
        ]);
    }

    public function activate(
        ActivateBranchKiosksRequest $request,
        Branch $branch,
        ActivateBranchKiosks $lote,
    ): RedirectResponse {
        $this->authorize('update', $branch);

        $version = SurveyVersion::query()
            ->where('organization_id', $branch->organization_id)
            ->where('ulid', $request->string('version')->toString())
            ->first();

        if ($version === null) {
            return back()->withErrors(['version' => __('interface.kiosks.version_not_found')]);
        }

        /** @var User $user */
        $user = $request->user();

        $resultado = $lote->activate($branch, $version, $user);

        return back()->with('status', __('interface.kiosks.activated', $resultado));
    }

    public function suspend(
        Request $request,
        Branch $branch,
        ActivateBranchKiosks $lote,
    ): RedirectResponse {
        $this->authorize('update', $branch);

        /** @var User $user */
        $user = $request->user();

        $afectados = $lote->suspend($branch, $user);

        return back()->with('status', __('interface.kiosks.suspended', ['count' => $afectados]));
    }

    /** @return list<array<string, mixed>> */
    private function publishedVersions(int $organizationId): array
    {
        return Survey::query()
            ->forOrganization($organizationId)
            ->with(['publishedVersion'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Survey $s): bool => $s->publishedVersion !== null)
            ->map(fn (Survey $s): array => [
                'ulid' => $s->publishedVersion->ulid,
                'name' => $s->name,
                'versionNumber' => $s->publishedVersion->version_number,
            ])
            ->values()
            ->all();
    }
}
