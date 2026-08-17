<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\SecondFactorAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * El segundo factor solo se ofrece si el correo llega. P-013.
 */
final class SecondFactorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_correo_configurado_no_esta_disponible(): void
    {
        /*
         * Con MAIL_MAILER=log el correo se escribe en un archivo del
         * servidor. Activar el MFA dejaria a esa persona fuera de su propia
         * cuenta: la pantalla le pediria un codigo que nunca va a recibir.
         */
        config(['mail.default' => 'log']);

        $this->assertFalse(app(SecondFactorAvailability::class)->isAvailable());
    }

    public function test_con_correo_real_si_esta_disponible(): void
    {
        config(['mail.default' => 'smtp']);

        $this->assertTrue(app(SecondFactorAvailability::class)->isAvailable());
    }

    public function test_el_servidor_lo_impide_no_solo_la_pantalla(): void
    {
        /*
         * Que el boton no aparezca no impide enviar la peticion a mano, y el
         * resultado seria una cuenta bloqueada de verdad.
         */
        config(['mail.default' => 'log']);

        $membership = $this->admin();

        $this->post(route('account.security.enable'))
            ->assertSessionHasErrors('mfa');

        $this->assertFalse($membership->user->fresh()->hasMfaEnabled());
    }

    public function test_la_pantalla_dice_por_que_no_se_puede(): void
    {
        // "No disponible" sin motivo hace que alguien abra una incidencia.
        config(['mail.default' => 'log']);

        $this->admin();

        $this->get(route('account.security'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('available', false)
                ->where('unavailableReason', 'mail_not_configured')
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
