<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\PasswordPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * RF-AUT-008 a 013 y RNF-AUT-006 a 010.
 */
final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_cuenta_existente_recibe_la_liga(): void
    {
        Notification::fake();

        $membership = Membership::factory()->create();

        $this->post('/recuperar-contrasena', ['email' => $membership->user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($membership->user, ResetPassword::class);
    }

    public function test_un_correo_desconocido_recibe_la_misma_respuesta(): void
    {
        // RF-AUT-009 y RNF-AUT-007. Es la prueba que impide que la pantalla
        // se convierta en un comprobador de cuentas registradas.
        Notification::fake();

        $membership = Membership::factory()->create();

        $conocido = $this->post('/recuperar-contrasena', [
            'email' => $membership->user->email,
        ])->getSession()->get('status');

        $this->flushSession();

        $desconocido = $this->post('/recuperar-contrasena', [
            'email' => 'nadie@example.test',
        ])->getSession()->get('status');

        $this->assertNotNull($conocido);
        $this->assertSame($conocido, $desconocido);

        // Y aun asi solo se envio un correo: la respuesta es igual, el
        // comportamiento no.
        Notification::assertCount(1);
    }

    public function test_el_sexto_intento_de_envio_queda_limitado(): void
    {
        // RNF-AUT-006.
        Notification::fake();

        for ($intento = 0; $intento < 5; $intento++) {
            $this->post('/recuperar-contrasena', ['email' => 'alguien@example.test']);
        }

        $this->post('/recuperar-contrasena', ['email' => 'alguien@example.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_una_liga_valida_cambia_la_contrasena(): void
    {
        Notification::fake();

        $usuario = Membership::factory()->create()->user;
        $token = $this->solicitarLiga($usuario);

        $this->post('/restablecer-contrasena', [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'ContrasenaNueva2026',
            'password_confirmation' => 'ContrasenaNueva2026',
        ])->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'ContrasenaNueva2026',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_una_liga_no_sirve_dos_veces(): void
    {
        // RF-AUT-010: de un solo uso.
        Notification::fake();

        $usuario = Membership::factory()->create()->user;
        $token = $this->solicitarLiga($usuario);

        $credenciales = [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'ContrasenaNueva2026',
            'password_confirmation' => 'ContrasenaNueva2026',
        ];

        $this->post('/restablecer-contrasena', $credenciales)
            ->assertRedirect(route('login'));

        $this->flushSession();

        $this->post('/restablecer-contrasena', $credenciales)
            ->assertSessionHasErrors('email');
    }

    public function test_un_token_inventado_no_sirve(): void
    {
        $usuario = Membership::factory()->create()->user;

        $this->post('/restablecer-contrasena', [
            'token' => 'inventado',
            'email' => $usuario->email,
            'password' => 'ContrasenaNueva2026',
            'password_confirmation' => 'ContrasenaNueva2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_una_contrasena_corta_se_rechaza(): void
    {
        // RF-AUT-012. El limite sale de PasswordPolicy, no de un numero
        // escrito aqui: si la politica cambia, esta prueba la sigue.
        Notification::fake();

        $usuario = Membership::factory()->create()->user;
        $token = $this->solicitarLiga($usuario);

        $corta = str_repeat('a1', (int) floor((PasswordPolicy::MIN_LENGTH - 1) / 2));
        $this->assertLessThan(PasswordPolicy::MIN_LENGTH, mb_strlen($corta));

        $this->post('/restablecer-contrasena', [
            'token' => $token,
            'email' => $usuario->email,
            'password' => $corta,
            'password_confirmation' => $corta,
        ])->assertSessionHasErrors('password');
    }

    public function test_la_pantalla_muestra_la_politica_que_aplica(): void
    {
        // La regla y su descripcion salen de la misma constante. Sin esto, el
        // texto puede prometer 8 caracteres mientras el servidor exige 12 y
        // nadie lo detecta hasta que un usuario se atasca.
        $this->get('/restablecer-contrasena/token-cualquiera')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ResetPassword')
                ->where('minLength', PasswordPolicy::MIN_LENGTH)
            );
    }

    public function test_la_pantalla_de_acceso_ofrece_recuperar_la_contrasena(): void
    {
        /*
         * La cadena 'interface.login.forgot' llevo dos entregas escrita sin
         * que ninguna pantalla la mostrara. Esta prueba existe por eso.
         *
         * Ahora comprueba PROPS y no marcado: con Inertia el servidor manda
         * la URL y las traducciones, y el componente las pinta. Si la prop no
         * llega, el enlace no puede existir.
         *
         * Lo que ya NO se comprueba desde aqui es que Login.tsx dibuje el
         * enlace. Eso solo lo ve una prueba de navegador.
         */
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('forgotUrl', route('password.request'))
                ->where('translations.interface.login.forgot', __('interface.login.forgot'))
            );
    }

    /**
     * Pide la liga y devuelve el token del correo enviado.
     *
     * El token se extrae FUERA del closure de assertSentTo a proposito. Si se
     * comprueba dentro y la notificacion no llega a enviarse, el closure no se
     * ejecuta nunca y las aserciones de dentro tampoco: la prueba pasa sin
     * haber comprobado nada.
     */
    private function solicitarLiga(User $usuario): string
    {
        $this->post('/recuperar-contrasena', ['email' => $usuario->email]);

        $token = null;

        Notification::assertSentTo(
            $usuario,
            ResetPassword::class,
            function (ResetPassword $aviso) use (&$token): bool {
                $token = $aviso->token;

                return true;
            }
        );

        $this->assertNotNull($token, 'No se envio ninguna liga de restablecimiento.');
        $this->flushSession();

        return $token;
    }
}
