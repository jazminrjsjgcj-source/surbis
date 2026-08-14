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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StaffMemberController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', StaffMember::class);

        return view('admin.people.person', [
            'person' => null,
            'branches' => $this->branches($request),
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

    public function edit(Request $request, StaffMember $staffMember): View
    {
        $this->authorize('update', $staffMember);

        return view('admin.people.person', [
            'person' => $staffMember,
            'branches' => $this->branches($request),
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

    public function accountForm(StaffMember $staffMember): View
    {
        $this->authorize('grantAccount', $staffMember);

        return view('admin.people.grant-account', ['person' => $staffMember]);
    }

    public function grantAccount(
        GrantAccountRequest $request,
        StaffMember $staffMember,
        GrantAccountToStaffMember $grant,
    ): RedirectResponse {
        $this->authorize('grantAccount', $staffMember);

        $grant->execute($staffMember, [
            'email' => (string) $request->string('email'),
            'role' => MembershipRole::from((string) $request->string('role')),
        ]);

        return redirect()->route('admin.people.index')
            ->with('status', __('interface.people.account_granted'));
    }

    /** @return Collection<int, Branch> */
    private function branches(Request $request)
    {
        return Branch::query()
            ->forOrganization($this->activeMembership($request)->organization_id)
            ->active()
            ->orderBy('name')
            ->get();
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
