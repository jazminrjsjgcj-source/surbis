<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF-AO-BRA-001 y 004 · RNF-AO-BRA-001 · RNF-GEN-005.
 */
final class AreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_solo_muestra_areas_de_esa_sucursal(): void
    {
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();
        $otra = Branch::factory()->for($membership->organization)->create();

        Area::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
            'name' => 'Ventanilla propia',
        ]);
        Area::factory()->for($otra)->create([
            'organization_id' => $membership->organization_id,
            'name' => 'Ventanilla de otra sede',
        ]);

        $this->get(route('admin.areas.index', $branch))
            ->assertOk()
            ->assertSee('Ventanilla propia')
            ->assertDontSee('Ventanilla de otra sede');
    }

    public function test_no_se_ven_areas_de_otra_organizacion(): void
    {
        // RNF-GEN-005.
        $this->admin();

        $ajena = Branch::factory()->create();

        $this->get(route('admin.areas.index', $ajena))->assertForbidden();
    }

    public function test_se_crea_un_area_heredando_la_organizacion_de_su_sucursal(): void
    {
        // La organizacion no llega del formulario. Si viniera de fuera, un
        // area podria apuntar a una organizacion distinta de la de su sede.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();

        $this->post(route('admin.areas.store', $branch), [
            'name' => 'Ventanilla 1',
            'code' => 'V1',
        ])->assertRedirect(route('admin.areas.index', $branch));

        $this->assertDatabaseHas('areas', [
            'branch_id' => $branch->id,
            'organization_id' => $membership->organization_id,
            'code' => 'V1',
        ]);
    }

    public function test_dos_sucursales_pueden_tener_el_mismo_codigo_de_area(): void
    {
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();
        $otra = Branch::factory()->for($membership->organization)->create();

        Area::factory()->for($otra)->create([
            'organization_id' => $membership->organization_id,
            'code' => 'VENTANILLA',
        ]);

        $this->post(route('admin.areas.store', $branch), [
            'name' => 'Ventanilla',
            'code' => 'VENTANILLA',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Area::query()->where('code', 'VENTANILLA')->count());
    }

    public function test_el_codigo_no_se_repite_dentro_de_la_sucursal(): void
    {
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();

        Area::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
            'code' => 'V1',
        ]);

        $this->post(route('admin.areas.store', $branch), [
            'name' => 'Otra',
            'code' => 'V1',
        ])->assertSessionHasErrors('code');
    }

    public function test_un_area_de_otra_sucursal_no_se_edita_desde_esta(): void
    {
        // Sin la comprobacion, la URL /sucursales/A/areas/B/editar
        // funcionaria con B colgando de otra sede y el usuario editaria algo
        // que no esta viendo.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();
        $otra = Branch::factory()->for($membership->organization)->create();

        $area = Area::factory()->for($otra)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $this->get(route('admin.areas.edit', [$branch, $area]))->assertNotFound();
    }

    public function test_no_se_archiva_un_area_con_colaboradores(): void
    {
        // RNF-AO-BRA-001, la misma regla que en sucursales.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();
        $area = Area::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
        ]);

        Membership::factory()->for($membership->organization)->create([
            'branch_id' => $branch->id,
            'area_id' => $area->id,
        ]);

        $this->post(route('admin.areas.archive', [$branch, $area]))
            ->assertSessionHasErrors('area');

        $this->assertSame('active', $area->fresh()->status->value);
    }

    public function test_un_area_sin_referencias_se_archiva(): void
    {
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();
        $area = Area::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $this->post(route('admin.areas.archive', [$branch, $area]))
            ->assertSessionHasNoErrors();

        $this->assertSame('archived', $area->fresh()->status->value);
    }

    public function test_no_se_activa_un_area_de_una_sucursal_archivada(): void
    {
        // Seria un sitio al que asignar gente en una sede que ya no opera.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->archived()->create();
        $area = Area::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
            'status' => 'archived',
        ]);

        $this->post(route('admin.areas.activate', [$branch, $area]))
            ->assertSessionHasErrors('area');

        $this->assertSame('archived', $area->fresh()->status->value);
    }

    public function test_archivar_la_sucursal_exige_archivar_antes_sus_areas(): void
    {
        // Decision del area usuaria: de dentro hacia fuera, sin cascada
        // silenciosa.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();
        $area = Area::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $this->post(route('admin.branches.archive', $branch))
            ->assertSessionHasErrors('branch');

        $this->post(route('admin.areas.archive', [$branch, $area]))
            ->assertSessionHasNoErrors();

        $this->post(route('admin.branches.archive', $branch))
            ->assertSessionHasNoErrors();

        $this->assertSame('archived', $branch->fresh()->status->value);
    }

    public function test_el_conteo_de_areas_enlaza_a_su_pantalla(): void
    {
        // Antes era un numero que no llevaba a ningun sitio.
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee(route('admin.areas.index', $branch), false);
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
