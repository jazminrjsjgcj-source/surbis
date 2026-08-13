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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BranchController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $membership = $this->activeMembership($request);
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        /** @var LengthAwarePaginator<int, Branch> $branches */
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

        return view('admin.branches.index', [
            'branches' => $branches,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('admin.branches.form', ['branch' => null]);
    }

    public function store(BranchRequest $request, SaveBranch $save): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $membership = $this->activeMembership($request);

        $save->create($membership->organization, $request->safe()->only(['name', 'code']));

        return redirect()->route('admin.branches.index')
            ->with('status', __('interface.branches.created'));
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('admin.branches.form', ['branch' => $branch]);
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
