<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Application\Deployments\ChangeDeploymentStatus;
use App\Application\Deployments\CreateDeployment;
use App\Application\Deployments\Exceptions\DeploymentRejected;
use App\Application\Deployments\ReassignDeployment;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas de las aplicaciones. RF-AO-DEP-002 a 006 · RNF-AO-DEP-001 y 003.
 */
final class DeploymentRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_borrador_no_se_despliega(): void
    {
        /*
         * RF-AO-DEP-003. Un borrador cambia cada vez que alguien escribe: si
         * se pudiera desplegar, dos personas contestarian encuestas distintas
         * creyendo que es la misma.
         */
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();
        $borrador = SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $this->expectException(DeploymentRejected::class);

        app(CreateDeployment::class)->execute(
            $membership->organization, $borrador, $membership->user,
            DeploymentChannel::PublicLink, DeploymentScope::Organization,
        );
    }

    public function test_el_quiosco_exige_dispositivo(): void
    {
        // Sin dispositivo, una respuesta no podria decir de que tableta vino.
        [$organization, $version, $user] = $this->publicada();

        $this->expectException(DeploymentRejected::class);

        app(CreateDeployment::class)->execute(
            $organization, $version, $user,
            DeploymentChannel::Kiosk, DeploymentScope::Organization,
        );
    }

    public function test_un_dispositivo_de_otra_organizacion_se_rechaza(): void
    {
        // RNF-GEN-005. Los ULID viajan al navegador: basta con enviar uno a
        // mano para intentarlo.
        [$organization, $version, $user] = $this->publicada();

        $ajeno = Device::factory()->create();

        $this->expectException(DeploymentRejected::class);

        app(CreateDeployment::class)->execute(
            $organization, $version, $user,
            DeploymentChannel::Kiosk, DeploymentScope::Device,
            ['device' => $ajeno],
        );
    }

    public function test_el_alcance_declarado_debe_coincidir(): void
    {
        // Declarar "sucursal" y pasar un dispositivo dejaria un deployment
        // que aplica en un sitio distinto del que alguien creia.
        [$organization, $version, $user] = $this->publicada();
        $branch = Branch::factory()->for($organization)->create();

        $this->expectException(DeploymentRejected::class);

        app(CreateDeployment::class)->execute(
            $organization, $version, $user,
            DeploymentChannel::Qr, DeploymentScope::Organization,
            ['branch' => $branch],
        );
    }

    public function test_la_base_impide_dos_alcances_a_la_vez(): void
    {
        /*
         * El CHECK de la base, no la validacion de PHP. Si la regla solo
         * viviera en el caso de uso, una importacion o la API futura podrian
         * saltarsela.
         */
        [$organization, $version] = $this->publicada();
        $branch = Branch::factory()->for($organization)->create();
        $device = Device::factory()->for($branch)->create([
            'organization_id' => $organization->id,
        ]);

        $this->expectException(QueryException::class);

        Deployment::query()->create([
            'organization_id' => $organization->id,
            'survey_version_id' => $version->id,
            'channel' => DeploymentChannel::Qr,
            'scope' => DeploymentScope::Branch,
            'branch_id' => $branch->id,
            'device_id' => $device->id,
            'status' => DeploymentStatus::Active,
        ]);
    }

    public function test_las_fechas_no_pueden_ir_al_reves(): void
    {
        // RNF-AO-DEP-001.
        [$organization, $version, $user] = $this->publicada();

        $this->expectException(DeploymentRejected::class);

        app(CreateDeployment::class)->execute(
            $organization, $version, $user,
            DeploymentChannel::PublicLink, DeploymentScope::Organization,
            [], now()->addWeek(), now(),
        );
    }

    public function test_una_version_publicada_admite_varios_deployments(): void
    {
        // Decision del area usuaria: si hace falta en cinco sucursales, son
        // cinco deployments.
        [$organization, $version, $user] = $this->publicada();

        foreach (range(1, 3) as $i) {
            $branch = Branch::factory()->for($organization)->create();

            app(CreateDeployment::class)->execute(
                $organization, $version, $user,
                DeploymentChannel::Qr, DeploymentScope::Branch,
                ['branch' => $branch],
            );
        }

        $this->assertSame(3, Deployment::query()->count());
    }

    public function test_un_cerrado_no_se_reabre(): void
    {
        // Reabrirlo mezclaria dos periodos de aplicacion en el mismo
        // registro, y las respuestas no podrian distinguirlos.
        $deployment = Deployment::factory()->create();
        $user = Membership::factory()->create()->user;

        app(ChangeDeploymentStatus::class)->close($deployment, $user);

        $this->expectException(DeploymentRejected::class);

        app(ChangeDeploymentStatus::class)->activate($deployment->fresh(), $user);
    }

    public function test_reasignar_cierra_el_anterior_y_crea_otro(): void
    {
        /*
         * RF-AO-DEP-006. Cambiar el alcance en sitio dejaria respuestas ya
         * recibidas apuntando a un lugar donde nunca se dieron.
         */
        [$organization, $version, $user] = $this->publicada();

        [$original] = app(CreateDeployment::class)->execute(
            $organization, $version, $user,
            DeploymentChannel::Qr, DeploymentScope::Organization,
        );

        $branch = Branch::factory()->for($organization)->create();

        [$nuevo] = app(ReassignDeployment::class)->execute(
            $original, $organization, $user, DeploymentScope::Branch, ['branch' => $branch],
        );

        $this->assertSame(DeploymentStatus::Closed, $original->fresh()->status);
        $this->assertNotNull($original->fresh()->closed_at);

        $this->assertNotSame($original->id, $nuevo->id);
        $this->assertSame($branch->id, $nuevo->branch_id);

        // Y NO cambia de version publicada: eso es crear otro a proposito.
        $this->assertSame($version->id, $nuevo->survey_version_id);
    }

    public function test_activo_no_es_lo_mismo_que_aplicando(): void
    {
        /*
         * Uno "activo" con fecha de inicio manana todavia no recibe
         * respuestas. Confundir las dos cosas haria que el listado mintiera.
         */
        $futuro = Deployment::factory()->create(['starts_at' => now()->addDay()]);
        $expirado = Deployment::factory()->create(['ends_at' => now()->subDay()]);
        $vigente = Deployment::factory()->create();

        $this->assertFalse($futuro->isApplying());
        $this->assertSame('not_started', $futuro->notApplyingReason());

        $this->assertFalse($expirado->isApplying());
        $this->assertSame('expired', $expirado->notApplyingReason());

        $this->assertTrue($vigente->isApplying());
        $this->assertNull($vigente->notApplyingReason());
    }

    public function test_crear_queda_auditado(): void
    {
        // RNF-AO-DEP-003.
        [$organization, $version, $user] = $this->publicada();

        app(CreateDeployment::class)->execute(
            $organization, $version, $user,
            DeploymentChannel::PublicLink, DeploymentScope::Organization,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deployment.created',
            'user_id' => $user->id,
        ]);
    }

    /** @return array{0: Organization, 1: SurveyVersion, 2: User} */
    private function publicada(): array
    {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
        ]);

        return [$membership->organization, $version, $membership->user];
    }
}
