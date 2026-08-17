<?php

declare(strict_types=1);

namespace Tests\Feature\Kiosk;

use App\Domain\Identity\Models\Membership;
use App\Domain\Kiosk\OfflineLimits;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los limites de trabajo sin conexion. Decision del area usuaria, 18 ago 2026.
 */
final class OfflineLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_defecto_siete_dias_y_cinco_mil(): void
    {
        $limites = app(OfflineLimits::class)->of($this->organizacion());

        $this->assertSame(7, $limites['days']);
        $this->assertSame(5000, $limites['count']);
    }

    public function test_se_pueden_ajustar_por_organizacion(): void
    {
        $organizacion = $this->organizacion(['offline' => ['days' => 3, 'count' => 500]]);

        $limites = app(OfflineLimits::class)->of($organizacion);

        $this->assertSame(3, $limites['days']);
        $this->assertSame(500, $limites['count']);
    }

    public function test_no_se_pueden_desactivar(): void
    {
        /*
         * Quitar el limite del todo convertiria un problema de conexion en
         * una perdida silenciosa: una cola de meses en un dispositivo que
         * puede perderse o romperse.
         */
        $organizacion = $this->organizacion(['offline' => ['days' => 0, 'count' => 0]]);

        $limites = app(OfflineLimits::class)->of($organizacion);

        $this->assertGreaterThan(0, $limites['days']);
        $this->assertGreaterThan(0, $limites['count']);
    }

    public function test_bloquea_al_alcanzar_el_limite_de_cantidad(): void
    {
        $organizacion = $this->organizacion(['offline' => ['count' => 100]]);

        $estado = app(OfflineLimits::class)->assess($organizacion, 100, 0);

        $this->assertSame('blocked', $estado['state']);
    }

    public function test_bloquea_al_alcanzar_el_limite_de_dias(): void
    {
        // "Lo que ocurra PRIMERO": con pocas respuestas pero muy viejas,
        // tambien se bloquea.
        $organizacion = $this->organizacion(['offline' => ['days' => 7]]);

        $estado = app(OfflineLimits::class)->assess($organizacion, 3, 7);

        $this->assertSame('blocked', $estado['state']);
    }

    public function test_avisa_antes_de_bloquear(): void
    {
        /*
         * Al 80% queda margen para llamar a alguien; al 95% ya no.
         *
         * El aviso es SOLO para el colaborador: quien contesta no puede hacer
         * nada con esa informacion.
         */
        $organizacion = $this->organizacion(['offline' => ['count' => 100]]);

        $this->assertSame('ok', app(OfflineLimits::class)->assess($organizacion, 50, 0)['state']);
        $this->assertSame('warning', app(OfflineLimits::class)->assess($organizacion, 85, 0)['state']);
    }

    public function test_el_estado_lleva_los_numeros_que_hacen_falta(): void
    {
        // Un "hay problemas" sin numeros no permite decidir si seguir o
        // llamar a alguien.
        $estado = app(OfflineLimits::class)->assess($this->organizacion(), 120, 2);

        $this->assertSame(120, $estado['pending']);
        $this->assertSame(2, $estado['oldest_days']);
        $this->assertSame(7, $estado['limit_days']);
        $this->assertSame(5000, $estado['limit_count']);
        $this->assertGreaterThan(0, $estado['capacity']);
    }

    /** @param array<string, mixed> $settings */
    private function organizacion(array $settings = []): Organization
    {
        $membership = Membership::factory()->create();

        $membership->organization->forceFill(['settings' => $settings])->save();

        return $membership->organization->fresh();
    }
}
