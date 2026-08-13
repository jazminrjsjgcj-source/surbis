<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_de_organizacion_llega_a_admin(): void
    {
        // RF-AUT-003.
        $membership = Membership::factory()->create();

        $this->post('/login', $this->credencialesDe($membership->user))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($membership->user);
    }

    public function test_el_colaborador_llega_a_la_preparacion_del_quiosco(): void
    {
        $membership = Membership::factory()->collaborator()->create();

        $this->post('/login', $this->credencialesDe($membership->user))
            ->assertRedirect(route('kiosk.start'));
    }

    public function test_el_administrador_de_plataforma_llega_a_platform(): void
    {
        $user = User::factory()->platformAdmin()->create();

        $this->post('/login', $this->credencialesDe($user))
            ->assertRedirect(route('platform.dashboard'));
    }

    public function test_una_contrasena_incorrecta_no_revela_si_la_cuenta_existe(): void
    {
        // RNF-AUT-003. El mensaje debe ser el mismo exista la cuenta o no.
        //
        // Se comprueban los dos contra la MISMA constante, y no uno contra
        // otro: en un test las dos peticiones comparten la instancia de
        // sesion, asi que compararlos entre si era compararlos consigo
        // mismos. Aquella version pasaba aunque los mensajes difirieran.
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'incorrecta',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
        $this->flushSession();

        $this->post('/login', [
            'email' => 'nadie@example.test',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_un_usuario_suspendido_no_entra(): void
    {
        // RF-AUT-005, primero de los tres rechazos.
        $membership = Membership::factory()
            ->for(User::factory()->suspended())
            ->create();

        $this->post('/login', $this->credencialesDe($membership->user))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_una_organizacion_suspendida_no_deja_entrar_a_sus_miembros(): void
    {
        // RF-AUT-005, segundo rechazo. Distinto del anterior a proposito.
        $membership = Membership::factory()
            ->for(Organization::factory()->suspended())
            ->create();

        $this->post('/login', $this->credencialesDe($membership->user))
            ->assertSessionHasErrors(['email' => __('auth.organization_suspended')]);

        $this->assertGuest();
    }

    public function test_una_membresia_suspendida_no_deja_entrar(): void
    {
        // RF-AUT-005, tercer rechazo. RA-006.
        $membership = Membership::factory()->suspended()->create();

        $this->post('/login', $this->credencialesDe($membership->user))
            ->assertSessionHasErrors(['email' => __('auth.membership_suspended')]);

        $this->assertGuest();
    }

    public function test_los_tres_rechazos_dan_mensajes_distintos(): void
    {
        // Que se rechacen los tres no basta: RF-AUT-005 los enumera por
        // separado porque cada uno se resuelve llamando a una persona
        // distinta. Un mensaje unico haria el sistema tecnicamente correcto
        // e inutil en la practica.
        $mensajes = [
            __('auth.user_suspended'),
            __('auth.organization_suspended'),
            __('auth.membership_suspended'),
        ];

        $this->assertCount(3, array_unique($mensajes));
    }

    public function test_una_cuenta_sin_membresia_no_entra(): void
    {
        $user = User::factory()->create();

        $this->post('/login', $this->credencialesDe($user))
            ->assertSessionHasErrors(['email' => __('auth.without_membership')]);

        $this->assertGuest();
    }

    public function test_el_sexto_intento_fallido_queda_limitado(): void
    {
        // RNF-AUT-001.
        $membership = Membership::factory()->create();

        for ($intento = 0; $intento < 5; $intento++) {
            $this->post('/login', [
                'email' => $membership->user->email,
                'password' => 'incorrecta',
            ]);
        }

        $respuesta = $this->post('/login', $this->credencialesDe($membership->user));

        // Las credenciales de esta ultima peticion son CORRECTAS: si aun asi
        // no entra, es el limitador y no otra cosa.
        $respuesta->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(RateLimiter::tooManyAttempts($this->claveDeLimite($membership->user), 5));

        // No se compara contra el texto en espanol escrito a mano: eso
        // convierte cualquier reescritura del mensaje en un fallo de prueba
        // que no senala a ningun defecto real.
        $this->assertNotSame(
            __('auth.failed'),
            (string) $respuesta->getSession()->get('errors')->first('email'),
        );
    }

    public function test_una_cuenta_suspendida_no_gasta_intentos(): void
    {
        // Reintentar con una cuenta suspendida no es un ataque. Penalizarlo
        // anadiria un segundo motivo invisible al mismo error.
        $membership = Membership::factory()->suspended()->create();

        for ($intento = 0; $intento < 6; $intento++) {
            $this->post('/login', $this->credencialesDe($membership->user));
        }

        $this->assertSame(0, RateLimiter::attempts($this->claveDeLimite($membership->user)));
    }

    public function test_el_cierre_de_sesion_invalida_la_sesion(): void
    {
        $membership = Membership::factory()->create();

        $this->actingAs($membership->user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * Reconstruye la clave del limitador tal y como la arma LoginRequest.
     *
     * Esta escrita dos veces —aqui y en la aplicacion— y eso es un riesgo
     * conocido: si una cambia y la otra no, las pruebas del limitador miran
     * una clave inexistente, encuentran cero intentos y pasan por el motivo
     * equivocado. Lo que impide ese falso verde es que otra prueba comprueba
     * que el bloqueo ocurre DE VERDAD con esta misma clave.
     */
    private function claveDeLimite(User $user): string
    {
        return Str::transliterate(Str::lower($user->email).'|127.0.0.1');
    }

    /** @return array<string, string> */
    private function credencialesDe(User $user): array
    {
        return [
            'email' => $user->email,
            'password' => 'password',
        ];
    }
}
