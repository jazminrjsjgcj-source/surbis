<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Application\Deployments\ActivateBranchKiosks;
use App\Application\Deployments\ChangeDeploymentStatus;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El interruptor de sucursal. Decision del area usuaria, 18 ago 2026.
 *
 * NO es un deployment de sucursal: es una operacion en LOTE sobre los
 * deployments de sus dispositivos. Por debajo siguen existiendo K-001,
 * K-002, K-003, cada uno con sus sesiones y su revocacion.
 */
final class BranchKiosksTest extends TestCase
{
    use RefreshDatabase;

    public function test_activar_crea_un_deployment_por_dispositivo(): void
    {
        [$branch, $version, $actor] = $this->sucursalCon(3);

        $resultado = app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);

        $this->assertSame(3, $resultado['created']);
        $this->assertSame(3, Deployment::query()->count());

        // Cada uno con SU dispositivo: ninguno de sucursal.
        foreach (Deployment::query()->get() as $deployment) {
            $this->assertSame(DeploymentScope::Device, $deployment->scope);
            $this->assertNotNull($deployment->device_id);
        }
    }

    public function test_con_otra_encuesta_se_cierra_y_se_crea_uno_nuevo(): void
    {
        /*
         * Activar "toda la sucursal" y que tres tabletas siguieran con otra
         * encuesta seria un resultado que nadie espera.
         *
         * Y se cierra en vez de editarse: las respuestas ya recibidas siguen
         * apuntando a donde se dieron (RF-AO-DEP-006).
         */
        [$branch, $version, $actor] = $this->sucursalCon(2);
        $otra = $this->versionPublicada($branch->organization_id, 2);

        app(ActivateBranchKiosks::class)->activate($branch, $otra, $actor);
        $resultado = app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);

        $this->assertSame(2, $resultado['replaced']);
        $this->assertSame(4, Deployment::query()->count());
        $this->assertSame(2, Deployment::query()->whereNotNull('closed_at')->count());
    }

    public function test_reactivar_la_misma_version_no_toca_nada(): void
    {
        /*
         * Reemplazarlo cerraria una sesion viva y partiria el turno de
         * alguien por una operacion que no cambia nada.
         */
        [$branch, $version, $actor] = $this->sucursalCon(2);

        app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);
        $resultado = app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);

        $this->assertSame(0, $resultado['created']);
        $this->assertSame(0, $resultado['replaced']);
        $this->assertSame(2, Deployment::query()->count());
    }

    public function test_el_interruptor_dice_activo_cuando_lo_estan_todos(): void
    {
        [$branch, $version, $actor] = $this->sucursalCon(3);

        app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);

        $estado = app(ActivateBranchKiosks::class)->state($branch);

        $this->assertSame('active', $estado['state']);
        $this->assertSame(3, $estado['total']);
        $this->assertSame(3, $estado['active']);
    }

    public function test_desactivar_una_tableta_deja_el_interruptor_en_parcial(): void
    {
        /*
         * "Parcial" NO es un estado que alguien ponga: es lo que queda al
         * desactivar una tableta suelta.
         *
         * Por eso se calcula y no se guarda: una columna con este valor se
         * desincronizaria en cuanto alguien tocara un deployment por su
         * cuenta.
         */
        [$branch, $version, $actor] = $this->sucursalCon(3);

        app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);

        $uno = Deployment::query()->first();
        app(ChangeDeploymentStatus::class)->suspend($uno, $actor);

        $estado = app(ActivateBranchKiosks::class)->state($branch);

        $this->assertSame('partial', $estado['state']);
        $this->assertSame(2, $estado['active']);
    }

    public function test_suspender_la_sucursal_apaga_todos(): void
    {
        // Suspende, no cierra: una sucursal se apaga por obras y despues se
        // vuelve a encender.
        [$branch, $version, $actor] = $this->sucursalCon(3);

        app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);
        $afectados = app(ActivateBranchKiosks::class)->suspend($branch, $actor);

        $this->assertSame(3, $afectados);
        $this->assertSame('inactive', app(ActivateBranchKiosks::class)->state($branch)['state']);

        // Y NINGUNO cerrado: se pueden volver a activar.
        $this->assertSame(0, Deployment::query()->whereNotNull('closed_at')->count());
    }

    public function test_una_tableta_nueva_nace_sin_deployment(): void
    {
        /*
         * Decision del area usuaria: heredar automaticamente significaria que
         * una tableta empieza a recoger respuestas sin que nadie lo
         * decidiera.
         */
        [$branch, $version, $actor] = $this->sucursalCon(2);

        app(ActivateBranchKiosks::class)->activate($branch, $version, $actor);

        Device::factory()->for($branch)->create([
            'organization_id' => $branch->organization_id,
        ]);

        $estado = app(ActivateBranchKiosks::class)->state($branch);

        $this->assertSame('partial', $estado['state']);
        $this->assertSame(3, $estado['total']);
        $this->assertSame(2, $estado['active']);
    }

    /** @return array{0: Branch, 1: SurveyVersion, 2: User} */
    private function sucursalCon(int $dispositivos): array
    {
        $membership = Membership::factory()->create();
        $branch = Branch::factory()->for($membership->organization)->create();

        foreach (range(1, $dispositivos) as $i) {
            Device::factory()->for($branch)->create([
                'organization_id' => $membership->organization_id,
            ]);
        }

        return [
            $branch->fresh(),
            $this->versionPublicada($membership->organization_id, 1),
            $membership->user,
        ];
    }

    private function versionPublicada(int $organizationId, int $numero): SurveyVersion
    {
        $survey = Survey::factory()->create(['organization_id' => $organizationId]);

        return SurveyVersion::factory()->for($survey)->published($numero)->create([
            'organization_id' => $organizationId,
        ]);
    }
}
