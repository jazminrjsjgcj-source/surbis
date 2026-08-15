<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Deployments\ChangeDeploymentStatus;
use App\Application\Deployments\CreateDeployment;
use App\Application\Deployments\Exceptions\DeploymentRejected;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Surveys\Models\Survey;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\DeploymentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Aplicaciones. RF-AO-DEP-001 a 006.
 *
 * El listado es GLOBAL —todo lo que aplica en la organizacion— pero crear
 * parte siempre de una encuesta: la version publicada es contexto de la ruta,
 * no algo que se elija en un desplegable. Decision del area usuaria.
 */
final class DeploymentController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Deployment::class);

        $membership = $this->activeMembership($request);

        $deployments = Deployment::query()
            ->forOrganization($membership->organization_id)
            // RNF-GEN-010: sin esto son cuatro consultas por fila.
            ->with(['version.survey', 'branch', 'area', 'device.branch'])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Deployments/Index', [
            'deployments' => $deployments->through(
                fn (Deployment $d): array => $this->serialize($d)
            ),
            'indexUrl' => route('admin.deployments.index'),
            'surveysUrl' => route('admin.surveys.index'),
        ]);
    }

    /**
     * El asistente. La version ya viene fijada por la ruta.
     */
    public function create(Request $request, Survey $survey): InertiaResponse
    {
        $this->authorize('create', Deployment::class);
        $this->authorize('view', $survey);

        $membership = $this->activeMembership($request);
        $version = $survey->publishedVersion;

        return Inertia::render('Admin/Deployments/Wizard', [
            'survey' => ['ulid' => $survey->ulid, 'name' => $survey->name],

            /*
             * Si no hay version publicada, la pantalla lo dice en lugar de
             * ofrecer un formulario que no puede enviarse. RF-AO-DEP-003.
             */
            'version' => $version === null ? null : [
                'number' => $version->version_number,
                'published_at' => $version->published_at?->diffForHumans(),
            ],

            /*
             * Que alcances admite cada canal, decidido en el SERVIDOR.
             *
             * Si el asistente lo dedujera por su cuenta, su criterio y el de
             * DeploymentChannel divergirian el dia que se anada un canal, y
             * la pantalla ofreceria combinaciones que el servidor rechaza.
             */
            'channels' => array_map(fn (DeploymentChannel $c): array => [
                'value' => $c->value,
                'requires_device' => $c->requiresDevice(),
                'scopes' => $c->requiresDevice()
                    ? [DeploymentScope::Device->value]
                    : DeploymentScope::values(),
            ], DeploymentChannel::cases()),

            'branches' => $this->branches($membership->organization_id),
            'devices' => $this->devices($membership->organization_id),

            'action' => route('admin.deployments.store', $survey),
            'cancelUrl' => route('admin.surveys.edit', $survey),
        ]);
    }

    public function store(
        DeploymentRequest $request,
        Survey $survey,
        CreateDeployment $create,
    ): RedirectResponse {
        $this->authorize('create', Deployment::class);
        $this->authorize('view', $survey);

        $membership = $this->activeMembership($request);
        $version = $survey->publishedVersion;

        if ($version === null) {
            return back()->withErrors(['channel' => __('interface.deployments.no_published_version')]);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            [, $token] = $create->execute(
                $membership->organization,
                $version,
                $user,
                DeploymentChannel::from($request->string('channel')->toString()),
                DeploymentScope::from($request->string('scope')->toString()),
                $this->targets($request, $membership->organization_id),
                $this->date($request->string('starts_at')->toString()),
                $this->date($request->string('ends_at')->toString()),
            );
        } catch (DeploymentRejected $rechazo) {
            // La clave se traduce aqui: el dominio no habla espanol a
            // proposito, para que la API futura pueda interpretarla.
            return back()->withErrors([
                'channel' => __("interface.deployments.rejected.{$rechazo->key}", $rechazo->replacements),
            ])->withInput();
        }

        return redirect()->route('admin.deployments.index')->with([
            'status' => __('interface.deployments.created'),

            /*
             * El token viaja UNA vez, en un flash.
             *
             * Es la unica ocasion en que existe en claro: en la base solo
             * queda su hash. Si se pierde, hay que regenerarlo y el anterior
             * deja de valer.
             */
            'public_token' => $token,
        ]);
    }

    public function activate(Request $request, Deployment $deployment, ChangeDeploymentStatus $status): RedirectResponse
    {
        $this->authorize('update', $deployment);

        /** @var User $user */
        $user = $request->user();

        $status->activate($deployment, $user);

        return back()->with('status', __('interface.deployments.activated'));
    }

    public function suspend(Request $request, Deployment $deployment, ChangeDeploymentStatus $status): RedirectResponse
    {
        $this->authorize('update', $deployment);

        /** @var User $user */
        $user = $request->user();

        $status->suspend($deployment, $user);

        return back()->with('status', __('interface.deployments.suspended'));
    }

    public function close(Request $request, Deployment $deployment, ChangeDeploymentStatus $status): RedirectResponse
    {
        $this->authorize('close', $deployment);

        /** @var User $user */
        $user = $request->user();

        $status->close($deployment, $user);

        return back()->with('status', __('interface.deployments.closed'));
    }

    /** @return array<string, mixed> */
    private function serialize(Deployment $deployment): array
    {
        $alcance = $deployment->scopeLabel();

        return [
            'ulid' => $deployment->ulid,
            'survey_name' => $deployment->version->survey->name,
            'version_number' => $deployment->version->version_number,
            'channel' => $deployment->channel->value,

            'scope' => $alcance['scope'],
            'scope_name' => $alcance['name'],

            'status' => $deployment->status->value,

            /*
             * "Aplicando" NO es lo mismo que "activo": uno activo con inicio
             * manana todavia no recibe respuestas. Van los dos porque la
             * pantalla necesita decir cual es cual.
             */
            'is_applying' => $deployment->isApplying(),
            'not_applying_reason' => $deployment->notApplyingReason(),

            'starts_at' => $deployment->starts_at?->toDateString(),
            'ends_at' => $deployment->ends_at?->toDateString(),

            'activate_url' => route('admin.deployments.activate', $deployment),
            'suspend_url' => route('admin.deployments.suspend', $deployment),
            'close_url' => route('admin.deployments.close', $deployment),
        ];
    }

    /**
     * @return array{branch?: ?Branch, area?: ?Area, device?: ?Device}
     */
    private function targets(Request $request, int $organizationId): array
    {
        return [
            'branch' => $this->find(Branch::class, $request->string('branch_ulid')->toString(), $organizationId),
            'area' => $this->find(Area::class, $request->string('area_ulid')->toString(), $organizationId),
            'device' => $this->find(Device::class, $request->string('device_ulid')->toString(), $organizationId),
        ];
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function find(string $model, string $ulid, int $organizationId)
    {
        if ($ulid === '') {
            return null;
        }

        /*
         * Se acota por organizacion AQUI ademas de en el guardian.
         *
         * El guardian lanza una excepcion si la entidad es ajena, pero
         * devolver null es mejor respuesta a un ULID que no existe: no
         * distingue "no existe" de "es de otra organizacion", y esa
         * diferencia es informacion.
         */
        return $model::query()
            ->where('organization_id', $organizationId)
            ->where('ulid', $ulid)
            ->first();
    }

    private function date(string $valor): ?CarbonImmutable
    {
        return $valor === '' ? null : CarbonImmutable::parse($valor);
    }

    /** @return list<array<string, mixed>> */
    private function branches(int $organizationId): array
    {
        return Branch::query()
            ->forOrganization($organizationId)
            ->active()
            ->with(['areas' => fn ($q) => $q->active()->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $b): array => [
                'ulid' => $b->ulid,
                'name' => $b->name,
                'areas' => $b->areas->map(fn (Area $a): array => [
                    'ulid' => $a->ulid,
                    'name' => $a->name,
                ])->all(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function devices(int $organizationId): array
    {
        return Device::query()
            ->forOrganization($organizationId)
            ->active()
            ->with('branch')
            ->orderBy('name')
            ->get()
            ->map(fn (Device $d): array => [
                'ulid' => $d->ulid,
                'name' => $d->name,
                'location' => $d->location(),
            ])
            ->all();
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
