<?php

declare(strict_types=1);

namespace Tests\Feature\Kiosk;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Application\Kiosk\OpenKioskSession;
use App\Application\Kiosk\ResolveStation;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Kiosk\Models\KioskSession;
use App\Domain\Kiosk\StationKey;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\StaffMember;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sesiones de quiosco. RF-COL-001 a 007 · RNF-COL-001 y 002.
 */
final class KioskSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_clave_valida_resuelve_su_dispositivo(): void
    {
        [$device, $clave] = $this->dispositivoConClave();

        $resuelto = app(ResolveStation::class)->device($clave);

        $this->assertSame($device->id, $resuelto->id);
    }

    public function test_la_clave_se_reconoce_sin_guiones_y_en_minusculas(): void
    {
        /*
         * Alguien la teclea en una tableta, probablemente con el dedo.
         * Rechazarla por el formato seria hacer perder el tiempo a quien la
         * escribio bien.
         */
        [$device, $clave] = $this->dispositivoConClave();

        $sinFormato = strtolower(str_replace('-', '', $clave));

        $this->assertSame($device->id, app(ResolveStation::class)->device($sinFormato)->id);
    }

    public function test_una_clave_revocada_no_vale(): void
    {
        // Y da el MISMO error que una desconocida: distinguirlas diria si esa
        // clave existio alguna vez.
        [$device, $clave] = $this->dispositivoConClave();

        $device->forceFill(['station_key_revoked_at' => now()])->save();

        $this->expectException(StationNotReady::class);

        app(ResolveStation::class)->device($clave);
    }

    public function test_la_clave_no_se_guarda_en_claro(): void
    {
        [$device, $clave] = $this->dispositivoConClave();

        $this->assertNotSame($clave, $device->station_key_hash);
        $this->assertDatabaseMissing('devices', ['station_key_hash' => $clave]);
    }

    public function test_sin_deployment_la_estacion_no_esta_lista(): void
    {
        // RF-COL-007.
        [$device] = $this->dispositivoConClave();

        $this->expectException(StationNotReady::class);

        app(ResolveStation::class)->deployment($device);
    }

    public function test_solo_vale_el_deployment_de_est_e_dispositivo(): void
    {
        /*
         * Sin herencia por sucursal ni por area. Decision del area usuaria,
         * 18 ago 2026.
         *
         * Un deployment de quiosco SIEMPRE lleva dispositivo —la base lo
         * exige con deployments_kiosk_needs_device— asi que no puede haber
         * uno de sucursal del que heredar. Configurar muchas tabletas de
         * golpe es una operacion en LOTE, no un deployment compartido.
         */
        [$device] = $this->dispositivoConClave();
        $version = $this->versionPublicada($device->organization_id);

        // El de OTRO dispositivo de la misma sucursal no sirve.
        $otro = Device::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $device->branch_id,
        ]);

        Deployment::factory()->create([
            'organization_id' => $device->organization_id,
            'survey_version_id' => $version->id,
            'channel' => DeploymentChannel::Kiosk,
            'scope' => DeploymentScope::Device,
            'device_id' => $otro->id,
        ]);

        $this->expectException(StationNotReady::class);

        app(ResolveStation::class)->deployment($device->fresh());
    }

    public function test_un_deployment_suspendido_no_prepara_la_estacion(): void
    {
        // "Existe" no es "esta aplicando", y lo que hay que hacer es distinto.
        [$device] = $this->dispositivoConClave();
        $version = $this->versionPublicada($device->organization_id);

        Deployment::factory()->create([
            'organization_id' => $device->organization_id,
            'survey_version_id' => $version->id,
            'channel' => DeploymentChannel::Kiosk,
            'scope' => DeploymentScope::Device,
            'device_id' => $device->id,
            'status' => DeploymentStatus::Suspended,
        ]);

        $this->expectException(StationNotReady::class);

        app(ResolveStation::class)->deployment($device->fresh());
    }

    public function test_cambiar_de_colaborador_sustituye_la_sesion(): void
    {
        /*
         * DECISION DEL AREA USUARIA. Reanudar atribuiria a la primera persona
         * lo que evaluo la segunda, y ese error no se ve: los datos entran,
         * solo que en la cuenta equivocada.
         */
        [$device, $deployment] = $this->estacionLista();

        $primero = StaffMember::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $device->branch_id,
        ]);
        $segundo = StaffMember::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $device->branch_id,
        ]);

        $sesionUno = app(OpenKioskSession::class)->execute($device, $deployment, $primero);
        $sesionDos = app(OpenKioskSession::class)->execute($device, $deployment, $segundo);

        $this->assertNotSame($sesionUno->id, $sesionDos->id);
        $this->assertNotNull($sesionUno->fresh()->closed_at);
        $this->assertSame('replaced', $sesionUno->fresh()->closed_reason);
        $this->assertTrue($sesionDos->isOpen());
    }

    public function test_el_mismo_colaborador_reanuda_su_sesion(): void
    {
        /*
         * Volver a preparar con la misma persona no es un cambio de turno: es
         * alguien que recargo la pantalla. Cerrar y abrir ahi partiria el
         * turno en dos por nada.
         */
        [$device, $deployment] = $this->estacionLista();

        $persona = StaffMember::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $device->branch_id,
        ]);

        $primera = app(OpenKioskSession::class)->execute($device, $deployment, $persona);
        $segunda = app(OpenKioskSession::class)->execute($device, $deployment, $persona);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, KioskSession::query()->count());
    }

    public function test_la_base_impide_dos_sesiones_abiertas(): void
    {
        /*
         * El indice unico, no la validacion de PHP. Dos sesiones en la misma
         * tableta harian que las respuestas se atribuyeran a cualquiera de
         * las dos, y el fallo no daria error.
         */
        [$device, $deployment] = $this->estacionLista();

        KioskSession::query()->create([
            'organization_id' => $device->organization_id,
            'device_id' => $device->id,
            'deployment_id' => $deployment->id,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        KioskSession::query()->create([
            'organization_id' => $device->organization_id,
            'device_id' => $device->id,
            'deployment_id' => $deployment->id,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    public function test_cerrar_una_sesion_no_la_borra(): void
    {
        // Las respuestas de ese turno apuntan a ella y tienen que seguir
        // explicando de quien eran.
        [$device, $deployment] = $this->estacionLista();

        $sesion = app(OpenKioskSession::class)->execute($device, $deployment);

        app(OpenKioskSession::class)->close($sesion);

        $this->assertDatabaseHas('kiosk_sessions', ['id' => $sesion->id]);
        $this->assertNotNull($sesion->fresh()->closed_at);
    }

    /** @return array{0: Device, 1: string} */
    private function dispositivoConClave(): array
    {
        $membership = Membership::factory()->create();
        $branch = Branch::factory()->for($membership->organization)->create();

        $keys = app(StationKey::class);
        $clave = $keys->generate();

        $device = Device::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
            'station_key_hash' => $keys->hash($clave),
            'station_key_set_at' => now(),
        ]);

        return [$device->fresh(), $clave];
    }

    /** @return array{0: Device, 1: Deployment} */
    private function estacionLista(): array
    {
        [$device] = $this->dispositivoConClave();
        $version = $this->versionPublicada($device->organization_id);

        $deployment = Deployment::factory()->create([
            'organization_id' => $device->organization_id,
            'survey_version_id' => $version->id,
            'channel' => DeploymentChannel::Kiosk,
            'scope' => DeploymentScope::Device,
            'device_id' => $device->id,
        ]);

        return [$device, $deployment->fresh()];
    }

    private function versionPublicada(int $organizationId): SurveyVersion
    {
        $survey = Survey::factory()->create(['organization_id' => $organizationId]);

        return SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $organizationId,
        ]);
    }
}
