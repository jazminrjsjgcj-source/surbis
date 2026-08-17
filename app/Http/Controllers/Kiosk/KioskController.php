<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Application\Kiosk\OpenKioskSession;
use App\Application\Kiosk\ResolveStation;
use App\Domain\Kiosk\Models\KioskSession;
use App\Domain\Kiosk\OfflineLimits;
use App\Domain\Organizations\Enums\StaffMemberStatus;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\StaffMember;
use App\Domain\Surveys\Enums\RenderLayout;
use App\Domain\Surveys\Rendering\RenderableSurvey;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveKioskStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * El quiosco. RF-COL-006 a 013.
 *
 * Lo que ve un ciudadano de pie en una ventanilla: sin sesion, sin
 * navegacion, sin nada administrativo (RF-COL-011).
 */
final class KioskController extends Controller
{
    /**
     * Preparar la estacion: elegir a quien se evalua. RF-COL-001 a 006.
     */
    public function prepare(
        Request $request,
        ResolveStation $stations,
        OfflineLimits $limits,
    ): InertiaResponse|RedirectResponse {
        $device = $this->device($request);

        if ($device === null) {
            return redirect()->route('kiosk.link');
        }

        try {
            $deployment = $stations->deployment($device);
        } catch (StationNotReady $problema) {
            return $this->notReady($problema, $device);
        }

        /*
         * Solo el personal de ESTA sucursal.
         *
         * Ofrecer toda la organizacion en una tableta de ventanilla llenaria
         * la lista de gente que no trabaja ahi, y elegir mal atribuiria las
         * respuestas a otra persona.
         */
        $staff = StaffMember::query()
            ->forOrganization($device->organization_id)
            ->where('branch_id', $device->branch_id)

            // Solo activas: quien esta archivado no atiende ventanilla, y
            // ofrecerlo llevaria a atribuir respuestas a alguien que ya no
            // trabaja ahi.
            ->where('status', StaffMemberStatus::Active)
            ->orderBy('first_name')
            ->get()
            ->map(fn (StaffMember $s): array => [
                'ulid' => $s->ulid,
                'name' => $s->fullName(),
            ])
            ->all();

        return Inertia::render('Kiosk/Prepare', [
            'device' => ['name' => $device->name, 'branch' => $device->branch?->name],
            'survey' => ['name' => $deployment->version->survey->name],
            'staff' => $staff,
            'current' => $this->openSession($device)?->staffMember?->ulid,
            'action' => route('kiosk.prepare.store'),

            /*
             * Los limites los decide el SERVIDOR.
             *
             * Si el cliente los llevara escritos, cambiar el ajuste de una
             * organizacion no llegaria a las tabletas hasta que alguien
             * recompilara.
             */
            'offline' => [
                'limitDays' => $limits->of($device->organization)['days'],
                'limitCount' => $limits->of($device->organization)['count'],
                'warnAt' => OfflineLimits::WARN_AT,
            ],
        ]);
    }

    public function open(
        Request $request,
        ResolveStation $stations,
        OpenKioskSession $sessions,
    ): RedirectResponse {
        $device = $this->device($request);

        if ($device === null) {
            return redirect()->route('kiosk.link');
        }

        try {
            $deployment = $stations->deployment($device);
        } catch (StationNotReady) {
            return redirect()->route('kiosk.welcome');
        }

        /*
         * La persona se busca por ULID y ACOTADA a esta sucursal.
         *
         * RNF-COL-001: el navegador no decide a quien se evalua. Sin acotar,
         * bastaria con enviar el ULID de alguien de otra oficina.
         */
        $staff = null;
        $ulid = $request->string('staff')->toString();

        if ($ulid !== '') {
            $staff = StaffMember::query()
                ->where('organization_id', $device->organization_id)
                ->where('branch_id', $device->branch_id)
                ->where('ulid', $ulid)
                ->first();
        }

        $sessions->execute($device, $deployment, $staff);

        // RF-COL-006: al terminar la preparacion, directo al quiosco.
        return redirect()->route('kiosk.welcome');
    }

    /**
     * La bienvenida. RF-COL-010 y 011.
     */
    public function welcome(Request $request, ResolveStation $stations): InertiaResponse|RedirectResponse
    {
        $device = $this->device($request);

        if ($device === null) {
            return redirect()->route('kiosk.link');
        }

        try {
            $deployment = $stations->deployment($device);
        } catch (StationNotReady $problema) {
            return $this->notReady($problema, $device);
        }

        $session = $this->openSession($device);

        // Sin sesion abierta no se puede contestar: no se sabria de quien es
        // el turno.
        if ($session === null) {
            return redirect()->route('kiosk.prepare');
        }

        return Inertia::render('Kiosk/Welcome', [
            'survey' => (new RenderableSurvey($deployment->version, RenderLayout::Stepped))->toArray(),

            /*
             * NADA administrativo. RF-COL-011: ni navegacion, ni resultados,
             * ni datos privados del colaborador.
             *
             * Ni siquiera el nombre de quien esta siendo evaluado: quien
             * contesta no tiene por que saber que su respuesta se atribuye a
             * una persona concreta, y saberlo cambia lo que contesta.
             */
            'submitUrl' => route('kiosk.submit'),
            'sessionUlid' => $session->ulid,

            /*
             * La version con la que se contesta, para que una respuesta en
             * cola conserve la suya.
             *
             * Decision del area usuaria: si el deployment cambia mientras la
             * tableta esta desconectada, las pendientes se sincronizan con su
             * version original. Nunca se mezclan preguntas de dos versiones
             * ni se migran respuestas.
             */
            'surveyVersionId' => $deployment->version->ulid,
        ]);
    }

    private function device(Request $request): ?Device
    {
        $device = $request->attributes->get(ResolveKioskStation::REQUEST_ATTRIBUTE);

        return $device instanceof Device ? $device : null;
    }

    private function openSession(Device $device): ?KioskSession
    {
        return KioskSession::query()
            ->where('device_id', $device->id)
            ->whereNull('closed_at')
            ->with('staffMember')
            ->first();
    }

    /**
     * La pantalla de estacion no configurada. RF-COL-007 a 009.
     *
     * RNF-COL-004: no expone IDs internos, tokens ni rutas. Y RF-COL-008: no
     * deja corregir la configuracion desde aqui —quien esta delante es un
     * colaborador de ventanilla, no quien administra—.
     */
    private function notReady(StationNotReady $problema, Device $device): InertiaResponse
    {
        return Inertia::render('Kiosk/NotReady', [
            'reason' => $problema->key,

            // El nombre de la tableta SI: es lo que hay que decirle al
            // administrador al llamarle.
            'deviceName' => $device->name,
            'branchName' => $device->branch?->name,

            'retryUrl' => route('kiosk.welcome'),
        ]);
    }
}
