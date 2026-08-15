<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Organizations\ActivateBranch;
use App\Application\Organizations\ArchiveBranch;
use App\Application\Organizations\Exceptions\HasActiveReferences;
use App\Application\Organizations\SaveBranch;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\BranchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class BranchController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Branch::class);

        $membership = $this->activeMembership($request);
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $branches = Branch::query()
            ->forOrganization($membership->organization_id)
            ->search($search)
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'archived', fn ($query) => $query->where('status', 'archived'))
            // withCount y no un conteo por fila: con veinte sucursales en
            // pantalla, contar dentro del bucle son veinte consultas extra.
            // RNF-GEN-010.
            ->withCount(['areas', 'memberships'])
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Branches/Index', [
            /*
             * through() transforma cada fila conservando la estructura de
             * paginacion. Sin el habria que reconstruir los enlaces a mano, y
             * ahi es donde se pierde withQueryString y los filtros dejan de
             * sobrevivir al cambio de pagina.
             */
            'branches' => $branches->through(fn (Branch $branch): array => [
                'ulid' => $branch->ulid,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_active' => $branch->isActive(),
                'areas_count' => $branch->areas_count,
                'memberships_count' => $branch->memberships_count,
                'edit_url' => route('admin.branches.edit', $branch),
                'areas_url' => route('admin.areas.index', $branch),
                'archive_url' => route('admin.branches.archive', $branch),
                'activate_url' => route('admin.branches.activate', $branch),
            ]),

            'filters' => ['q' => $search, 'status' => $status],
            'createUrl' => route('admin.branches.create'),
            'indexUrl' => route('admin.branches.index'),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', Branch::class);

        return Inertia::render('Admin/Branches/Form', [
            'branch' => null,
            'action' => route('admin.branches.store'),
            'cancelUrl' => route('admin.branches.index'),
        ]);
    }

    public function store(BranchRequest $request, SaveBranch $save): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $membership = $this->activeMembership($request);

        $save->create($membership->organization, $request->safe()->only(['name', 'code']));

        return redirect()->route('admin.branches.index')
            ->with('status', __('interface.branches.created'));
    }

    public function edit(Branch $branch): InertiaResponse
    {
        $this->authorize('update', $branch);

        return Inertia::render('Admin/Branches/Form', [
            'branch' => [
                'ulid' => $branch->ulid,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'action' => route('admin.branches.update', $branch),
            'cancelUrl' => route('admin.branches.index'),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch, SaveBranch $save): RedirectResponse
    {
        $this->authorize('update', $branch);

        $save->update($branch, $request->safe()->only(['name', 'code']));

        return redirect()->route('admin.branches.index')
            ->with('status', __('interface.branches.updated'));
    }

    public function archive(Branch $branch, ArchiveBranch $archive): RedirectResponse
    {
        $this->authorize('archive', $branch);

        try {
            $archive->execute($branch);
        } catch (HasActiveReferences $blocked) {
            // El mensaje dice CUANTAS cosas hay que mover y de que tipo.
            // RNF-AO-BRA-001 pide advertencia y resolucion explicita, y una
            // advertencia sin el detalle obliga a buscarlo a mano.
            return back()->withErrors([
                'branch' => __('interface.branches.archive_blocked', [
                    'references' => $this->describeReferences($blocked->references),
                ]),
            ]);
        }

        return back()->with('status', __('interface.branches.archived'));
    }

    public function activate(Branch $branch, ActivateBranch $activate): RedirectResponse
    {
        $this->authorize('archive', $branch);

        $activate->execute($branch);

        return back()->with('status', __('interface.branches.activated'));
    }

    /** @param array<string, int> $references */
    private function describeReferences(array $references): string
    {
        $parts = [];

        foreach ($references as $type => $count) {
            $parts[] = trans_choice('interface.branches.reference.'.$type, $count, ['count' => $count]);
        }

        return implode(', ', $parts);
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
