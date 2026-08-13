<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Identity\Exceptions\LastAdministrator;
use App\Application\Identity\InviteMember;
use App\Application\Identity\ManageMembership;
use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\PersonRow;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\StaffMember;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\AssignPersonRequest;
use App\Http\Requests\Admin\InviteMemberRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Usuarios y colaboradores. RF-AO-COL-001 a 006.
 *
 * La lista mezcla dos entidades (P-014). No se paginan en la base porque no
 * hay una consulta que las una: son dos tablas sin relacion directa. Se traen
 * las dos y se ordenan en memoria.
 *
 * Eso es aceptable con los volumenes de una organizacion municipal —decenas o
 * cientos de personas, no millones—. Si algun dia deja de serlo, la salida es
 * una vista de PostgreSQL que las una, no paginar a mano. Queda dicho aqui
 * para que quien lo lea sepa que es una decision y no un descuido.
 */
final class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Membership::class);

        $membership = $this->activeMembership($request);
        $search = $request->string('q')->toString();
        $filter = $request->string('type')->toString();

        $rows = $this->rows($membership->organization_id, $search, $filter);

        return view('admin.people.index', [
            'rows' => $rows,
            'search' => $search,
            'filter' => $filter,
            'branches' => Branch::query()
                ->forOrganization($membership->organization_id)
                ->active()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Membership::class);

        $membership = $this->activeMembership($request);

        return view('admin.people.invite', [
            'branches' => Branch::query()
                ->forOrganization($membership->organization_id)
                ->active()
                ->with('areas')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(InviteMemberRequest $request, InviteMember $invite): RedirectResponse
    {
        $this->authorize('create', Membership::class);

        $membership = $this->activeMembership($request);

        $invite->execute($membership->organization, [
            'name' => (string) $request->string('name'),
            'email' => (string) $request->string('email'),
            'role' => MembershipRole::from((string) $request->string('role')),
            'branch_id' => $request->integer('branch_id') ?: null,
            'area_id' => $request->integer('area_id') ?: null,
        ]);

        return redirect()->route('admin.people.index')
            ->with('status', __('interface.people.invited'));
    }

    public function suspend(Membership $membership, ManageMembership $manage): RedirectResponse
    {
        $this->authorize('suspend', $membership);

        try {
            $manage->suspend($membership);
        } catch (LastAdministrator) {
            return back()->withErrors(['membership' => __('interface.people.last_admin')]);
        }

        return back()->with('status', __('interface.people.suspended'));
    }

    public function activate(Membership $membership, ManageMembership $manage): RedirectResponse
    {
        $this->authorize('suspend', $membership);

        $manage->activate($membership);

        return back()->with('status', __('interface.people.activated'));
    }

    public function assign(
        AssignPersonRequest $request,
        Membership $membership,
        ManageMembership $manage,
    ): RedirectResponse {
        $this->authorize('update', $membership);

        $manage->assign($membership, [
            'branch_id' => $request->integer('branch_id') ?: null,
            'area_id' => $request->integer('area_id') ?: null,
        ]);

        return back()->with('status', __('interface.people.assigned'));
    }

    /** @return Collection<int, PersonRow> */
    private function rows(int $organizationId, string $search, string $filter): Collection
    {
        $rows = collect();

        if ($filter !== 'evaluated') {
            $memberships = Membership::query()
                ->where('organization_id', $organizationId)
                ->search($search)
                // with() y no consultas dentro del bucle: sin esto son cuatro
                // consultas por fila. RNF-GEN-010.
                ->with(['user', 'branch', 'area', 'staffMember'])
                ->get();

            $rows = $rows->concat($memberships->map(
                fn (Membership $m): PersonRow => PersonRow::fromMembership($m)
            ));
        }

        if ($filter !== 'accounts') {
            $staff = StaffMember::query()
                ->forOrganization($organizationId)
                ->withoutAccount()
                ->search($search)
                ->with(['branch', 'area'])
                ->get();

            $rows = $rows->concat($staff->map(
                fn (StaffMember $s): PersonRow => PersonRow::fromStaffMember($s)
            ));
        }

        return $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
