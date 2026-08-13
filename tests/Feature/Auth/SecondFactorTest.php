<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Identity\PendingSecondFactor;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\SecondFactorChallenge;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SecondFactorCode;
use App\Notifications\SecondFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * RF-AUT-007, 014, 015, 016 y RNF-AUT-011, 012.
 */
final class SecondFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_segundo_factor_el_acceso_no_cambia(): void
    {
        // La primera prueba de la tarea es que la tarea no rompe nada. La
        // mayoria de usuarios no tienen MFA y su camino debe ser identico.
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_con_segundo_factor_la_sesion_no_se_crea_todavia(): void
    {
        // RF-AUT-007. Es el requisito central: contrasena correcta NO es
        // sesion. Si esta falla, alguien con la contrasena robada entra sin
        // tocar el correo.
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ])->assertRedirect(route('auth.second-factor.challenge'));

        $this->assertGuest();
        $this->assertSame($usuario->id, session(PendingSecondFactor::USER_KEY));
    }

    public function test_el_codigo_correcto_completa_el_acceso(): void
    {
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $codigo = $this->iniciarVerificacion($usuario);

        $this->post('/verificacion', ['code' => $codigo])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_codigo_incorrecto_no_da_acceso(): void
    {
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $this->iniciarVerificacion($usuario);

        $this->post('/verificacion', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_el_codigo_no_se_guarda_en_claro(): void
    {
        // RNF-AUT-012. Un codigo de acceso guardado en claro es una
        // contrasena guardada en claro con otro nombre.
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $codigo = $this->iniciarVerificacion($usuario);

        $this->assertDatabaseMissing('second_factor_challenges', [
            'code_hash' => $codigo,
        ]);

        $this->assertDatabaseHas('second_factor_challenges', [
            'user_id' => $usuario->id,
            'code_hash' => SecondFactorCode::hashOf($codigo),
        ]);
    }

    public function test_el_codigo_no_sirve_dos_veces(): void
    {
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $codigo = $this->iniciarVerificacion($usuario);

        $this->post('/verificacion', ['code' => $codigo])
            ->assertRedirect(route('admin.dashboard'));

        $this->post('/logout');
        $this->flushSession();

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $this->post('/verificacion', ['code' => $codigo])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_el_codigo_vencido_no_sirve(): void
    {
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $codigo = $this->iniciarVerificacion($usuario);

        SecondFactorChallenge::query()
            ->where('user_id', $usuario->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->post('/verificacion', ['code' => $codigo])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_el_sexto_intento_agota_el_codigo(): void
    {
        // RNF-AUT-012: la verificacion limita intentos.
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $codigo = $this->iniciarVerificacion($usuario);

        for ($intento = 0; $intento < 5; $intento++) {
            $this->post('/verificacion', ['code' => '000000']);
        }

        // Aunque ahora acierte, el reto esta agotado.
        $this->post('/verificacion', ['code' => $codigo])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_un_codigo_de_recuperacion_tambien_sirve(): void
    {
        // RF-AUT-014.
        Notification::fake();

        $usuario = Membership::factory()->create()->user;
        $codigos = $this->activarSegundoFactor($usuario);

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $this->post('/verificacion', ['code' => $codigos[0]])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_codigo_de_recuperacion_no_sirve_dos_veces(): void
    {
        Notification::fake();

        $usuario = Membership::factory()->create()->user;
        $codigos = $this->activarSegundoFactor($usuario);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'password']);
        $this->post('/verificacion', ['code' => $codigos[0]]);
        $this->post('/logout');
        $this->flushSession();

        $this->post('/login', ['email' => $usuario->email, 'password' => 'password']);
        $this->post('/verificacion', ['code' => $codigos[0]])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_cancelar_cierra_la_sesion_parcial(): void
    {
        // RF-AUT-015.
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $this->iniciarVerificacion($usuario);

        $this->post('/verificacion/cancelar')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session(PendingSecondFactor::USER_KEY));

        // Y la pantalla deja de ser alcanzable.
        $this->get('/verificacion')->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_verificacion_no_es_alcanzable_sin_sesion_parcial(): void
    {
        $this->get('/verificacion')->assertRedirect(route('login'));
        $this->post('/verificacion', ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_los_cambios_de_mfa_quedan_auditados(): void
    {
        // RF-AUT-016.
        $usuario = Membership::factory()->create()->user;

        $this->actingAs($usuario)->post(route('account.security.enable'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $usuario->id,
            'action' => 'mfa.enabled',
        ]);

        $this->actingAs($usuario)->delete(route('account.security.disable'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $usuario->id,
            'action' => 'mfa.disabled',
        ]);
    }

    public function test_la_auditoria_no_guarda_el_codigo_introducido(): void
    {
        // RNF-AUT-012. El intento fallido se registra; el codigo, no.
        Notification::fake();

        $usuario = $this->usuarioConSegundoFactor();
        $this->iniciarVerificacion($usuario);

        $this->post('/verificacion', ['code' => '424242']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $usuario->id,
            'action' => 'mfa.verification_failed',
        ]);

        foreach (AuditLog::all() as $entrada) {
            $this->assertStringNotContainsString(
                '424242',
                json_encode($entrada->getAttributes(), JSON_THROW_ON_ERROR),
            );
        }
    }

    public function test_desactivar_borra_los_codigos_de_recuperacion(): void
    {
        $usuario = Membership::factory()->create()->user;
        $this->activarSegundoFactor($usuario);

        $this->assertDatabaseCount('mfa_recovery_codes', 8);

        $this->actingAs($usuario)->delete(route('account.security.disable'));

        $this->assertDatabaseCount('mfa_recovery_codes', 0);
    }

    private function usuarioConSegundoFactor(): User
    {
        $usuario = Membership::factory()->create()->user;
        $this->activarSegundoFactor($usuario);

        return $usuario->fresh();
    }

    /** @return list<string> */
    private function activarSegundoFactor(User $usuario): array
    {
        $respuesta = $this->actingAs($usuario)->post(route('account.security.enable'));

        $codigos = $respuesta->getSession()->get('recovery_codes');

        $this->assertIsArray($codigos, 'Activar el segundo factor debe devolver los codigos.');

        $this->post('/logout');
        $this->flushSession();

        return $codigos;
    }

    /**
     * Inicia sesion, llega a la pantalla de verificacion y devuelve el codigo
     * del correo.
     *
     * El codigo se extrae FUERA del closure de assertSentTo: si la
     * notificacion no se envia, el closure no corre y las aserciones de
     * dentro tampoco (T-017).
     */
    private function iniciarVerificacion(User $usuario): string
    {
        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $this->get('/verificacion')->assertOk();

        $codigo = null;

        Notification::assertSentTo(
            $usuario,
            SecondFactorCodeNotification::class,
            function (SecondFactorCodeNotification $aviso) use (&$codigo): bool {
                $codigo = $aviso->code->plain;

                return true;
            }
        );

        $this->assertNotNull($codigo, 'No se envio ningun codigo.');

        return $codigo;
    }
}
