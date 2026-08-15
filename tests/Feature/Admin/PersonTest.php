<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Identity\Exceptions\LastAdministrator;
use App\Application\Identity\ManageMembership;
use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\StaffMember;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * RF-AO-COL-001 a 006 · RNF-AO-COL-001 y 003 · RNF-GEN-005.
 */
final class PersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_lista_muestra_cuentas_y_personas_sin_cuenta(): void
    {
        // P-014.
        $admin = $this->admin();

        StaffMember::factory()->for($admin->organization)->create([
            'first_name' => 'Maria',
            'last_name' => 'Ventanilla',
        ]);

        $this->get(route('admin.people.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/People/Index')
                ->has('rows', 2)
                ->where('rows.1.name', 'Maria Ventanilla')
            );
    }

    public function test_una_persona_con_cuenta_no_sale_dos_veces(): void
    {
        // Sin el filtro withoutAccount saldria como cuenta y como persona, y
        // el administrador creeria que hay dos Marias.
        $admin = $this->admin();

        $otra = Membership::factory()->for($admin->organization)->create();
        StaffMember::factory()->for($admin->organization)->create([
            'membership_id' => $otra->id,
            'first_name' => 'Maria',
            'last_name' => 'Duplicada',
        ]);

        /*
         * Se cuentan las filas, no se busca el texto.
         *
         * assertDontSee habria seguido pasando si la fila duplicada llegara
         * en las props sin pintarse. Y aqui el fallo seria invisible: dos
         * Marias identicas donde deberia haber una.
         */
        $this->get(route('admin.people.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 2)
                ->where('rows', fn (Collection $rows): bool => $rows
                    ->pluck('name')
                    ->doesntContain('Maria Duplicada'))
            );
    }

    public function test_no_se_ven_personas_de_otra_organizacion(): void
    {
        // RNF-GEN-005.
        $this->admin();

        $ajena = Membership::factory()->create();
        StaffMember::factory()->create(['first_name' => 'Persona', 'last_name' => 'Ajena']);

        /*
         * La prueba de aislamiento, contra PROPS.
         *
         * Con Inertia, assertDontSee pasaria aunque la fila ajena viajara en
         * el JSON: quedaria en verde justo cuando empezara a haber una fuga
         * entre organizaciones. Contar filas si lo detecta.
         */
        $this->get(route('admin.people.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
            );
    }

    public function test_quien_no_tiene_cuenta_lo_dice_en_lugar_de_dejarlo_vacio(): void
    {
        // Un guion no distingue "no tiene" de "no se sabe" de "no aplica".
        $admin = $this->admin();
        StaffMember::factory()->for($admin->organization)->create();

        /*
         * D-018: el componente pinta "No inicia sesion" cuando no hay correo,
         * y lo decide con estas props. Que el texto salga en pantalla lo
         * comprueba una prueba de navegador.
         */
        $this->get(route('admin.people.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.1.email', null)
                ->where('rows.1.has_account', false)
                ->where('rows.1.is_evaluated', true)
            );
    }

    public function test_invitar_crea_la_membresia_suspendida_y_envia_la_liga(): void
    {
        // P-015. Nace suspendida: entre la invitacion y su aceptacion, esa
        // cuenta no puede entrar.
        Notification::fake();

        $admin = $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Nueva Persona',
            'email' => 'nueva@example.test',
            'role' => 'collaborator',
        ])->assertRedirect(route('admin.people.index'));

        $usuario = User::query()->where('email', 'nueva@example.test')->firstOrFail();

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $admin->organization_id,
            'user_id' => $usuario->id,
            'status' => 'suspended',
        ]);

        Notification::assertSentTo($usuario, ResetPassword::class);
    }

    public function test_una_invitacion_no_puede_iniciar_sesion_todavia(): void
    {
        Notification::fake();

        $admin = $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Nueva Persona',
            'email' => 'nueva@example.test',
            'role' => 'admin',
        ]);

        $this->post('/logout');
        $this->flushSession();

        $this->post('/login', [
            'email' => 'nueva@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_definir_la_contrasena_activa_la_membresia(): void
    {
        // P-015: al usar la liga, la cuenta se considera aceptada y activada.
        Notification::fake();

        $this->admin();

        $this->post(route('admin.people.store'), [
            'name' => 'Nueva Persona',
            'email' => 'nueva@example.test',
            'role' => 'admin',
        ]);

        $usuario = User::query()->where('email', 'nueva@example.test')->firstOrFail();

        $token = null;
        Notification::assertSentTo($usuario, ResetPassword::class, function (ResetPassword $aviso) use (&$token): bool {
            $token = $aviso->token;

            return true;
        });
        $this->assertNotNull($token);

        $this->post('/logout');
        $this->flushSession();

        $this->post('/restablecer-contrasena', [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'ContrasenaNueva2026',
            'password_confirmation' => 'ContrasenaNueva2026',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('memberships', [
            'user_id' => $usuario->id,
            'status' => 'active',
        ]);
    }

    public function test_un_restablecimiento_normal_no_reactiva_una_membresia_suspendida(): void
    {
        /*
         * La otra rama, y la que se olvida: activar una membresia que un
         * administrador suspendio a proposito seria deshacer su decision
         * desde la pantalla de contrasenas.
         */
        Notification::fake();

        $membership = Membership::factory()->suspended()->create(['joined_at' => now()->subMonth()]);
        $usuario = $membership->user;

        $this->post('/recuperar-contrasena', ['email' => $usuario->email]);

        $token = null;
        Notification::assertSentTo($usuario, ResetPassword::class, function (ResetPassword $aviso) use (&$token): bool {
            $token = $aviso->token;

            return true;
        });

        $this->flushSession();

        $this->post('/restablecer-contrasena', [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'ContrasenaNueva2026',
            'password_confirmation' => 'ContrasenaNueva2026',
        ]);

        $this->assertSame('suspended', $membership->fresh()->status->value);
    }

    public function test_no_se_invita_dos_veces_a_la_misma_organizacion(): void
    {
        // RNF-AO-COL-003.
        Notification::fake();

        $admin = $this->admin();
        $existente = Membership::factory()->for($admin->organization)->create();

        $this->post(route('admin.people.store'), [
            'name' => 'Repetido',
            'email' => $existente->user->email,
            'role' => 'collaborator',
        ])->assertSessionHasErrors('email');
    }

    public function test_la_misma_persona_puede_estar_en_dos_organizaciones(): void
    {
        // P-004. Es el reverso de la prueba anterior y por eso va aqui.
        Notification::fake();

        $admin = $this->admin();
        $ajena = Membership::factory()->create();

        $this->post(route('admin.people.store'), [
            'name' => $ajena->user->name,
            'email' => $ajena->user->email,
            'role' => 'collaborator',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Membership::query()->where('user_id', $ajena->user_id)->count());
    }

    public function test_con_dos_administradores_se_puede_suspender_a_uno(): void
    {
        $admin = $this->admin();
        $otro = Membership::factory()->for($admin->organization)->create();

        $this->post(route('admin.people.suspend', $otro))->assertSessionHasNoErrors();

        $this->assertSame('suspended', $otro->fresh()->status->value);
        $this->assertSame('active', $admin->fresh()->status->value);
    }

    public function test_el_guardian_del_ultimo_administrador_bloquea_la_operacion(): void
    {
        /*
         * RF-AO-COL-006, y hay que explicar por que esta prueba llama al caso
         * de uso en lugar de a la pantalla.
         *
         * Desde /admin/personas ese bloqueo NO se puede provocar: la Policy
         * exige que quien actua sea administrador activo, y P-017 impide
         * actuar sobre uno mismo. Si el objetivo es administrador activo, el
         * actor tambien lo es, asi que siempre hay al menos dos y el objetivo
         * nunca es el ultimo.
         *
         * O sea: hoy quien cumple RF-AO-COL-006 en esta pantalla es P-017, no
         * este guardian.
         *
         * El guardian se queda porque los caminos que SI podran alcanzarlo
         * llegan despues: el perfil propio, un "salir de la organizacion", y
         * el acceso de soporte del administrador de plataforma. Cuando
         * existan, la regla ya estara escrita y probada en lugar de
         * descubrirse el dia que una organizacion se quede sin nadie que
         * pueda administrarla.
         *
         * Esta prueba es lo unico que impide que ese guardian sea codigo que
         * nadie ejecuta jamas.
         */
        $membership = Membership::factory()->create();

        $this->expectException(LastAdministrator::class);

        app(ManageMembership::class)->suspend($membership);
    }

    public function test_el_guardian_deja_pasar_cuando_hay_sustitucion(): void
    {
        $membership = Membership::factory()->create();
        Membership::factory()->for($membership->organization)->create();

        app(ManageMembership::class)->suspend($membership);

        $this->assertSame('suspended', $membership->fresh()->status->value);
    }

    public function test_bajar_de_rol_al_ultimo_administrador_tambien_se_bloquea(): void
    {
        // Dejar a la organizacion sin administradores por la via del rol es
        // el mismo dano que por la via de la suspension.
        $membership = Membership::factory()->create();

        $this->expectException(LastAdministrator::class);

        app(ManageMembership::class)->changeRole($membership, MembershipRole::Collaborator);
    }

    public function test_el_ultimo_administrador_no_puede_ser_suspendido_por_otro(): void
    {
        $admin = $this->admin();
        $colaborador = Membership::factory()->collaborator()->for($admin->organization)->create();

        // Un colaborador no llega ni a intentarlo.
        $this->actingAsMembership($colaborador);
        $this->post(route('admin.people.suspend', $admin))->assertForbidden();

        $this->assertSame('active', $admin->fresh()->status->value);
    }

    public function test_nadie_se_suspende_a_si_mismo(): void
    {
        // P-017.
        $admin = $this->admin();
        Membership::factory()->for($admin->organization)->create();

        $this->post(route('admin.people.suspend', $admin))->assertForbidden();

        $this->assertSame('active', $admin->fresh()->status->value);
    }

    public function test_no_se_asigna_una_sucursal_de_otra_organizacion(): void
    {
        $admin = $this->admin();
        $otro = Membership::factory()->for($admin->organization)->create();
        $ajena = Branch::factory()->create();

        $this->put(route('admin.people.assign', $otro), ['branch_id' => $ajena->id])
            ->assertSessionHasErrors('branch_id');

        $this->assertNull($otro->fresh()->branch_id);
    }

    public function test_las_acciones_quedan_auditadas(): void
    {
        // RNF-AO-COL-001.
        $admin = $this->admin();
        $otro = Membership::factory()->for($admin->organization)->create();

        $this->post(route('admin.people.suspend', $otro));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->user_id,
            'action' => 'membership.suspended',
        ]);
    }

    private function admin(): Membership
    {
        $membership = Membership::factory()->create();
        $this->actingAsMembership($membership);

        return $membership;
    }

    private function actingAsMembership(Membership $membership): void
    {
        $this->post('/logout');
        $this->flushSession();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);
    }
}
