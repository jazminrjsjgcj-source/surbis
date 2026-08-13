<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La raiz del sistema no puede devolver 404.
 *
 * Estuvo devolviendolo desde que se retiro la pagina de bienvenida de
 * Laravel, y el enlace "Volver al inicio" de la pantalla de seguridad
 * apuntaba ahi. No lo detecto ninguna verificacion porque url('/') no es una
 * ruta nombrada y mis comprobaciones solo miran las nombradas.
 */
final class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_lleva_al_acceso(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_el_administrador_de_organizacion_llega_a_su_panel(): void
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get('/')->assertRedirect(route('admin.dashboard'));
    }

    public function test_el_colaborador_llega_al_quiosco(): void
    {
        $membership = Membership::factory()->collaborator()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get('/')->assertRedirect(route('kiosk.start'));
    }

    public function test_el_administrador_de_plataforma_llega_a_platform(): void
    {
        $usuario = User::factory()->platformAdmin()->create();

        $this->actingAs($usuario)->get('/')->assertRedirect(route('platform.dashboard'));
    }

    public function test_con_verificacion_pendiente_lleva_a_la_verificacion(): void
    {
        // Sin esta rama, la raiz mandaria al login a alguien que ya escribio
        // su contrasena correctamente y solo le falta el codigo.
        Notification::fake();

        $usuario = Membership::factory()->create()->user;
        $this->actingAs($usuario)->post(route('account.security.enable'));
        $this->post('/logout');
        $this->flushSession();

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $this->get('/')->assertRedirect(route('auth.second-factor.challenge'));
    }

    public function test_no_obliga_a_reelegir_organizacion(): void
    {
        // Quien pertenece a varias ya eligio al entrar. Volver a preguntarle
        // cada vez que escribe la direccion seria perder su eleccion.
        $usuario = User::factory()->create();
        Membership::factory()->for($usuario)->create();
        $segunda = Membership::factory()->for($usuario)->create();

        $this->actingAs($usuario)
            ->post('/organizaciones', ['organization' => $segunda->organization->ulid]);

        $this->get('/')->assertRedirect(route('admin.dashboard'));
    }
}
