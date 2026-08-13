<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RNF-AUT-004 y RNF-GEN-006.
 *
 * La accesibilidad no se comprueba entera con pruebas automaticas, pero si
 * las partes que son estructura y no criterio: que cada campo tenga etiqueta
 * asociada, que el error se anuncie, que el autocompletado sea el correcto.
 *
 * Estas son justamente las que se rompen sin avisar al rediseñar una
 * pantalla, porque la pantalla sigue viendose bien.
 */
final class LoginAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_campo_tiene_etiqueta_asociada(): void
    {
        $respuesta = $this->get('/login');

        $respuesta->assertOk()
            ->assertSee('for="email"', false)
            ->assertSee('id="email"', false)
            ->assertSee('for="password"', false)
            ->assertSee('id="password"', false);
    }

    public function test_los_campos_declaran_su_autocompletado(): void
    {
        // Sin esto, el gestor de contrasenas del navegador no rellena y el
        // teclado del movil no ofrece el diseno adecuado.
        $this->get('/login')
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_un_error_se_anuncia_y_se_asocia_al_campo(): void
    {
        $respuesta = $this->from('/login')
            ->followingRedirects()
            ->post('/login', [
                'email' => 'nadie@example.test',
                'password' => 'incorrecta',
            ]);

        $respuesta->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="email-error"', false)
            ->assertSee('id="email-error"', false);
    }

    public function test_el_correo_escrito_se_conserva_tras_un_error(): void
    {
        // Prevencion de errores: obligar a reescribir el correo despues de
        // fallar la contrasena es trabajo que el sistema puede evitar.
        $membership = Membership::factory()->create();

        $this->from('/login')
            ->followingRedirects()
            ->post('/login', [
                'email' => $membership->user->email,
                'password' => 'incorrecta',
            ])
            ->assertOk()
            ->assertSee($membership->user->email, false);
    }

    public function test_la_eleccion_de_organizacion_agrupa_las_opciones(): void
    {
        // Un grupo de radios sin fieldset y legend se anuncia como opciones
        // sueltas, sin decir de que son opciones.
        $user = Membership::factory()->create()->user;
        Membership::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('/organizaciones')
            ->assertOk()
            ->assertSee('<fieldset', false)
            ->assertSee('<legend', false);
    }
}
