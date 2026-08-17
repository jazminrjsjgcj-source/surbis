<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Analytics\QueryMetrics;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\StaffMember;
use App\Domain\Responses\AnonymityThreshold;
use App\Domain\Responses\Models\Response;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Analisis. RF-AO-ANL-* · RNF-AO-RES-003.
 *
 * Seccion propia y no dentro del Panel: el ANEXO 1 seccion 18 dice que el
 * dashboard no debe ser un deposito de indicadores. El Panel responde "que
 * requiere atencion"; aqui se viene a mirar numeros a proposito.
 */
final class AnalyticsController extends Controller
{
    public function __invoke(
        Request $request,
        QueryMetrics $metrics,
        AnonymityThreshold $threshold,
    ): InertiaResponse {
        // Reutiliza el permiso de respuestas: quien puede verlas puede ver
        // sus indicadores, y al reves no tendria sentido.
        $this->authorize('viewAny', Response::class);

        $membership = $this->activeMembership($request);
        $organization = $membership->organization;
        $filtros = $this->filters($request);

        return Inertia::render('Admin/Analytics/Index', [
            'filters' => $filtros,

            /*
             * Cada bloque pasa por QueryMetrics, que aplica el umbral.
             *
             * Ninguna pantalla consulta response_metrics directamente: si lo
             * hiciera, la primera que se olvidara de comprobar el umbral
             * abriria el agujero para todas.
             */
            'summary' => $metrics->summary($organization, $filtros)->toArray(),
            'daily' => $metrics->daily($organization, $filtros),

            'byBranch' => $this->named(
                $metrics->groupedBy($organization, 'branch', $filtros),
                Branch::class,
            ),
            'byArea' => $this->named(
                $metrics->groupedBy($organization, 'area', $filtros),
                Area::class,
            ),
            'byStaff' => $this->named(
                $metrics->groupedBy($organization, 'staff', $filtros),
                StaffMember::class,
            ),
            'byChannel' => $metrics->groupedBy($organization, 'channel', $filtros),

            /*
             * Cuando se actualizaron. Decision del area usuaria: el panel
             * dice cuando se calcularon por ultima vez.
             *
             * Sin esto, un numero desfasado es indistinguible de uno al dia,
             * y quien decida con el no sabra si mira ayer o hace una semana.
             */
            'updatedAt' => $metrics->lastUpdatedAt($organization),

            'threshold' => $threshold->of($organization),
            'branches' => $this->options(Branch::query()->forOrganization($organization->id)),
            'channels' => DeploymentChannel::values(),
            'indexUrl' => route('admin.analytics'),
        ]);
    }

    /**
     * Pone nombre a los grupos.
     *
     * Los ids no se muestran nunca: una tarjeta que diga "7" en vez de
     * "Palacio Municipal" no sirve para decidir nada. Y para los grupos SIN
     * datos suficientes se resuelve el nombre igual —la sucursal existe, lo
     * que no se puede enseñar son sus numeros—.
     *
     * @param  list<array<string, mixed>>  $grupos
     * @param  class-string  $model
     * @return list<array<string, mixed>>
     */
    private function named(array $grupos, string $model): array
    {
        $ids = array_values(array_filter(array_column($grupos, 'group')));

        if ($ids === []) {
            return [];
        }

        $nombres = $model::query()->whereKey($ids)->get()->keyBy('id');

        return array_values(array_map(function (array $grupo) use ($nombres, $model): array {
            $registro = $nombres->get($grupo['group']);

            return [
                ...$grupo,
                'name' => match (true) {
                    $registro === null => null,
                    $model === StaffMember::class => $registro->fullName(),
                    default => $registro->name,
                },
            ];
        }, array_filter($grupos, fn (array $g): bool => $g['group'] !== null)));
    }

    /**
     * El periodo por defecto: los ultimos treinta dias.
     *
     * Un panel sin filtros que muestre TODO el historico es lento y dice poco:
     * lo que interesa casi siempre es como va esto ultimamente.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $desde = $request->string('from')->toString()
            ?: CarbonImmutable::now()->subDays(30)->toDateString();

        $hasta = $request->string('to')->toString()
            ?: CarbonImmutable::now()->toDateString();

        return [
            'from' => $desde,
            'to' => $hasta,
            'branch' => $this->idOf(Branch::class, $request->string('branch')->toString()),
            'channel' => $request->string('channel')->toString() ?: null,
        ];
    }

    /**
     * De ULID a id interno.
     *
     * Los ULID viajan al navegador; los ids no salen nunca. Y se acota por
     * organizacion: sin eso, un ULID ajeno filtraria por una sucursal de otra
     * empresa —no daria datos, pero confirmaria que existe—.
     *
     * @param  class-string  $model
     */
    private function idOf(string $model, string $ulid): ?int
    {
        if ($ulid === '') {
            return null;
        }

        return $model::query()->where('ulid', $ulid)->value('id');
    }

    /** @return list<array<string, string>> */
    private function options($query): array
    {
        return $query->orderBy('name')->get(['ulid', 'name'])
            ->map(fn ($m): array => ['ulid' => $m->ulid, 'name' => $m->name])
            ->all();
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
