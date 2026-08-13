<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Domain\Identity\Models\ConfidentialAccessGrant;
use App\Domain\Identity\Models\SupportGrant;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RA-001 y P-005.
 *
 * Una concesion caducada que sigue funcionando no da ningun error: la pantalla
 * carga, el dato se muestra, y el permiso llevaba meses vencido. Por eso la
 * vigencia se prueba, y se prueba tambien el caso vencido, que es el que se
 * olvida.
 */
final class GrantValidityTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_concesion_vigente_es_efectiva(): void
    {
        $grant = $this->crearSupportGrant(now()->subDay(), now()->addDay());

        $this->assertTrue($grant->isEffective());
        $this->assertSame(1, SupportGrant::query()->effective()->count());
    }

    public function test_una_concesion_vencida_no_es_efectiva(): void
    {
        $grant = $this->crearSupportGrant(now()->subMonth(), now()->subDay());

        $this->assertFalse($grant->isEffective());
        $this->assertSame(0, SupportGrant::query()->effective()->count());
    }

    public function test_una_concesion_revocada_no_es_efectiva(): void
    {
        $grant = $this->crearSupportGrant(now()->subDay(), now()->addDay());
        $grant->update(['revoked_at' => now()]);

        $this->assertFalse($grant->fresh()->isEffective());
        $this->assertSame(0, SupportGrant::query()->effective()->count());
    }

    public function test_una_concesion_revocada_se_conserva(): void
    {
        // El area usuaria lo pidio expresamente: los permisos vencidos o
        // revocados se conservan para auditoria.
        $grant = $this->crearSupportGrant(now()->subDay(), now()->addDay());
        $grant->update(['revoked_at' => now()]);

        $this->assertDatabaseCount('support_grants', 1);
    }

    public function test_la_base_rechaza_una_concesion_que_vence_antes_de_empezar(): void
    {
        $this->expectException(QueryException::class);

        $this->crearSupportGrant(now(), now()->subDay());
    }

    public function test_el_acceso_confidencial_usa_la_misma_regla_de_vigencia(): void
    {
        $organizacion = Organization::factory()->create();
        $usuario = User::factory()->create();

        ConfidentialAccessGrant::query()->create([
            'organization_id' => $organizacion->id,
            'user_id' => $usuario->id,
            'reason' => 'Atencion de solicitud de derechos ARCO',
            'granted_by' => User::factory()->create()->id,
            'granted_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame(0, ConfidentialAccessGrant::query()->effective()->count());
    }

    private function crearSupportGrant(mixed $desde, mixed $hasta): SupportGrant
    {
        return SupportGrant::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'user_id' => User::factory()->platformAdmin()->create()->id,
            'reason' => 'Revision de incidencia reportada por la organizacion',
            'granted_by' => User::factory()->platformAdmin()->create()->id,
            'granted_at' => $desde,
            'expires_at' => $hasta,
        ]);
    }
}
