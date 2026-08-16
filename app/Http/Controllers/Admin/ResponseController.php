<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Responses\InvalidateResponse;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Responses\AnonymityThreshold;
use App\Domain\Responses\Models\Response;
use App\Domain\Responses\Models\ResponseAnswer;
use App\Domain\Responses\ResponseFilters;
use App\Domain\Surveys\Models\Survey;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\InvalidateResponseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Consultar respuestas. RF-AO-RES-001 a 006.
 */
final class ResponseController extends Controller
{
    private const PER_PAGE = 25;

    public function index(
        Request $request,
        ResponseFilters $filters,
        AnonymityThreshold $threshold,
    ): InertiaResponse {
        $this->authorize('viewAny', Response::class);

        $membership = $this->activeMembership($request);
        $filtros = $this->filterValues($request);

        $query = $filters->apply(
            Response::query()->forOrganization($membership->organization_id),
            $filtros,
        );

        /*
         * El umbral se comprueba sobre el resultado FILTRADO. RNF-AO-RES-003.
         *
         * Es el punto: sin filtros hay mil respuestas y no se identifica a
         * nadie; filtrando por una ventanilla y un dia quedan dos, y ahi si.
         * Por eso se cuenta despues de filtrar y no antes.
         */
        $total = (clone $query)->count();
        $suficientes = $threshold->allows($membership->organization, $total);

        $responses = $query
            // RNF-GEN-010: sin esto son varias consultas por fila.
            ->with(['version.survey', 'branch'])
            ->orderByDesc('submitted_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Responses/Index', [
            /*
             * Si no se alcanza el umbral, NO se mandan las filas.
             *
             * Ocultarlas en el componente dejaria los datos en el JSON de
             * props, donde cualquiera puede leerlos. El umbral tiene que
             * llegar hasta aqui.
             */
            'responses' => $suficientes
                ? $responses->through(fn (Response $r): array => $this->row($r))
                : null,

            'total' => $total,
            'thresholdMet' => $suficientes,
            'threshold' => $threshold->of($membership->organization),

            'filters' => $filtros,
            'surveys' => $this->surveys($membership->organization_id),
            'branches' => $this->branches($membership->organization_id),
            'channels' => DeploymentChannel::values(),
            'indexUrl' => route('admin.responses.index'),
        ]);
    }

    public function show(
        Request $request,
        Response $response,
        RecordAuditLog $audit,
    ): InertiaResponse {
        $this->authorize('view', $response);

        $response->load(['answers' => fn ($q) => $q->orderBy('position')]);

        /** @var User $user */
        $user = $request->user();
        $puedeVerIdentidad = $user->can('viewIdentity', $response);

        /*
         * RNF-AO-RES-004: consultar datos identificados queda registrado.
         *
         * Solo cuando de verdad los hay y de verdad se ven: auditar cada
         * apertura de una respuesta anonima llenaria el registro de ruido y
         * haria imposible encontrar los accesos que importan.
         */
        if ($response->hasIdentity() && $puedeVerIdentidad) {
            $audit->record('response.identity_viewed', $response, [], actor: $user);
        }

        return Inertia::render('Admin/Responses/Show', [
            'response' => [
                ...$this->row($response),

                'comment' => $response->comment,
                'invalidationReason' => $response->invalidation_reason,

                // RF-AO-RES-003: la version HISTORICA, no la actual.
                'answers' => $response->answers->map(fn (ResponseAnswer $a): array => [
                    'question' => $a->question_text,
                    'type' => $a->question_type->value,
                    'option' => $a->option_label,
                    'value' => $a->value,
                    'score' => $a->score,
                ])->all(),

                /*
                 * RF-AO-RES-004: se dice SI es identificada, sin revelar los
                 * datos a quien no puede verlos.
                 */
                'identityMode' => $response->identity_mode->value,
                'hasIdentity' => $response->hasIdentity(),
                'canViewIdentity' => $puedeVerIdentidad,

                'identity' => $response->hasIdentity() && $puedeVerIdentidad ? [
                    'name' => $response->respondent_name,
                    'email' => $response->respondent_email,
                    'phone' => $response->respondent_phone,
                ] : null,
            ],

            'invalidateUrl' => route('admin.responses.invalidate', $response),
            'backUrl' => route('admin.responses.index'),
        ]);
    }

    public function invalidate(
        InvalidateResponseRequest $request,
        Response $response,
        InvalidateResponse $invalidate,
    ): RedirectResponse {
        $this->authorize('invalidate', $response);

        /** @var User $user */
        $user = $request->user();

        $invalidate->execute($response, $user, $request->string('reason')->toString());

        return back()->with('status', __('interface.responses.invalidated'));
    }

    /** @return array<string, mixed> */
    private function row(Response $response): array
    {
        return [
            'ulid' => $response->ulid,
            'submittedAt' => $response->submitted_at?->format('Y-m-d H:i'),

            /*
             * Del SNAPSHOT, no de la relacion.
             *
             * Si la encuesta o la sucursal se renombraron despues, esta
             * respuesta se dio con el nombre de entonces. Leer la relacion
             * haria que el historico cambiara solo.
             */
            'surveyName' => $response->survey_name,
            'versionNumber' => $response->survey_version_number,
            'branchName' => $response->branch_name,
            'areaName' => $response->area_name,
            'channel' => $response->channel,

            'score' => $response->score,
            'maxScore' => $response->max_score,

            'isInvalidated' => $response->invalidated_at !== null,
            'url' => route('admin.responses.show', $response),
        ];
    }

    /** @return array<string, string|null> */
    private function filterValues(Request $request): array
    {
        return [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'survey' => $request->string('survey')->toString() ?: null,
            'branch' => $request->string('branch')->toString() ?: null,
            'channel' => $request->string('channel')->toString() ?: null,
            'validity' => $request->string('validity')->toString() ?: null,
        ];
    }

    /** @return list<array<string, string>> */
    private function surveys(int $organizationId): array
    {
        return Survey::query()
            ->forOrganization($organizationId)
            ->orderBy('name')
            ->get(['ulid', 'name'])
            ->map(fn (Survey $s): array => ['ulid' => $s->ulid, 'name' => $s->name])
            ->all();
    }

    /** @return list<array<string, string>> */
    private function branches(int $organizationId): array
    {
        return Branch::query()
            ->forOrganization($organizationId)
            ->orderBy('name')
            ->get(['ulid', 'name'])
            ->map(fn (Branch $b): array => ['ulid' => $b->ulid, 'name' => $b->name])
            ->all();
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
