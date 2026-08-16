<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * El punto de entrada del administrador, ahora en React.
 *
 * Las aserciones son sobre PROPS y no sobre marcado. La version anterior
 * comprobaba assertSee de las rutas, y con Inertia eso pasaria igual porque
 * las URLs aparecen dentro del JSON de props: verde por el motivo
 * equivocado, que es peor que rojo.
 */
final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_se_sirve_con_inertia(): void
    {
        $this->admin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Dashboard'));
    }

    public function test_el_panel_lleva_a_las_secciones_que_existen(): void
    {
        // Las URLs viajan como props: React no conoce las rutas nombradas de
        // Laravel, y escribirlas en el componente crearia una segunda verdad.
        $this->admin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('branchesUrl', route('admin.branches.index'))
                ->where('peopleUrl', route('admin.people.index'))
                ->where('surveysUrl', route('admin.surveys.index'))
            );
    }

    public function test_la_navegacion_viaja_como_prop_compartida(): void
    {
        /*
         * Cuatro entradas: panel, sucursales, personas y encuestas. Ni una
         * mas.
         *
         * Un menu que promete pantallas que no existen es un mecanismo que no
         * hace nada. Esta cuenta se actualiza cuando una fase anade la suya, y
         * ponerla a proposito obliga a mirarla en ese momento.
         */
        $this->admin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('nav', 6)
                ->where('nav.0.key', 'dashboard')
                ->where('nav.3.key', 'surveys')
            );
    }

    public function test_un_colaborador_no_entra_al_panel(): void
    {
        // RA-002 y RA-005: ocultar el enlace no es autorizar.
        $membership = Membership::factory()->collaborator()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_sin_sesion_no_se_comparte_navegacion(): void
    {
        // La navegacion se construye con route(), y sin usuario no hay
        // organizacion activa que la justifique.
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('nav', 0));
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
