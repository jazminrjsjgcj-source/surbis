<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invariantes que PostgreSQL debe hacer cumplir por si mismo.
 *
 * Ninguna de estas pruebas comprueba una pantalla. Comprueban que la regla
 * sobrevive a un import, a un script de mantenimiento y a un olvido en un
 * formulario. RNF-GEN-012.
 */
final class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dos_organizaciones_pueden_usar_el_mismo_codigo_de_sucursal(): void
    {
        // RNF-AO-BRA-002. El error clasico de multiempresa es hacer este
        // codigo unico globalmente: entonces la segunda organizacion que
        // quiera una sucursal "CENTRO" no puede darla de alta, y nadie
        // entiende por que.
        $primera = Organization::factory()->create();
        $segunda = Organization::factory()->create();

        Branch::factory()->for($primera)->create(['code' => 'CENTRO']);
        Branch::factory()->for($segunda)->create(['code' => 'CENTRO']);

        $this->assertSame(2, Branch::query()->where('code', 'CENTRO')->count());
    }

    public function test_una_sucursal_no_puede_repetir_codigo_en_su_organizacion(): void
    {
        $organizacion = Organization::factory()->create();

        Branch::factory()->for($organizacion)->create(['code' => 'CENTRO']);

        $this->expectException(QueryException::class);

        Branch::factory()->for($organizacion)->create(['code' => 'CENTRO']);
    }

    public function test_un_usuario_puede_pertenecer_a_varias_organizaciones(): void
    {
        // P-004.
        $usuario = User::factory()->create();

        Membership::factory()->for($usuario)->create();
        Membership::factory()->for($usuario)->create();

        $this->assertSame(2, $usuario->memberships()->count());
    }

    public function test_un_usuario_no_puede_tener_dos_membresias_en_la_misma_organizacion(): void
    {
        // RNF-AO-COL-003. Lo impide la base, no el formulario.
        $usuario = User::factory()->create();
        $organizacion = Organization::factory()->create();

        Membership::factory()->for($usuario)->for($organizacion)->create();

        $this->expectException(QueryException::class);

        Membership::factory()->for($usuario)->for($organizacion)->create();
    }

    public function test_una_persona_evaluable_no_necesita_cuenta(): void
    {
        // P-007. Quien atiende una ventanilla puede no tener usuario.
        $persona = StaffMember::factory()->create();

        $this->assertNull($persona->membership_id);
        $this->assertFalse($persona->hasUserAccount());
    }

    public function test_una_cuenta_no_puede_vincularse_a_dos_personas_evaluables(): void
    {
        $membresia = Membership::factory()->create();

        StaffMember::factory()
            ->create([
                'organization_id' => $membresia->organization_id,
                'membership_id' => $membresia->id,
            ]);

        $this->expectException(QueryException::class);

        StaffMember::factory()
            ->create([
                'organization_id' => $membresia->organization_id,
                'membership_id' => $membresia->id,
            ]);
    }
}
