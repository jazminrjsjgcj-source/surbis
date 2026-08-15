<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * RNF-AUT-004 y RNF-GEN-006.
 *
 * ATENCION: este archivo comprobaba mucho mas de lo que comprueba ahora.
 *
 * Antes de Inertia afirmaba sobre el marcado que devolvia el servidor:
 * for="email", aria-describedby, aria-invalid, role="alert". Ese marcado lo
 * genera ahora el navegador a partir de Login.tsx, asi que una peticion HTTP
 * ya no puede verlo.
 *
 * Lo que queda aqui es lo unico que SIGUE siendo comprobable desde el
 * servidor: que los datos que el formulario necesita para comportarse bien
 * llegan al componente.
 *
 * LO QUE SE PERDIO, y no esta resuelto:
 *
 *   - que cada campo tenga su etiqueta asociada
 *   - que los errores se anuncien con role="alert"
 *   - que el campo con error lleve aria-invalid y aria-describedby
 *   - que el autocompletado sea el correcto
 *
 * Login.tsx tiene las cuatro cosas. Lo que ya no existe es la red que vigila
 * que sigan estando manana. Recuperarla pide pruebas de navegador —Dusk o
 * Playwright— y eso es una decision y una tarea aparte, no un detalle de
 * esta.
 *
 * Se deja escrito aqui, y no solo en el documento de contexto, porque quien
 * abra este archivo dentro de seis meses vera cuatro pruebas donde habia
 * ocho y tiene derecho a saber por que.
 */
final class LoginAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_correo_escrito_se_conserva_tras_un_error(): void
    {
        /*
         * Prevencion de errores: obligar a reescribir el correo despues de
         * fallar la contrasena es trabajo que el sistema puede evitar.
         *
         * Con Inertia esto lo hace `old input` de la sesion, que el
         * componente lee. Se comprueba en la sesion porque es donde el
         * servidor lo deja.
         */
        $membership = Membership::factory()->create();

        $this->from('/login')
            ->post('/login', [
                'email' => $membership->user->email,
                'password' => 'incorrecta',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasInput('email', $membership->user->email);
    }

    public function test_la_contrasena_no_se_conserva(): void
    {
        // La contrasena NO debe quedarse en la sesion ni volver al navegador.
        // Es la otra mitad de la prueba anterior, y la que se olvida.
        $membership = Membership::factory()->create();

        $this->from('/login')
            ->post('/login', [
                'email' => $membership->user->email,
                'password' => 'incorrecta',
            ]);

        $this->assertNull(session('_old_input.password'));
    }

    public function test_el_error_se_asocia_al_campo_del_correo(): void
    {
        /*
         * El error va bajo la clave 'email' y no en un mensaje suelto.
         *
         * De esa clave depende que el componente pinte aria-invalid y
         * aria-describedby en el campo correcto. Si el error llegara sin
         * clave, la pantalla mostraria el aviso arriba y el campo quedaria
         * sin marcar para quien usa lector de pantalla.
         */
        $this->from('/login')
            ->post('/login', [
                'email' => 'nadie@example.test',
                'password' => 'incorrecta',
            ])
            ->assertInvalid(['email']);
    }

    public function test_la_eleccion_de_organizacion_recibe_sus_opciones(): void
    {
        /*
         * Ya no se comprueba <fieldset> ni <legend>.
         *
         * Esta prueba se escribio con una nota: valia mientras la pantalla
         * fuera Blade, y habia que cambiarla al convertirla. Ese momento
         * llego.
         *
         * El agrupado sigue en ChooseOrganization.tsx —sin el, un lector de
         * pantalla lee opciones sueltas sin decir de que eligen— pero eso
         * ahora solo lo ve una prueba de navegador. Lo comprobable desde el
         * servidor es que lleguen las organizaciones entre las que elegir.
         */
        $user = Membership::factory()->create()->user;
        Membership::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('/organizaciones')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ChooseOrganization')
                ->has('memberships', 2)
            );
    }
}
