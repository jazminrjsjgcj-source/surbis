<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Identity\GrantAccountToStaffMember;
use App\Application\Organizations\ArchiveStaffMember;
use App\Application\Organizations\SaveStaffMember;
use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\StaffMember;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\GrantAccountRequest;
use App\Http\Requests\Admin\StaffMemberRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Personas evaluables: las que se evaluan y no usan el sistema.
 *
 * RF-AO-COL-007 a 010 y D-018.
 */
final class StaffMemberController extends Controller
{
    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', StaffMember::class);

        return Inertia::render('Admin/People/Person', [
            'person' => null,
            'branches' => $this->branchesWithAreas($request),
            'action' => route('admin.people.person.store'),
            'cancelUrl' => route('admin.people.index'),
        ]);
    }

    public function store(StaffMemberRequest $request, SaveStaffMember $save): RedirectResponse
    {
        $this->authorize('create', StaffMember::class);

        $save->create(
            $this->activeMembership($request)->organization,
            $request->safe()->only(['first_name', 'last_name', 'employee_code', 'branch_id', 'area_id']),
        );

        return redirect()->route('admin.people.index')
            ->with('status', __('interface.people.person_created'));
    }

    public function edit(Request $request, StaffMember $staffMember): InertiaResponse
    {
        $this->authorize('update', $staffMember);

        return Inertia::render('Admin/People/Person', [
            'person' => [
                'ulid' => $staffMember->ulid,
                'first_name' => $staffMember->first_name,
                'last_name' => $staffMember->last_name,
                'employee_code' => $staffMember->employee_code,
                'branch_id' => $staffMember->branch_id,
                'area_id' => $staffMember->area_id,
                'is_active' => $staffMember->isActive(),
                'archive_url' => route('admin.people.person.archive', $staffMember),
                'activate_url' => route('admin.people.person.activate', $staffMember),
            ],
            'branches' => $this->branchesWithAreas($request),
            'action' => route('admin.people.person.update', $staffMember),
            'cancelUrl' => route('admin.people.index'),
        ]);
    }

    public function update(StaffMemberRequest $request, StaffMember $staffMember, SaveStaffMember $save): RedirectResponse
    {
        $this->authorize('update', $staffMember);

        $save->update(
            $staffMember,
            $request->safe()->only(['first_name', 'last_name', 'employee_code', 'branch_id', 'area_id']),
        );

        return redirect()->route('admin.people.index')
            ->with('status', __('interface.people.person_updated'));
    }

    public function archive(StaffMember $staffMember, ArchiveStaffMember $archive): RedirectResponse
    {
        $this->authorize('archive', $staffMember);

        $archive->archive($staffMember);

        return back()->with('status', __('interface.people.person_archived'));
    }

    public function activate(StaffMember $staffMember, ArchiveStaffMember $archive): RedirectResponse
    {
        $this->authorize('archive', $staffMember);

        $archive->activate($staffMember);

        return back()->with('status', __('interface.people.person_activated'));
    }

    public function accountForm(StaffMember $staffMember): InertiaResponse
    {
        $this->authorize('grantAccount', $staffMember);

        return Inertia::render('Admin/People/GrantAccount', [
            'person' => [
                'ulid' => $staffMember->ulid,
                'name' => trim($staffMember->first_name.' '.$staffMember->last_name),
            ],
            'roles' => array_map(fn (MembershipRole $r): string => $r->value, MembershipRole::cases()),
            'action' => route('admin.people.person.account.store', $staffMember),
            'cancelUrl' => route('admin.people.index'),
        ]);
    }

    public function grantAccount(
        GrantAccountRequest $request,
        StaffMember $staffMember,
        GrantAccountToStaffMember $grant,
    ): RedirectResponse {
        $this->authorize('grantAccount', $staffMember);

        // D-021: vincula membership_id a la persona existente. NO crea un
        // registro nuevo, asi que sus evaluaciones anteriores se conservan.
        $grant->execute($staffMember, [
            'email' => (string) $request->string('email'),
            'role' => MembershipRole::from((string) $request->string('role')),
        ]);

        return redirect()->route('admin.people.index')
            ->with('status', __('interface.people.account_granted'));
    }

    /** @return list<array<string, mixed>> */
    private function branchesWithAreas(Request $request): array
    {
        return Branch::query()
            ->forOrganization($this->activeMembership($request)->organization_id)
            ->active()
            ->with(['areas' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'areas' => $branch->areas->map(fn ($area): array => [
                    'id' => $area->id,
                    'name' => $area->name,
                ])->all(),
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
