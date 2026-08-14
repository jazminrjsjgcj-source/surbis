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
use Illuminate\View\View;

final class SurveyController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Survey::class);

        $membership = $this->activeMembership($request);
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $surveys = Survey::query()
            ->forOrganization($membership->organization_id)
            ->search($search)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            /*
             * RNF-AO-SUR-001: no cargar preguntas ni respuestas que no se
             * muestran. Aqui solo se traen la version publicada y el
             * borrador, que son las dos columnas de la tabla.
             */
            ->with(['publishedVersion', 'draft'])
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.surveys.index', [
            'surveys' => $surveys,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Survey::class);

        return view('admin.surveys.form', ['survey' => null]);
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

    public function edit(Survey $survey): View
    {
        $this->authorize('view', $survey);

        return view('admin.surveys.form', [
            'survey' => $survey->load(['versions' => fn ($query) => $query->orderByDesc('version_number')]),
        ]);
    }

    public function update(SurveyRequest $request, Survey $survey, UpdateSurveyGeneral $update): RedirectResponse
    {
        $this->authorize('update', $survey);

        $update->execute($survey, $request->safe()->only(['name', 'description']));

        return back()->with('status', __('interface.surveys.updated'));
    }

    /**
     * RF-AO-SUR-007. Abre un borrador nuevo en lugar de tocar lo publicado.
     */
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
