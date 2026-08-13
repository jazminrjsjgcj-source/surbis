<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Identity\ActiveOrganizationContext;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF-GEN-001, RF-GEN-003 y RNF-GEN-005.
 *
 * La organizacion activa se resuelve en el servidor. Estas pruebas existen
 * sobre todo por la ultima: que un identificador enviado desde el navegador
 * no sirva para nada.
 */
final class ActiveOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_una_sola_organizacion_no_se_pregunta_nada(): void
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertSame(
            $membership->id,
            session(ActiveOrganizationContext::SESSION_KEY),
        );
    }

    public function test_con_varias_organizaciones_hay_que_elegir(): void
    {
        // P-004. No se asigna la primera ni la mas reciente: un valor por
        // defecto aqui taparia que la eleccion nunca se hizo.
        $user = User::factory()->create();
        Membership::factory()->for($user)->create();
        Membership::factory()->for($user)->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('auth.organizations.choose'));

        $this->assertNull(session(ActiveOrganizationContext::SESSION_KEY));
    }

    public function test_elegir_una_organizacion_propia_establece_el_contexto(): void
    {
        $user = User::factory()->create();
        $primera = Membership::factory()->for($user)->create();
        $segunda = Membership::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/organizaciones', ['organization' => $segunda->organization->ulid])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame($segunda->id, session(ActiveOrganizationContext::SESSION_KEY));
        $this->assertNotSame($primera->id, session(ActiveOrganizationContext::SESSION_KEY));
    }

    public function test_no_se_puede_elegir_una_organizacion_ajena(): void
    {
        // El caso que da sentido a la fase: el identificador viene del
        // navegador y el servidor no se fia de el.
        $user = User::factory()->create();
        Membership::factory()->for($user)->create();

        $ajena = Organization::factory()->create();

        $this->actingAs($user)
            ->post('/organizaciones', ['organization' => $ajena->ulid])
            ->assertSessionHasErrors('organization');

        $this->assertNull(session(ActiveOrganizationContext::SESSION_KEY));
    }

    public function test_suspender_la_membresia_corta_el_acceso_en_la_siguiente_peticion(): void
    {
        // RA-006. No hace falta esperar a que caduque la sesion.
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.dashboard'))->assertOk();

        $membership->update(['status' => 'suspended']);

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_un_colaborador_no_puede_entrar_al_panel_de_organizacion(): void
    {
        // RA-002 y RA-003. Ocultar el enlace no es autorizar. RA-005.
        $membership = Membership::factory()->collaborator()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_un_administrador_de_organizacion_no_entra_al_panel_de_plataforma(): void
    {
        // RA-001.
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get(route('platform.dashboard'))->assertForbidden();
    }

    public function test_el_administrador_de_plataforma_no_entra_al_panel_de_organizacion(): void
    {
        // RA-001: el acceso a una organizacion exige un mecanismo de soporte
        // auditado, que llega en su propia tarea. Sin el, no pasa.
        $user = User::factory()->platformAdmin()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }
}
