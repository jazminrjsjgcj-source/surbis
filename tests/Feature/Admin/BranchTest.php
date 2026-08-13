<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RF-AO-BRA-001, 002 y 004 · RNF-AO-BRA-001 y 002 · RNF-GEN-005.
 */
final class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_solo_muestra_sucursales_de_la_organizacion_activa(): void
    {
        // RNF-GEN-005. La prueba que da sentido a toda la fase.
        $membership = $this->admin();

        Branch::factory()->for($membership->organization)->create(['name' => 'Sucursal propia']);
        Branch::factory()->create(['name' => 'Sucursal ajena']);

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee('Sucursal propia')
            ->assertDontSee('Sucursal ajena');
    }

    public function test_no_se_puede_editar_una_sucursal_ajena(): void
    {
        $this->admin();

        $ajena = Branch::factory()->create();

        $this->get(route('admin.branches.edit', $ajena))->assertForbidden();
        $this->put(route('admin.branches.update', $ajena), [
            'name' => 'Secuestrada',
            'code' => 'X1',
        ])->assertForbidden();

        $this->assertNotSame('Secuestrada', $ajena->fresh()->name);
    }

    public function test_un_colaborador_no_entra_al_listado(): void
    {
        // RA-002 y RA-005: ocultar el enlace no es autorizar.
        $membership = Membership::factory()->collaborator()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.branches.index'))->assertForbidden();
    }

    public function test_se_crea_una_sucursal(): void
    {
        $membership = $this->admin();

        $this->post(route('admin.branches.store'), [
            'name' => 'Oficina Centro',
            'code' => 'CENTRO',
        ])->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseHas('branches', [
            'organization_id' => $membership->organization_id,
            'code' => 'CENTRO',
        ]);
    }

    public function test_dos_organizaciones_pueden_usar_el_mismo_codigo(): void
    {
        // RNF-AO-BRA-002. El error clasico de multiempresa.
        $membership = $this->admin();

        Branch::factory()->create(['code' => 'CENTRO']);

        $this->post(route('admin.branches.store'), [
            'name' => 'Oficina Centro',
            'code' => 'CENTRO',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Branch::query()->where('code', 'CENTRO')->count());
    }

    public function test_el_codigo_no_se_repite_dentro_de_la_organizacion(): void
    {
        $membership = $this->admin();

        Branch::factory()->for($membership->organization)->create(['code' => 'CENTRO']);

        $this->post(route('admin.branches.store'), [
            'name' => 'Otra',
            'code' => 'CENTRO',
        ])->assertSessionHasErrors('code');
    }

    public function test_editar_una_sucursal_no_choca_con_su_propio_codigo(): void
    {
        // El fallo clasico de la regla unique al editar: la sucursal choca
        // consigo misma y el usuario no puede cambiar solo el nombre.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create(['code' => 'CENTRO']);

        $this->put(route('admin.branches.update', $branch), [
            'name' => 'Nombre nuevo',
            'code' => 'CENTRO',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nombre nuevo', $branch->fresh()->name);
    }

    public function test_archivar_conserva_la_sucursal(): void
    {
        // RF-AO-BRA-004 y RF-GEN-010: archivar no es borrar.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();

        $this->post(route('admin.branches.archive', $branch))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'status' => 'archived',
        ]);

        $this->assertNotNull($branch->fresh()->archived_at);
    }

    public function test_no_se_archiva_una_sucursal_con_colaboradores_asignados(): void
    {
        // RNF-AO-BRA-001: advertencia y resolucion explicita.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();

        Membership::factory()
            ->for($membership->organization)
            ->create(['branch_id' => $branch->id]);

        $this->post(route('admin.branches.archive', $branch))
            ->assertSessionHasErrors('branch');

        $this->assertSame('active', $branch->fresh()->status->value);
    }

    public function test_no_se_archiva_una_sucursal_con_areas_activas(): void
    {
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();

        Area::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $this->post(route('admin.branches.archive', $branch))
            ->assertSessionHasErrors('branch');
    }

    public function test_una_sucursal_archivada_se_puede_reactivar(): void
    {
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->archived()->create();

        $this->post(route('admin.branches.activate', $branch))
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $branch->fresh()->status->value);
        $this->assertNull($branch->fresh()->archived_at);
    }

    public function test_la_busqueda_no_distingue_mayusculas(): void
    {
        // En PostgreSQL, LIKE si distingue. Buscar "centro" no encontraria
        // "CENTRO" y el usuario concluiria que la sucursal no existe.
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->create([
            'name' => 'Oficina CENTRO',
            'code' => 'C1',
        ]);

        $this->get(route('admin.branches.index', ['q' => 'centro']))
            ->assertOk()
            ->assertSee('Oficina CENTRO');
    }

    public function test_la_busqueda_no_se_escapa_de_la_organizacion(): void
    {
        // Un filtro que ignore el aislamiento es una fuga con buscador.
        $membership = $this->admin();
        Branch::factory()->create(['name' => 'Ajena buscable']);

        $this->get(route('admin.branches.index', ['q' => 'Ajena']))
            ->assertOk()
            ->assertDontSee('Ajena buscable');
    }

    public function test_los_cambios_quedan_auditados(): void
    {
        // RNF-AO-COL-001.
        $membership = $this->admin();

        $this->post(route('admin.branches.store'), [
            'name' => 'Oficina Centro',
            'code' => 'CENTRO',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $membership->organization_id,
            'user_id' => $membership->user_id,
            'action' => 'branch.created',
        ]);
    }

    public function test_el_listado_no_dispara_una_consulta_por_fila(): void
    {
        /*
         * RNF-GEN-010. Con withCount son dos consultas pase lo que pase; sin
         * el, contar areas y colaboradores dentro del bucle son dos por fila.
         *
         * El umbral es generoso a proposito: lo que vigila no es el numero
         * exacto, es que NO crezca con la cantidad de sucursales.
         */
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->count(15)->create();

        $consultas = 0;
        DB::listen(function () use (&$consultas): void {
            $consultas++;
        });

        $this->get(route('admin.branches.index'))->assertOk();

        $this->assertLessThan(20, $consultas, "El listado hizo {$consultas} consultas.");
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
