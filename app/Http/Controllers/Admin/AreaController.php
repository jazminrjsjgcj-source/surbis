<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Organizations\ActivateArea;
use App\Application\Organizations\ArchiveArea;
use App\Application\Organizations\Exceptions\HasActiveReferences;
use App\Application\Organizations\SaveArea;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AreaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Las areas viven anidadas bajo su sucursal.
 *
 * REQ las pone en la misma pantalla que las sucursales, pero un area
 * pertenece a UNA sucursal: una lista plana obligaria a una columna
 * "sucursal" y a filtrar por ella. Anidadas, el conteo de areas de la tabla
 * de sucursales deja de ser un numero muerto y pasa a ser la entrada.
 */
final class AreaController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, Branch $branch): View
    {
        $this->authorize('viewAny', Area::class);
        $this->authorize('view', $branch);

        $search = $request->string('q')->toString();

        $areas = Area::query()
            ->forBranch($branch)
            ->search($search)
            ->withCount(['memberships', 'staffMembers'])
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.areas.index', [
            'branch' => $branch,
            'areas' => $areas,
            'search' => $search,
        ]);
    }

    public function create(Branch $branch): View
    {
        $this->authorize('create', Area::class);
        $this->authorize('view', $branch);

        return view('admin.areas.form', ['branch' => $branch, 'area' => null]);
    }

    public function store(AreaRequest $request, Branch $branch, SaveArea $save): RedirectResponse
    {
        $this->authorize('create', Area::class);
        $this->authorize('view', $branch);

        $save->create($branch, $request->safe()->only(['name', 'code']));

        return redirect()->route('admin.areas.index', $branch)
            ->with('status', __('interface.areas.created'));
    }

    public function edit(Branch $branch, Area $area): View
    {
        $this->authorize('update', $area);
        $this->ensureBelongsTo($branch, $area);

        return view('admin.areas.form', ['branch' => $branch, 'area' => $area]);
    }

    public function update(AreaRequest $request, Branch $branch, Area $area, SaveArea $save): RedirectResponse
    {
        $this->authorize('update', $area);
        $this->ensureBelongsTo($branch, $area);

        $save->update($area, $request->safe()->only(['name', 'code']));

        return redirect()->route('admin.areas.index', $branch)
            ->with('status', __('interface.areas.updated'));
    }

    public function archive(Branch $branch, Area $area, ArchiveArea $archive): RedirectResponse
    {
        $this->authorize('archive', $area);
        $this->ensureBelongsTo($branch, $area);

        try {
            $archive->execute($area);
        } catch (HasActiveReferences $blocked) {
            return back()->withErrors([
                'area' => __('interface.areas.archive_blocked', [
                    'references' => $this->describeReferences($blocked->references),
                ]),
            ]);
        }

        return back()->with('status', __('interface.areas.archived'));
    }

    public function activate(Branch $branch, Area $area, ActivateArea $activate): RedirectResponse
    {
        $this->authorize('archive', $area);
        $this->ensureBelongsTo($branch, $area);

        // Un area activa dentro de una sucursal archivada seria un sitio al
        // que asignar gente en una sede que ya no opera.
        if (! $activate->isAllowedFor($branch)) {
            return back()->withErrors([
                'area' => __('interface.areas.activate_blocked'),
            ]);
        }

        $activate->execute($area);

        return back()->with('status', __('interface.areas.activated'));
    }

    /**
     * Una area de OTRA sucursal, aunque sea de la misma organizacion, no se
     * edita desde esta. Sin esto, la URL /sucursales/A/areas/B/editar
     * funcionaria con B colgando de otra sede y el usuario editaria algo que
     * no esta viendo.
     */
    private function ensureBelongsTo(Branch $branch, Area $area): void
    {
        abort_unless($area->branch_id === $branch->id, 404);
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
}
