<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Dar de alta con contrasena, cuando no hay correo.
 *
 * Decision del area usuaria, 19 ago 2026. Sin correo configurado el enlace de
 * invitacion no llega a nadie, asi que no se podria dar de alta a ninguna
 * persona.
 *
 * Tiene un coste conocido: quien da de alta CONOCE esa contrasena, y mientras
 * no se cambie puede entrar como esa persona —con la auditoria registrando
 * sus acciones bajo el nombre del titular—. Por eso se marca.
 */
final class CreateWithPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_correo_se_puede_dar_de_alta_con_contrasena(): void
    {
        config(['mail.default' => 'log']);

        $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.test',
            'role' => 'collaborator',
            'password' => 'UnaClaveNueva9!',
            'password_confirmation' => 'UnaClaveNueva9!',
        ])->assertRedirect();

        $usuario = User::query()->where('email', 'ana@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('UnaClaveNueva9!', $usuario->password));
    }

    public function test_queda_marcado_que_la_puso_otra_persona(): void
    {
        // Es lo que permite ver despues donde esta ese riesgo.
        config(['mail.default' => 'log']);

        $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.test',
            'role' => 'collaborator',
            'password' => 'UnaClaveNueva9!',
            'password_confirmation' => 'UnaClaveNueva9!',
        ]);

        $usuario = User::query()->where('email', 'ana@example.test')->firstOrFail();

        $this->assertNotNull($usuario->getAttributes()['password_set_by_other_at']);
    }

    public function test_la_membresia_nace_activa(): void
    {
        /*
         * La invitacion normal nace suspendida y se activa al aceptarla. Aqui
         * no hay nada que aceptar, asi que dejarla suspendida impediria entrar
         * a alguien que ya tiene sus credenciales.
         */
        config(['mail.default' => 'log']);

        $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.test',
            'role' => 'collaborator',
            'password' => 'UnaClaveNueva9!',
            'password_confirmation' => 'UnaClaveNueva9!',
        ]);

        $nueva = Membership::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'ana@example.test'))
            ->firstOrFail();

        $this->assertSame(MembershipStatus::Active, $nueva->status);
    }

    public function test_con_correo_configurado_la_contrasena_se_ignora(): void
    {
        /*
         * Con correo, se invita. Aceptar una contrasena aqui daria dos formas
         * de crear cuentas y la mas insegura seria la comoda.
         *
         * Se comprueba en el SERVIDOR contra la configuracion real: mandarla
         * a mano no sirve de nada.
         */
        config(['mail.default' => 'smtp']);

        $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.test',
            'role' => 'collaborator',
            'password' => 'UnaClaveNueva9!',
            'password_confirmation' => 'UnaClaveNueva9!',
        ]);

        $usuario = User::query()->where('email', 'ana@example.test')->firstOrFail();

        $this->assertFalse(Hash::check('UnaClaveNueva9!', $usuario->password));
        $this->assertNull($usuario->getAttributes()['password_set_by_other_at']);
    }

    public function test_sin_contrasena_se_invita_como_siempre(): void
    {
        config(['mail.default' => 'log']);

        $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.test',
            'role' => 'collaborator',
        ])->assertRedirect();

        $nueva = Membership::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'ana@example.test'))
            ->firstOrFail();

        $this->assertSame(MembershipStatus::Suspended, $nueva->status);
    }

    public function test_la_pantalla_dice_si_se_puede_invitar(): void
    {
        config(['mail.default' => 'log']);

        $this->admin();

        $this->get(route('admin.people.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canInvite', false));
    }

    public function test_dar_de_alta_con_contrasena_queda_auditado(): void
    {
        // Se distingue de una invitacion normal: son dos cosas con riesgos
        // distintos.
        config(['mail.default' => 'log']);

        $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.test',
            'role' => 'collaborator',
            'password' => 'UnaClaveNueva9!',
            'password_confirmation' => 'UnaClaveNueva9!',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'membership.created_with_password',
        ]);
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
