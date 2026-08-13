<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RF-AUT-013: cambiar la contrasena invalida las sesiones anteriores.
 *
 * Se prueba aparte porque no depende del formulario de restablecimiento sino
 * del middleware AuthenticateSession. Si alguien lo quita de bootstrap/app.php
 * dentro de seis meses, nada mas se rompe: las sesiones viejas simplemente
 * siguen funcionando, sin dar ningun error.
 */
final class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambiar_la_contrasena_cierra_la_sesion_abierta(): void
    {
        $membership = Membership::factory()->create();
        $usuario = $membership->user;

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.dashboard'))->assertOk();

        // Simula el cambio hecho desde otro dispositivo.
        $usuario->forceFill(['password' => Hash::make('OtraContrasena2026')])->save();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
