<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Identity\ManageMembership;
use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DIAGNOSTICO TEMPORAL. Se retira en cuanto sepamos por que no se suspende.
 *
 * assertSessionHasNoErrors pasa tambien con un 403: una respuesta denegada no
 * tiene errores de sesion. Asi que la prueba original no distinguia "funciono"
 * de "ni siquiera entro".
 *
 * Estas tres separan las posibilidades. Cada una solo puede fallar por un
 * motivo.
 */
final class SuspendDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_el_caso_de_uso_suspende_por_su_cuenta(): void
    {
        // Si falla: el problema esta en ManageMembership, no en la pantalla.
        $membership = Membership::factory()->create();
        $otro = Membership::factory()->for($membership->organization)->create();

        app(ManageMembership::class)->suspend($otro);

        $this->assertSame('suspended', $otro->fresh()->status->value);
    }

    public function test_b_la_peticion_no_es_denegada(): void
    {
        // Si falla con 403: la Policy deniega y hay que mirar por que.
        // Si falla con 404: el enlace de rutas no encuentra la membresia.
        $admin = $this->admin();
        $otro = Membership::factory()->for($admin->organization)->create();

        $respuesta = $this->post(route('admin.people.suspend', $otro));

        $this->assertNotSame(403, $respuesta->getStatusCode(), 'La Policy denego la accion.');
        $this->assertNotSame(404, $respuesta->getStatusCode(), 'La ruta no encontro la membresia.');
        $this->assertSame(302, $respuesta->getStatusCode(), 'Se esperaba una redireccion.');
    }

    public function test_c_la_ruta_apunta_a_la_membresia_correcta(): void
    {
        // Membership no usa ULID, asi que su clave de ruta es el id. Si esto
        // falla, la URL lleva otro valor y el enlace resuelve otra cosa.
        $admin = $this->admin();
        $otro = Membership::factory()->for($admin->organization)->create();

        $this->assertSame(
            (string) $otro->id,
            (string) $otro->getRouteKey(),
            'La clave de ruta de Membership no es su id.',
        );

        $this->assertStringContainsString(
            '/admin/personas/'.$otro->id.'/suspender',
            route('admin.people.suspend', $otro),
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
