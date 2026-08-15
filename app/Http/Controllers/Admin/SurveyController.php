<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Surveys\ArchiveSurvey;
use App\Application\Surveys\CreateSurvey;
use App\Application\Surveys\OpenDraft;
use App\Application\Surveys\UpdateSurveyGeneral;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Surveys\Models\Survey;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\SurveyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class SurveyController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Survey::class);

        $membership = $this->activeMembership($request);
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $surveys = Survey::query()
            ->forOrganization($membership->organization_id)
            ->search($search)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            // RNF-AO-SUR-001: no cargar preguntas ni respuestas que no se
            // muestran. Solo la version publicada y el borrador.
            ->with(['publishedVersion', 'draft'])
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Surveys/Index', [
            'surveys' => $surveys->through(fn (Survey $survey): array => [
                'ulid' => $survey->ulid,
                'name' => $survey->name,
                'description' => $survey->description,
                'status' => $survey->status->value,
                'published_version' => $survey->publishedVersion?->version_number,
                'draft_version' => $survey->draft?->version_number,
                'updated_at' => $survey->updated_at?->diffForHumans(),
                'edit_url' => route('admin.surveys.edit', $survey),
                'archive_url' => route('admin.surveys.archive', $survey),
                'activate_url' => route('admin.surveys.activate', $survey),
            ]),

            'filters' => ['q' => $search, 'status' => $status],
            'createUrl' => route('admin.surveys.create'),
            'indexUrl' => route('admin.surveys.index'),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', Survey::class);

        return Inertia::render('Admin/Surveys/Form', [
            'survey' => null,
            'versions' => [],
            'action' => route('admin.surveys.store'),
            'cancelUrl' => route('admin.surveys.index'),
            'builderUrl' => null,
            'settingsUrl' => null,
            'draftUrl' => null,
        ]);
    }

    public function store(SurveyRequest $request, CreateSurvey $create): RedirectResponse
    {
        $this->authorize('create', Survey::class);

        /** @var User $user */
        $user = $request->user();

        $survey = $create->execute(
            $this->activeMembership($request)->organization,
            $user,
            $request->safe()->only(['name', 'description']),
        );

        return redirect()->route('admin.surveys.edit', $survey)
            ->with('status', __('interface.surveys.created'));
    }

    public function edit(Survey $survey): InertiaResponse
    {
        $this->authorize('view', $survey);

        $survey->load(['versions' => fn ($query) => $query->orderByDesc('version_number')]);

        return Inertia::render('Admin/Surveys/Form', [
            'survey' => [
                'ulid' => $survey->ulid,
                'name' => $survey->name,
                'description' => $survey->description,
                'has_draft' => $survey->draft()->exists(),
            ],

            'versions' => $survey->versions->map(fn ($version): array => [
                'number' => $version->version_number,
                'status' => $version->status->value,
                'date' => $version->published_at?->diffForHumans()
                    ?? $version->created_at?->diffForHumans(),
            ])->all(),

            'action' => route('admin.surveys.update', $survey),
            'cancelUrl' => route('admin.surveys.index'),

            // Las dos puertas de la encuesta. El constructor llevo tres tareas
            // construido sin enlace desde aqui: solo se alcanzaba escribiendo
            // la URL.
            'builderUrl' => route('admin.surveys.builder', $survey),
            'settingsUrl' => route('admin.surveys.settings', $survey),
            'draftUrl' => route('admin.surveys.draft', $survey),
        ]);
    }

    public function update(SurveyRequest $request, Survey $survey, UpdateSurveyGeneral $update): RedirectResponse
    {
        $this->authorize('update', $survey);

        $update->execute($survey, $request->safe()->only(['name', 'description']));

        return back()->with('status', __('interface.surveys.saved'));
    }

    /** RF-AO-SUR-007: abre un borrador nuevo en lugar de tocar lo publicado. */
    public function draft(Survey $survey, OpenDraft $open): RedirectResponse
    {
        $this->authorize('update', $survey);

        $open->execute($survey);

        return back()->with('status', __('interface.surveys.draft_opened'));
    }

    public function archive(Survey $survey, ArchiveSurvey $archive): RedirectResponse
    {
        $this->authorize('archive', $survey);

        $archive->archive($survey);

        return back()->with('status', __('interface.surveys.archived'));
    }

    public function activate(Survey $survey, ArchiveSurvey $archive): RedirectResponse
    {
        $this->authorize('archive', $survey);

        $archive->activate($survey);

        return back()->with('status', __('interface.surveys.activated'));
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
