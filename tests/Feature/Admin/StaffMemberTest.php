<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\StaffMember;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * RF-AO-COL-002 y P-016.
 */
final class StaffMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_registra_una_persona_sin_cuenta(): void
    {
        // Hasta ahora la lista las mostraba y no habia forma de crearlas
        // fuera del seeder.
        $admin = $this->admin();

        $this->post(route('admin.people.person.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Ventanilla',
            'employee_code' => 'EMP-900',
        ])->assertRedirect(route('admin.people.index'));

        $this->assertDatabaseHas('staff_members', [
            'organization_id' => $admin->organization_id,
            'employee_code' => 'EMP-900',
            'membership_id' => null,
        ]);
    }

    public function test_el_codigo_de_empleado_no_se_repite_en_la_organizacion(): void
    {
        $admin = $this->admin();
        StaffMember::factory()->for($admin->organization)->create(['employee_code' => 'EMP-1']);

        $this->post(route('admin.people.person.store'), [
            'first_name' => 'Otra',
            'last_name' => 'Persona',
            'employee_code' => 'EMP-1',
        ])->assertSessionHasErrors('employee_code');
    }

    public function test_el_codigo_de_empleado_puede_repetirse_entre_organizaciones(): void
    {
        $admin = $this->admin();
        StaffMember::factory()->create(['employee_code' => 'EMP-1']);

        $this->post(route('admin.people.person.store'), [
            'first_name' => 'Propia',
            'last_name' => 'Persona',
            'employee_code' => 'EMP-1',
        ])->assertRedirect(route('admin.people.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_el_codigo_de_empleado_es_opcional(): void
    {
        // El indice de la base es parcial: varias personas pueden no tenerlo.
        $admin = $this->admin();

        StaffMember::factory()->for($admin->organization)->create(['employee_code' => null]);

        $this->post(route('admin.people.person.store'), [
            'first_name' => 'Sin',
            'last_name' => 'Codigo',
            'employee_code' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, StaffMember::query()->whereNull('employee_code')->count());
    }

    public function test_no_se_edita_una_persona_de_otra_organizacion(): void
    {
        // RNF-GEN-005.
        $this->admin();
        $ajena = StaffMember::factory()->create();

        $this->get(route('admin.people.person.edit', $ajena))->assertForbidden();
    }

    public function test_no_se_asigna_una_sucursal_de_otra_organizacion(): void
    {
        $this->admin();
        $ajena = Branch::factory()->create();

        $this->post(route('admin.people.person.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Ventanilla',
            'branch_id' => $ajena->id,
        ])->assertSessionHasErrors('branch_id');
    }

    public function test_dar_cuenta_conserva_a_la_misma_persona(): void
    {
        /*
         * P-016, y el punto entero de la tarea: no se crea un registro nuevo.
         * Si se creara, el historial de evaluaciones de esos meses dejaria de
         * encontrarla.
         */
        Notification::fake();

        $admin = $this->admin();
        $branch = Branch::factory()->for($admin->organization)->create();

        $persona = StaffMember::factory()->for($admin->organization)->create([
            'first_name' => 'Maria',
            'last_name' => 'Ventanilla',
            'branch_id' => $branch->id,
        ]);

        $this->post(route('admin.people.person.account.store', $persona), [
            'email' => 'maria@example.test',
            'role' => 'collaborator',
        ])->assertRedirect(route('admin.people.index'));

        $persona->refresh();

        $this->assertNotNull($persona->membership_id);
        $this->assertSame(1, StaffMember::query()->count());

        // Y hereda la asignacion que ya tenia.
        $this->assertSame($branch->id, $persona->membership->branch_id);
    }

    public function test_la_cuenta_recien_dada_nace_suspendida(): void
    {
        // Igual que cualquier invitacion: se activa al definir la contrasena.
        Notification::fake();

        $admin = $this->admin();
        $persona = StaffMember::factory()->for($admin->organization)->create();

        $this->post(route('admin.people.person.account.store', $persona), [
            'email' => 'maria@example.test',
            'role' => 'collaborator',
        ]);

        $this->assertSame('suspended', $persona->fresh()->membership->status->value);

        Notification::assertSentTo($persona->fresh()->membership->user, ResetPassword::class);
    }

    public function test_no_se_da_cuenta_dos_veces(): void
    {
        // Un segundo intento crearia una membresia suelta y dejaria la
        // primera huerfana.
        Notification::fake();

        $admin = $this->admin();
        $persona = StaffMember::factory()->for($admin->organization)->create();

        $this->post(route('admin.people.person.account.store', $persona), [
            'email' => 'maria@example.test',
            'role' => 'collaborator',
        ]);

        $this->post(route('admin.people.person.account.store', $persona), [
            'email' => 'otra@example.test',
            'role' => 'collaborator',
        ])->assertForbidden();

        $this->assertSame(1, Membership::query()
            ->where('organization_id', $admin->organization_id)
            ->where('id', '!=', $admin->id)
            ->count());
    }

    public function test_archivar_una_persona_conserva_su_registro(): void
    {
        // RF-GEN-010: sus evaluaciones siguen apuntando a ella.
        $admin = $this->admin();
        $persona = StaffMember::factory()->for($admin->organization)->create();

        $this->post(route('admin.people.person.archive', $persona))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('archived', $persona->fresh()->status->value);
        $this->assertDatabaseCount('staff_members', 1);
    }

    public function test_la_lista_ofrece_registrar_y_dar_cuenta(): void
    {
        $admin = $this->admin();
        $persona = StaffMember::factory()->for($admin->organization)->create();

        $this->get(route('admin.people.index'))
            ->assertOk()
            ->assertSee(route('admin.people.person.create'), false)
            ->assertSee(route('admin.people.person.account', $persona), false);
    }

    private function admin(): Membership
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        return $membership;
    }
}
