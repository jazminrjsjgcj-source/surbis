<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * El marco de administracion y las piezas compartidas.
 *
 * ATENCION: este archivo comprobaba mas de lo que comprueba ahora.
 *
 * Antes de Inertia afirmaba sobre el marcado que devolvia el servidor:
 * <caption>, scope="col", aria-current="page", rel="next". Ese marcado lo
 * genera ahora el navegador a partir de DataTable, Pagination y AdminShell,
 * asi que una peticion HTTP ya no puede verlo.
 *
 * Lo que queda aqui es lo que SIGUE siendo comprobable desde el servidor: que
 * los datos que la pantalla necesita para comportarse bien llegan como props.
 *
 * LO QUE SE PERDIO, y no esta resuelto:
 *
 *   - que la tabla tenga caption y scope="col"
 *   - que la seccion activa lleve aria-current
 *   - que la paginacion marque rel="next"
 *
 * Los componentes lo tienen. Lo que ya no existe es la red que vigila que
 * sigan estando manana, y eso pide pruebas de navegador. Es la misma deuda
 * que dejo la conversion del acceso, ahora extendida al panel.
 */
final class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_navegacion_viaja_como_prop_en_toda_pantalla_de_admin(): void
    {
        // React no conoce las rutas nombradas de Laravel. Si cada componente
        // escribiera sus URLs, habria tantas verdades como pantallas.
        $this->admin();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('nav', 5)
                ->where('nav.1.key', 'branches')
                ->where('nav.1.url', route('admin.branches.index'))
            );
    }

    public function test_la_navegacion_no_promete_pantallas_que_no_existen(): void
    {
        /*
         * Cuatro entradas, ni una mas. La lista se amplia cuando una fase
         * anade la suya, y fijar la cuenta obliga a mirarla en ese momento.
         *
         * Multimedia (Fase 5), respuestas (Fase 9) y analisis (Fase 12) no
         * estan. Un menu que promete pantallas que no existen es un mecanismo
         * que no hace nada.
         */
        $this->admin();

        $respuesta = $this->get(route('admin.branches.index'))->assertOk();

        $respuesta->assertInertia(fn (AssertableInertia $page) => $page
            ->has('nav', 5)
            ->where('nav.0.key', 'dashboard')
            ->where('nav.1.key', 'branches')
            ->where('nav.2.key', 'people')
            ->where('nav.3.key', 'surveys')
        );
    }

    public function test_la_tabla_recibe_las_filas_que_debe_pintar(): void
    {
        // Lo comprobable desde el servidor. Que la tabla lleve caption y
        // scope="col" solo lo ve una prueba de navegador.
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->count(3)->create();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('branches.data', 3));
    }

    public function test_sin_sucursales_no_llega_ninguna_fila(): void
    {
        $this->admin();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('branches.data', 0)
                ->where('filters.q', '')
                ->where('filters.status', '')
            );
    }

    public function test_un_filtro_sin_resultados_llega_con_el_filtro_puesto(): void
    {
        /*
         * Son dos vacios distintos, y el componente decide cual mostrar
         * mirando `filters`. Si esa prop no llegara, la pantalla diria
         * "todavia no hay sucursales" a alguien que acaba de buscar, y esa
         * persona creeria que perdio sus datos.
         */
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->create(['name' => 'Centro']);

        $this->get(route('admin.branches.index', ['q' => 'inexistente']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('branches.data', 0)
                ->where('filters.q', 'inexistente')
            );
    }

    public function test_la_paginacion_llega_con_sus_enlaces(): void
    {
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->count(25)->create();

        $this->get(route('admin.branches.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('branches.data', 20)
                ->where('branches.total', 25)
                ->has('branches.links')
            );
    }

    public function test_la_paginacion_conserva_los_filtros(): void
    {
        // withQueryString(). Sin el, pasar a la pagina dos pierde la busqueda
        // y el usuario ve resultados que no pidio sin entender por que.
        $membership = $this->admin();
        Branch::factory()->for($membership->organization)->count(25)->create(['name' => 'Oficina']);

        $this->get(route('admin.branches.index', ['q' => 'Oficina']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('branches.links.2.url', fn (?string $url): bool => $url !== null
                    && str_contains($url, 'q=Oficina'))
            );
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
