<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Lo que la vista tiene que sostener y no se ve mirandola.
 *
 * RNF-AUT-004 y RNF-GEN-006. Son las piezas que se rompen al rediseñar una
 * pantalla sin que la pantalla se vea mal.
 */
final class SecondFactorScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_campo_de_codigo_tiene_etiqueta_y_autocompletado(): void
    {
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();

        $this->post('/login', ['email' => $usuario->email, 'password' => 'password']);

        $this->get('/verificacion')
            ->assertOk()
            ->assertSee('for="code"', false)
            ->assertSee('id="code"', false)
            // one-time-code hace que iOS y Android ofrezcan el codigo del
            // SMS o del correo sin teclearlo.
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('aria-describedby="code-hint', false);
    }

    public function test_la_pantalla_de_verificacion_ofrece_reenviar_y_cancelar(): void
    {
        // RF-AUT-015. Sin la salida, quien no reciba el correo se queda
        // atrapado entre la contrasena y el codigo, sin sesion y sin puerta.
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $this->post('/login', ['email' => $usuario->email, 'password' => 'password']);

        $this->get('/verificacion')
            ->assertOk()
            ->assertSee(route('auth.second-factor.resend'), false)
            ->assertSee(route('auth.second-factor.cancel'), false);
    }

    public function test_los_codigos_de_recuperacion_se_muestran_al_activar(): void
    {
        $usuario = Membership::factory()->create()->user;

        $this->actingAs($usuario)
            ->from(route('account.security'))
            ->followingRedirects()
            ->post(route('account.security.enable'))
            ->assertOk()
            ->assertSee(__('interface.security.codes_heading'), false)
            ->assertSee('role="alert"', false);
    }

    public function test_los_codigos_se_muestran_una_vez_y_solo_una(): void
    {
        /*
         * Van en un flash de sesion y en la base solo queda su hash.
         *
         * La version anterior de esta prueba solo comprobaba la segunda
         * mitad, y fallaba: daba por hecho que el flash se consumia en la
         * peticion del POST, cuando se consume en la SIGUIENTE, que es
         * precisamente la pantalla donde tienen que aparecer.
         *
         * Ahora comprueba las dos mitades, que es lo que hace que el aviso
         * de "guardalos ahora" sea verdad y no una frase.
         */
        $usuario = Membership::factory()->create()->user;

        $this->actingAs($usuario)->post(route('account.security.enable'));

        $this->actingAs($usuario)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee(__('interface.security.codes_heading'), false);

        $this->actingAs($usuario)
            ->get(route('account.security'))
            ->assertOk()
            ->assertDontSee(__('interface.security.codes_heading'), false);
    }

    public function test_la_pantalla_de_seguridad_dice_el_estado_en_texto(): void
    {
        // El color no puede ser el unico portador del estado.
        // ANEXO 1 seccion 47.
        $usuario = Membership::factory()->create()->user;

        $this->actingAs($usuario)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee(__('interface.security.mfa_off'), false);
    }

    public function test_la_pantalla_de_seguridad_es_para_cualquier_cuenta(): void
    {
        // No lleva el middleware 'organization': un administrador de
        // plataforma no tiene ninguna y tambien necesita entrar. RA-001.
        $plataforma = User::factory()->platformAdmin()->create();

        $this->actingAs($plataforma)
            ->get(route('account.security'))
            ->assertOk();
    }

    public function test_la_pantalla_de_seguridad_exige_sesion(): void
    {
        $this->get(route('account.security'))->assertRedirect(route('login'));
    }

    private function usuarioConSegundoFactor(): User
    {
        $usuario = Membership::factory()->create()->user;

        $this->actingAs($usuario)->post(route('account.security.enable'));
        $this->post('/logout');
        $this->flushSession();

        return $usuario->fresh();
    }
}
