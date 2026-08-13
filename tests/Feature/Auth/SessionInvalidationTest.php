<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * RF-AUT-013: cambiar la contrasena invalida las sesiones anteriores.
 *
 * El mecanismo son dos piezas distintas, y por eso hay dos pruebas:
 *
 *   AuthenticateSession   compara en cada peticion el hash guardado en la
 *                         sesion con el actual del usuario. Cubre las
 *                         sesiones activas.
 *   remember_token        se rota al restablecer. Cubre las sesiones
 *                         "recordadas" por cookie, que sobreviven al cierre
 *                         del navegador y no pasan por la comparacion
 *                         anterior.
 *
 * Si alguien quita AuthenticateSession de bootstrap/app.php dentro de seis
 * meses, nada mas se rompe: las sesiones viejas simplemente siguen
 * funcionando, sin dar ningun error. Estas dos son lo unico que lo diria.
 */
final class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambiar_la_contrasena_cierra_la_sesion_abierta(): void
    {
        $usuario = Membership::factory()->create()->user;

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSessionHas('password_hash_web');

        // Simula el cambio hecho desde otro dispositivo.
        $usuario->forceFill(['password' => Hash::make('OtraContrasena2026')])->save();

        /*
         * ALCANCE DE ESTA PRUEBA, y por que hace falta esta linea.
         *
         * En produccion cada peticion es un proceso nuevo y el guard relee al
         * usuario de la base, asi que la comparacion del middleware ve el
         * hash nuevo. En una prueba, las dos peticiones comparten proceso y
         * el guard conserva en memoria la instancia con el hash viejo:
         * compara viejo contra viejo y no cierra nada.
         *
         * forgetGuards() reproduce lo que produccion hace sola. Costo cinco
         * hipotesis descartadas averiguarlo, y queda escrito para que nadie
         * lo vuelva a pagar.
         *
         * Lo que esta prueba GARANTIZA: que el middleware esta registrado y
         * que su comparacion cierra la sesion.
         * Lo que NO demuestra: el escenario real de dos dispositivos, que
         * requeriria dos clientes HTTP con sesiones separadas.
         */
        $this->app['auth']->forgetGuards();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_restablecer_la_contrasena_rota_el_token_de_recordar(): void
    {
        /*
         * La otra mitad de RF-AUT-013, y la que no depende de ningun truco
         * del entorno de prueba.
         *
         * Una sesion "recordada" vive en una cookie que contiene el
         * remember_token. Rotarlo la invalida aunque el navegador siga
         * cerrado y nunca llegue a pasar por AuthenticateSession.
         */
        Notification::fake();

        $usuario = Membership::factory()->create()->user;
        $tokenAnterior = $usuario->remember_token;

        $this->assertNotNull($tokenAnterior, 'La factory debe crear un remember_token.');

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

        $this->post('/restablecer-contrasena', [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'ContrasenaNueva2026',
            'password_confirmation' => 'ContrasenaNueva2026',
        ])->assertRedirect(route('login'));

        $this->assertNotSame($tokenAnterior, $usuario->fresh()->remember_token);
    }

    public function test_un_cambio_de_contrasena_no_afecta_a_otros_usuarios(): void
    {
        // El error de simetria: invalidar de mas es tan defecto como
        // invalidar de menos.
        $ajeno = Membership::factory()->create()->user;
        $tokenAjeno = $ajeno->remember_token;

        $usuario = Membership::factory()->create()->user;
        $usuario->forceFill(['password' => Hash::make('OtraContrasena2026')])->save();

        $this->assertSame($tokenAjeno, $ajeno->fresh()->remember_token);
        $this->assertInstanceOf(User::class, $ajeno->fresh());
    }
}
