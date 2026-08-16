<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Application\Deployments\PublicToken;
use App\Application\Deployments\RenderQrCode;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * QR y enlace publico. RF-AO-DEP-008, 009 y 010 · RNF-AO-DEP-002.
 */
final class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_qr_sale_en_svg(): void
    {
        /*
         * SVG y no PNG: RF-AO-DEP-009 pide formato apto para IMPRESION, y un
         * cartel puede acabar en A5 o en A2. Un PNG de tamano fijo se pixela
         * al ampliarlo.
         */
        $svg = app(RenderQrCode::class)->svg('https://example.test/e/abc');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_el_enlace_publico_abre_sin_sesion(): void
    {
        // Quien escanea un cartel no ha iniciado sesion en nada.
        $tokens = app(PublicToken::class);
        $token = $tokens->generate();

        Deployment::factory()->create(['public_token_hash' => $tokens->hash($token)]);

        $this->get(route('public.survey', $token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/Survey')
                ->where('available', true)
            );
    }

    public function test_un_token_inexistente_responde_igual_que_uno_caducado(): void
    {
        /*
         * RNF-AO-DEP-002. Distinguirlos convertiria la URL en un comprobador:
         * probando tokens se sabria cuales existen, y con eso se pueden
         * buscar los que si valen.
         */
        $tokens = app(PublicToken::class);
        $caducado = $tokens->generate();

        Deployment::factory()->create([
            'public_token_hash' => $tokens->hash($caducado),
            'status' => DeploymentStatus::Closed,
            'closed_at' => now(),
        ]);

        $respuestaCaducado = $this->get(route('public.survey', $caducado));
        $respuestaInexistente = $this->get(route('public.survey', $tokens->generate()));

        /*
         * Se comprueba 'survey', no 'surveyName'.
         *
         * La pantalla dejo de ser un marcador: ahora recibe la encuesta
         * entera para pintarla. Cuando no esta disponible llega null, y eso
         * es lo que importa: ni el nombre ni las preguntas viajan.
         */
        $respuestaCaducado->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('available', false)->where('survey', null)
        );

        $respuestaInexistente->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('available', false)->where('survey', null)
        );
    }

    public function test_sin_el_token_en_claro_no_se_puede_descargar(): void
    {
        /*
         * El QR codifica la URL completa, y esa URL lleva el token. En la
         * base solo esta el hash, asi que sin el flash de sesion no hay nada
         * que dibujar.
         *
         * 410 y no 404: el recurso existe, pero ya no se puede obtener.
         */
        $membership = $this->admin();

        $deployment = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
        ]);

        $this->get(route('admin.deployments.qr.svg', $deployment))->assertStatus(410);
    }

    public function test_regenerar_invalida_el_anterior(): void
    {
        // RF-AO-DEP-010. Los carteles ya impresos dejan de funcionar, y por
        // eso la pantalla pide confirmacion.
        $membership = $this->admin();
        $tokens = app(PublicToken::class);
        $viejo = $tokens->generate();

        $deployment = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'public_token_hash' => $tokens->hash($viejo),
        ]);

        $this->post(route('admin.deployments.qr.regenerate', $deployment))->assertRedirect();

        $this->assertNotSame($tokens->hash($viejo), $deployment->fresh()->public_token_hash);

        // Y el enlace viejo deja de abrir.
        $this->get(route('public.survey', $viejo))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('available', false));
    }

    public function test_regenerar_queda_auditado(): void
    {
        // RNF-AO-DEP-003.
        $membership = $this->admin();

        $deployment = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
        ]);

        $this->post(route('admin.deployments.qr.regenerate', $deployment));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deployment.token_regenerated',
            'user_id' => $membership->user->id,
        ]);
    }

    public function test_una_aplicacion_ajena_no_regenera(): void
    {
        $this->admin();
        $ajena = Deployment::factory()->create();

        $this->post(route('admin.deployments.qr.regenerate', $ajena))->assertForbidden();
    }

    /**
     * Entra por el formulario real, como el resto de las pruebas del proyecto.
     */
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
