<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * DIAGNOSTICO TEMPORAL. Se retira cuando sepamos por que AuthenticateSession
 * no cierra la sesion.
 *
 * Aisla las tres piezas de la cadena por separado, sin peticiones HTTP.
 * Hasta ahora las he estado mirando juntas y por eso llevo tres hipotesis
 * falsas: el orden del middleware, su registro, y una asercion mia que
 * comparaba un HMAC contra un hash bcrypt sin darse cuenta.
 */
final class PasswordHashDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_force_fill_con_hash_ya_hecho_cambia_el_valor_guardado(): void
    {
        // Si esto falla, mi prueba de invalidacion simulaba un cambio de
        // contrasena que nunca ocurria, y AuthenticateSession funciona bien.
        $usuario = User::factory()->create();
        $antes = $usuario->password;

        $usuario->forceFill(['password' => Hash::make('OtraContrasena2026')])->save();

        $this->assertNotSame($antes, $usuario->fresh()->password);
    }

    public function test_b_el_cast_hashed_no_vuelve_a_hashear_un_hash(): void
    {
        // El cast 'hashed' debe reconocer que ya recibe un hash y dejarlo
        // pasar. Si lo rehashea, el valor guardado no seria verificable con
        // la contrasena original y el login se romperia en silencio.
        $usuario = User::factory()->create();
        $hash = Hash::make('OtraContrasena2026');

        $usuario->forceFill(['password' => $hash])->save();

        $this->assertSame($hash, $usuario->fresh()->password);
        $this->assertTrue(Hash::check('OtraContrasena2026', $usuario->fresh()->password));
    }

    public function test_c_el_valor_guardado_en_sesion_no_es_el_hash_sino_un_hmac(): void
    {
        // Esta es la que explica por que mi asercion de diagnostico paso sin
        // comprobar nada: comparaba dos cosas de naturaleza distinta.
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/login');

        $enSesion = session('password_hash_web');

        $this->assertNotNull($enSesion, 'El middleware no guardo nada en la sesion.');
        $this->assertNotSame(
            $usuario->getAuthPassword(),
            $enSesion,
            'Si fueran iguales, el valor de sesion seria el hash y no un HMAC.',
        );
    }
}
