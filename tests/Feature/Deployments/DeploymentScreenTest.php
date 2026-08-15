<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pantallas de aplicaciones. RF-AO-DEP-001 a 005.
 */
final class DeploymentScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_solo_muestra_lo_propio(): void
    {
        /*
         * RNF-GEN-005. Se cuentan FILAS y no se busca texto: con Inertia,
         * assertDontSee pasaria aunque la fila ajena viajara en las props, y
         * una fuga entre organizaciones quedaria en verde.
         */
        $membership = $this->admin();

        Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $this->versionPublicada($membership)->id,
        ]);

        Deployment::factory()->create();

        $this->get(route('admin.deployments.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Deployments/Index')
                ->has('deployments.data', 1)
            );
    }

    public function test_el_asistente_avisa_si_no_hay_version_publicada(): void
    {
        /*
         * RF-AO-DEP-003. Mostrar el formulario y fallar al enviar seria hacer
         * rellenar tres pasos para nada.
         */
        $membership = $this->admin();
        $survey = Survey::factory()->for($membership->organization)->create();

        $this->get(route('admin.deployments.create', $survey))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Deployments/Wizard')
                ->where('version', null)
            );
    }

    public function test_el_asistente_dice_que_alcances_admite_cada_canal(): void
    {
        /*
         * Los alcances los decide el SERVIDOR. Si el asistente los dedujera,
         * su criterio y el de DeploymentChannel divergirian el dia que se
         * anada un canal, y ofreceria combinaciones que el servidor rechaza.
         */
        $membership = $this->admin();
        $version = $this->versionPublicada($membership);

        $this->get(route('admin.deployments.create', $version->survey))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('version.number', 1)
                ->has('channels', 4)
                ->where('channels.0.value', DeploymentChannel::Kiosk->value)
                ->where('channels.0.requires_device', true)
                ->where('channels.0.scopes', [DeploymentScope::Device->value])
            );
    }

    public function test_crear_desde_el_asistente_funciona(): void
    {
        $membership = $this->admin();
        $version = $this->versionPublicada($membership);
        $branch = Branch::factory()->for($membership->organization)->create();

        $this->post(route('admin.deployments.store', $version->survey), [
            'channel' => DeploymentChannel::Qr->value,
            'scope' => DeploymentScope::Branch->value,
            'branch_ulid' => $branch->ulid,
        ])->assertRedirect(route('admin.deployments.index'));

        $this->assertDatabaseHas('deployments', [
            'survey_version_id' => $version->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_el_quiosco_sin_dispositivo_se_rechaza_con_mensaje(): void
    {
        // El guardian lanza; el controlador lo traduce a un error de campo en
        // vez de una pagina de error.
        $membership = $this->admin();
        $version = $this->versionPublicada($membership);

        $this->post(route('admin.deployments.store', $version->survey), [
            'channel' => DeploymentChannel::Kiosk->value,
            'scope' => DeploymentScope::Organization->value,
        ])->assertSessionHasErrors('channel');

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_un_dispositivo_ajeno_no_se_encuentra(): void
    {
        /*
         * El controlador acota por organizacion ANTES de pasar la entidad al
         * guardian: devolver null a un ULID ajeno no distingue "no existe" de
         * "es de otra organizacion", y esa diferencia es informacion.
         */
        $membership = $this->admin();
        $version = $this->versionPublicada($membership);
        $ajeno = Device::factory()->create();

        $this->post(route('admin.deployments.store', $version->survey), [
            'channel' => DeploymentChannel::Kiosk->value,
            'scope' => DeploymentScope::Device->value,
            'device_ulid' => $ajeno->ulid,
        ])->assertSessionHasErrors('channel');

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_una_aplicacion_ajena_no_se_suspende(): void
    {
        $this->admin();
        $ajena = Deployment::factory()->create();

        $this->post(route('admin.deployments.suspend', $ajena))->assertForbidden();

        $this->assertSame(DeploymentStatus::Active, $ajena->fresh()->status);
    }

    public function test_una_cerrada_no_admite_cambios(): void
    {
        // La Policy lo dice, asi que la pantalla puede preguntar lo mismo y
        // no ofrecer un boton que va a fallar.
        $membership = $this->admin();

        $cerrada = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $this->versionPublicada($membership)->id,
            'status' => DeploymentStatus::Closed,
            'closed_at' => now(),
        ]);

        $this->post(route('admin.deployments.activate', $cerrada))->assertForbidden();
    }

    /**
     * Entra por el formulario real, como el resto de las pruebas del proyecto.
     *
     * NO vale actingAs() con la sesion puesta a mano: EnsureActiveOrganization
     * guarda la membresia en los atributos de la peticion durante el acceso, y
     * saltarselo deja al controlador recibiendo un array donde espera un
     * modelo. El sintoma engana: "Call to a member function all() on array",
     * que no menciona sesiones por ningun lado.
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

    private function versionPublicada(Membership $membership): SurveyVersion
    {
        $survey = Survey::factory()->for($membership->organization)->create();

        return SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
        ]);
    }
}
