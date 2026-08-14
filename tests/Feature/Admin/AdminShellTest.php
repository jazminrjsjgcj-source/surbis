<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El marco de administracion y las piezas que reutilizaran las demas
 * pantallas: navegacion, tabla, paginacion y estados vacios.
 */
final class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_navegacion_lleva_a_las_sucursales(): void
    {
        /*
         * Antes de esta tarea, /admin/sucursales solo se alcanzaba
         * escribiendo la direccion: ninguna pantalla enlazaba a ella. Codigo
         * inalcanzable, la trampa T-001 otra vez.
         */
        $this->admin();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee(route('admin.branches.index'), false)
            ->assertSee(__('interface.nav.branches'), false);
    }

    public function test_la_seccion_activa_se_anuncia_en_el_marcado(): void
    {
        // aria-current y no solo un color: quien no ve la pantalla tambien
        // tiene que saber donde esta. ANEXO 1 seccion 47.
        $this->admin();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_la_navegacion_no_promete_pantallas_que_no_existen(): void
    {
        /*
         * Un menu con entradas que no llevan a ningun sitio es un mecanismo
         * que no hace nada. Cada fase anade la suya cuando tiene algo detras.
         *
         * La lista se acorta segun avanzan las fases, y eso es deliberado:
         * cuando una ruta empieza a existir, esta prueba se pone roja y
         * obliga a retirarla de aqui a mano. Es lo que paso con
         * /admin/encuestas al abrir la Fase 3.
         *
         * Podria comprobarse automaticamente contra la tabla de rutas, pero
         * entonces no avisaria de nada: pasaria siempre.
         */
        $this->admin();

        $respuesta = $this->get(route('admin.branches.index'))->assertOk();

        // Fase 5, 9 y 12. Se retiran de aqui cuando existan.
        foreach (['/admin/multimedia', '/admin/respuestas', '/admin/analisis'] as $ruta) {
            $respuesta->assertDontSee($ruta, false);
        }
    }

    public function test_la_navegacion_lleva_a_las_encuestas(): void
    {
        // Contrapartida de la anterior: lo que ya existe tiene que estar.
        $this->admin();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee(route('admin.surveys.index'), false)
            ->assertSee(__('interface.nav.surveys'), false);
    }

    public function test_la_tabla_tiene_titulo_y_encabezados_de_columna(): void
    {
        // Sin scope="col" un lector de pantalla no relaciona cada celda con
        // su columna, y la tabla se lee como una lista de valores sueltos.
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->create();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee('<caption', false)
            ->assertSee('scope="col"', false);
    }

    public function test_sin_sucursales_explica_que_son_y_como_crear_la_primera(): void
    {
        $this->admin();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee(__('interface.branches.empty_title'), false)
            ->assertSee(route('admin.branches.create'), false);
    }

    public function test_un_filtro_sin_resultados_dice_algo_distinto(): void
    {
        // Son dos situaciones con causas y salidas distintas. Un mensaje
        // unico deja al usuario creyendo que perdio sus datos.
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->create(['name' => 'Centro']);

        $this->get(route('admin.branches.index', ['q' => 'inexistente']))
            ->assertOk()
            ->assertSee(__('interface.branches.empty_search_title'), false)
            ->assertDontSee(__('interface.branches.empty_title'), false);
    }

    public function test_la_paginacion_aparece_al_pasar_de_una_pagina(): void
    {
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->count(25)->create();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee(__('interface.pagination.next'), false)
            ->assertSee('rel="next"', false);
    }

    public function test_la_paginacion_no_aparece_con_una_sola_pagina(): void
    {
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->count(3)->create();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertDontSee('rel="next"', false);
    }

    public function test_la_paginacion_conserva_los_filtros(): void
    {
        // withQueryString(). Sin el, pasar a la pagina dos pierde la busqueda
        // y el usuario ve resultados que no pidio sin entender por que.
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->count(25)->create(['name' => 'Oficina']);

        $this->get(route('admin.branches.index', ['q' => 'Oficina']))
            ->assertOk()
            ->assertSee('q=Oficina', false);
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
