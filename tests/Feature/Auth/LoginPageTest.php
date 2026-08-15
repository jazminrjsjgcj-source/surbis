<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * La pantalla de acceso, ahora en React.
 *
 * Estas pruebas comprueban PROPS, no marcado. Es la ganancia del cambio: las
 * versiones anteriores afirmaban sobre `for="email"` y `role="alert"`, asi que
 * cualquier rediseno las rompia aunque el comportamiento fuera el mismo.
 *
 * Lo que ya no se puede comprobar aqui —etiquetas asociadas, aria-describedby,
 * orden de los font-face— no desaparece: pasa a necesitar una prueba de
 * navegador. Queda anotado como deuda, no como resuelto.
 */
final class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_acceso_se_sirve_con_inertia(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_la_direccion_del_texto_se_resuelve_en_el_servidor(): void
    {
        /*
         * Sigue decidiendose en el servidor, como en Blade. Si se resolviera
         * en React, la primera pintada saldria en la direccion equivocada y
         * el arabe daria un salto visible al cargar.
         */
        $this->get('/login')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dir', 'ltr')
                ->where('locale', 'es')
            );
    }

    public function test_cambiar_a_arabe_invierte_el_documento(): void
    {
        app()->setLocale('ar');

        $this->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('dir', 'rtl'));
    }

    public function test_las_traducciones_viajan_como_props(): void
    {
        // Sin esto, los textos habria que duplicarlos en el cliente: dos
        // verdades sobre lo mismo, que es como acaban diciendo cosas
        // distintas.
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('translations.interface.login.submit', __('interface.login.submit'))
                ->where('translations.auth.failed', __('auth.failed'))
            );
    }

    public function test_la_url_de_recuperacion_viaja_como_prop(): void
    {
        // React no conoce las rutas nombradas de Laravel. Escribirla en el
        // componente crearia una segunda verdad sobre la misma direccion.
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('forgotUrl', route('password.request'))
            );
    }

    public function test_el_acceso_sigue_funcionando_igual(): void
    {
        // El comportamiento no cambia al cambiar de capa de presentacion.
        // Las pruebas de LoginTest lo cubren entero; esta es el recordatorio
        // de que siguen valiendo.
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($membership->user);
    }

    public function test_un_error_de_credenciales_llega_al_componente(): void
    {
        $this->from('/login')
            ->post('/login', [
                'email' => 'nadie@example.test',
                'password' => 'incorrecta',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }
}
