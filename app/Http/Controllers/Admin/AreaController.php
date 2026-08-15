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
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Areas, anidadas bajo su sucursal. D-016.
 *
 * Las rutas llevan la sucursal delante —/admin/sucursales/{branch}/areas— y
 * scopeBindings() garantiza que un area de otra sucursal de la MISMA
 * organizacion tampoco se alcance por esta via.
 */
final class AreaController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, Branch $branch): InertiaResponse
    {
        $this->authorize('viewAny', Area::class);

        /*
         * DOS autorizaciones, no una.
         *
         * La primera dice que este rol puede ver areas; la segunda, que puede
         * ver ESTA sucursal. Sin la segunda, la ruta acepta la sucursal de
         * otra organizacion y responde 200 con su listado vacio: no filtra
         * datos, pero confirma que esa sucursal existe.
         */
        $this->authorize('view', $branch);

        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $areas = $branch->areas()
            ->search($search)
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'archived', fn ($query) => $query->where('status', 'archived'))
            ->withCount(['memberships', 'staffMembers'])
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Areas/Index', [
            'branch' => [
                'ulid' => $branch->ulid,
                'name' => $branch->name,
                'is_active' => $branch->isActive(),
            ],

            'areas' => $areas->through(fn (Area $area): array => [
                'ulid' => $area->ulid,
                'name' => $area->name,
                'code' => $area->code,
                'is_active' => $area->isActive(),
                'memberships_count' => $area->memberships_count,
                'staff_members_count' => $area->staff_members_count,
                'edit_url' => route('admin.areas.edit', [$branch, $area]),
                'archive_url' => route('admin.areas.archive', [$branch, $area]),
                'activate_url' => route('admin.areas.activate', [$branch, $area]),
            ]),

            'filters' => ['q' => $search, 'status' => $status],
            'createUrl' => route('admin.areas.create', $branch),
            'indexUrl' => route('admin.areas.index', $branch),
            'branchesUrl' => route('admin.branches.index'),
        ]);
    }

    public function create(Branch $branch): InertiaResponse
    {
        $this->authorize('create', Area::class);
        $this->authorize('view', $branch);

        return Inertia::render('Admin/Areas/Form', [
            'branch' => ['ulid' => $branch->ulid, 'name' => $branch->name],
            'area' => null,
            'action' => route('admin.areas.store', $branch),
            'cancelUrl' => route('admin.areas.index', $branch),
        ]);
    }

    public function store(AreaRequest $request, Branch $branch, SaveArea $save): RedirectResponse
    {
        $this->authorize('create', Area::class);
        $this->authorize('view', $branch);

        $save->create($branch, $request->safe()->only(['name', 'code']));

        return redirect()->route('admin.areas.index', $branch)
            ->with('status', __('interface.areas.created'));
    }

    public function edit(Branch $branch, Area $area): InertiaResponse
    {
        $this->authorize('update', $area);

        return Inertia::render('Admin/Areas/Form', [
            'branch' => ['ulid' => $branch->ulid, 'name' => $branch->name],
            'area' => ['ulid' => $area->ulid, 'name' => $area->name, 'code' => $area->code],
            'action' => route('admin.areas.update', [$branch, $area]),
            'cancelUrl' => route('admin.areas.index', $branch),
        ]);
    }

    public function update(AreaRequest $request, Branch $branch, Area $area, SaveArea $save): RedirectResponse
    {
        $this->authorize('update', $area);

        $save->update($area, $request->safe()->only(['name', 'code']));

        return redirect()->route('admin.areas.index', $branch)
            ->with('status', __('interface.areas.updated'));
    }

    public function archive(Branch $branch, Area $area, ArchiveArea $archive): RedirectResponse
    {
        $this->authorize('archive', $area);

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

        /*
         * Se PREGUNTA antes de actuar. ActivateArea no lanza para este caso:
         * expone isAllowedFor().
         *
         * La primera version de este metodo capturaba una excepcion que nunca
         * ocurre. La prueba lo cazo —"Session is missing expected key
         * [errors]"— pero el codigo parecia correcto leyendolo: un catch que
         * protege de algo que no pasa es de los mecanismos que no dan error y
         * no hacen nada.
         *
         * D-017: un area no se activa dentro de una sucursal archivada.
         * Permitirlo dejaria un area viva colgando de una sede cerrada.
         */
        if (! $activate->isAllowedFor($branch)) {
            return back()->withErrors(['area' => __('interface.areas.activate_blocked')]);
        }

        $activate->execute($area);

        return back()->with('status', __('interface.areas.activated'));
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
