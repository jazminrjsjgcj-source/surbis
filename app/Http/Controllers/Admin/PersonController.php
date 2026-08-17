<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Identity\Exceptions\LastAdministrator;
use App\Application\Identity\InviteMember;
use App\Application\Identity\ManageMembership;
use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\PersonRow;
use App\Domain\Identity\SecondFactorAvailability;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\StaffMember;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Requests\Admin\AssignPersonRequest;
use App\Http\Requests\Admin\InviteMemberRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Membership::class);

        $membership = $this->activeMembership($request);
        $search = $request->string('q')->toString();
        $filter = $request->string('type')->toString();

        $rows = $this->rows($membership->organization_id, $search, $filter);

        return Inertia::render('Admin/People/Index', [
            'rows' => $rows->map(fn (PersonRow $row): array => $this->serialize($row))->all(),

            'filters' => ['q' => $search, 'type' => $filter],

            // Para el desplegable de asignacion, que vive en la propia fila.
            'branches' => $this->branchesWithAreas($membership->organization_id),

            'inviteUrl' => route('admin.people.create'),
            'personUrl' => route('admin.people.person.create'),
            'indexUrl' => route('admin.people.index'),
        ]);
    }

    public function create(Request $request, SecondFactorAvailability $mail): InertiaResponse
    {
        $this->authorize('create', Membership::class);

        $membership = $this->activeMembership($request);

        return Inertia::render('Admin/People/Invite', [
            'branches' => $this->branchesWithAreas($membership->organization_id),
            /*
             * Si se puede invitar por correo.
             *
             * Cuando no, la pantalla pide una contrasena: sin correo el
             * enlace de invitacion no llega a nadie y la persona no podria
             * entrar nunca.
             */
            'canInvite' => $mail->isAvailable(),

            'roles' => array_map(fn (MembershipRole $r): string => $r->value, MembershipRole::cases()),
            'action' => route('admin.people.store'),
            'cancelUrl' => route('admin.people.index'),
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

            /*
             * La contrasena solo llega cuando no hay correo: el Form Request
             * la excluye del todo si el correo esta configurado, asi que aqui
             * no hace falta volver a comprobarlo.
             */
            /*
             * La contrasena solo cuando NO hay correo. Se comprueba aqui
             * ademas del Form Request.
             *
             * excludeIf del Request no basta: si alguna otra via llama a este
             * controlador, la contrasena entraria. Y con correo configurado
             * habria dos formas de crear cuentas, siendo la mas insegura la
             * mas comoda.
             */
            ...($request->filled('password') && ! app(SecondFactorAvailability::class)->isAvailable()
                ? ['password' => $request->string('password')->toString()]
                : []),
        ]);

        return redirect()->route('admin.people.index')->with(
            'status',
            $request->filled('password')
                ? __('interface.people.created_with_password')
                : __('interface.people.invited'),
        );
    }

    public function suspend(Membership $membership, ManageMembership $manage): RedirectResponse
    {
        $this->authorize('suspend', $membership);

        try {
            $manage->suspend($membership);
        } catch (LastAdministrator) {
            // P-017 y D-020: no se puede dejar la organizacion sin nadie que
            // la administre.
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

    /**
     * Una fila de la lista, lista para pintar.
     *
     * Aqui se decide QUE puede hacerse con cada persona, y no en el
     * componente: si React dedujera por su cuenta que una persona sin cuenta
     * puede recibir una, su criterio y el de las Policies divergirian, y la
     * pantalla ofreceria acciones que el servidor rechaza.
     *
     * @return array<string, mixed>
     */
    private function serialize(PersonRow $row): array
    {
        $membership = $row->membership;
        $staff = $row->staffMember;

        return [
            'key' => $row->key,
            'name' => $row->name,
            'email' => $row->email,
            'branch' => $row->branchName,
            'area' => $row->areaName,

            'has_account' => $row->hasAccount(),
            'is_evaluated' => $row->isEvaluated(),

            'role' => $membership?->role->value,
            'membership_status' => $membership?->status->value,
            'staff_status' => $staff?->status->value,

            'branch_id' => $membership?->branch_id ?? $staff?->branch_id,
            'area_id' => $membership?->area_id ?? $staff?->area_id,

            'suspend_url' => $membership === null ? null : route('admin.people.suspend', $membership),
            'activate_url' => $membership === null ? null : route('admin.people.activate', $membership),
            'assign_url' => $membership === null ? null : route('admin.people.assign', $membership),

            'edit_url' => $staff === null ? null : route('admin.people.person.edit', $staff),
            'account_url' => $staff === null || $row->hasAccount()
                ? null
                : route('admin.people.person.account', $staff),
        ];
    }

    /**
     * Sucursales activas con sus areas, para los desplegables encadenados.
     *
     * @return list<array<string, mixed>>
     */
    private function branchesWithAreas(int $organizationId): array
    {
        return Branch::query()
            ->forOrganization($organizationId)
            ->active()
            // with() y no una consulta por sucursal dentro del bucle.
            // RNF-GEN-010.
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
