<?php

declare(strict_types=1);

namespace Tests\Feature\Kiosk;

use App\Application\Kiosk\ManageStationKey;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * La clave de estacion desde el panel. TASK-005 · RNF-AO-DEP-003.
 */
final class StationKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_clave_se_muestra_una_sola_vez(): void
    {
        /*
         * Es la unica ocasion en que existe fuera de la tableta: en la base
         * queda su hash. Quien recargue la pantalla ya no la vera.
         */
        [$membership, $device] = $this->dispositivo();

        $this->post(route('admin.devices.key.generate', $device))
            ->assertRedirect()
            ->assertSessionHas('station_key');

        // Y al volver a la pantalla ya no esta.
        $this->get(route('admin.branches.kiosks', $device->branch))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('station_key'));
    }

    public function test_la_clave_no_se_guarda_en_claro(): void
    {
        [$membership, $device] = $this->dispositivo();

        $respuesta = $this->post(route('admin.devices.key.generate', $device));
        $clave = $respuesta->getSession()->get('station_key');

        $this->assertNotNull($clave);
        $this->assertDatabaseMissing('devices', ['station_key_hash' => $clave]);
    }

    public function test_la_pantalla_manda_el_estado_de_la_clave_nunca_la_clave(): void
    {
        [$membership, $device] = $this->dispositivo();

        $this->post(route('admin.devices.key.generate', $device));

        $this->get(route('admin.branches.kiosks', $device->branch))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('devices.0.key.state', 'usable')
                ->missing('devices.0.key.hash')
                ->missing('devices.0.station_key_hash')
            );
    }

    public function test_revocar_no_borra_el_hash(): void
    {
        /*
         * Borrarlo dejaria un dispositivo indistinguible de uno que nunca se
         * configuro, y nadie sabria si la clave se retiro a proposito o se
         * perdio.
         */
        [$membership, $device] = $this->dispositivo();

        $this->post(route('admin.devices.key.generate', $device));
        $this->post(route('admin.devices.key.revoke', $device));

        $fresco = $device->fresh();

        $this->assertNotNull($fresco->station_key_hash);
        $this->assertNotNull($fresco->station_key_revoked_at);
        $this->assertSame('revoked', app(ManageStationKey::class)->status($fresco)['state']);
    }

    public function test_regenerar_levanta_una_revocacion(): void
    {
        // Si alguien revoca y luego regenera, es que quiere volver a usar el
        // dispositivo.
        [$membership, $device] = $this->dispositivo();

        $this->post(route('admin.devices.key.generate', $device));
        $this->post(route('admin.devices.key.revoke', $device));
        $this->post(route('admin.devices.key.generate', $device));

        $this->assertNull($device->fresh()->station_key_revoked_at);
    }

    public function test_la_clave_caduca_a_las_24_horas(): void
    {
        /*
         * Decision del area usuaria: 24 horas. Quien configura diez
         * ventanillas no puede volver al panel entre cada una, y mas alla de
         * un dia una clave apuntada en un papel deja de ser temporal.
         *
         * Caducar NO apaga las tabletas ya vinculadas: esas tienen su propia
         * credencial persistente.
         */
        [$membership, $device] = $this->dispositivo();

        $this->post(route('admin.devices.key.generate', $device));

        $this->travel(25)->hours();

        $this->assertSame('expired', app(ManageStationKey::class)->status($device->fresh())['state']);
    }

    public function test_generar_queda_auditado(): void
    {
        // RNF-AO-DEP-003.
        [$membership, $device] = $this->dispositivo();

        $this->post(route('admin.devices.key.generate', $device));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.station_key_generated',
            'user_id' => $membership->user_id,
        ]);
    }

    public function test_un_dispositivo_ajeno_no_se_toca(): void
    {
        $this->dispositivo();
        $ajeno = Device::factory()->create();

        $this->post(route('admin.devices.key.generate', $ajeno))->assertForbidden();
    }

    /** @return array{0: Membership, 1: Device} */
    private function dispositivo(): array
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $branch = Branch::factory()->for($membership->organization)->create();

        $device = Device::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
        ]);

        return [$membership, $device->fresh()];
    }
}
