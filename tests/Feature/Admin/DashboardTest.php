<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El punto de entrada del administrador.
 *
 * Existe esta prueba porque el fallo que la motivo era invisible desde el
 * codigo: la pantalla cargaba, no daba ningun error, y simplemente no tenia
 * por donde salir.
 */
final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_panel_lleva_a_las_secciones_que_existen(): void
    {
        $this->admin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.branches.index'), false)
            ->assertSee(route('admin.people.index'), false);
    }

    public function test_el_panel_muestra_la_barra_lateral(): void
    {
        // Sin ella, quien entra aterriza en una pagina sin salida.
        $this->admin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('interface.nav.branches'), false)
            ->assertSee(__('interface.nav.people'), false)
            ->assertSee(__('interface.nav.security'), false);
    }

    public function test_el_panel_no_promete_secciones_que_no_existen(): void
    {
        $this->admin();

        $respuesta = $this->get(route('admin.dashboard'))->assertOk();

        // Fase 5, 9 y 12. /admin/encuestas salio de esta lista al abrir la
        // Fase 3: la prueba se puso roja y aviso, que es su trabajo.
        foreach (['/admin/multimedia', '/admin/respuestas', '/admin/analisis'] as $ruta) {
            $respuesta->assertDontSee($ruta, false);
        }
    }

    public function test_un_colaborador_no_entra_al_panel(): void
    {
        $membership = Membership::factory()->collaborator()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.dashboard'))->assertForbidden();
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
