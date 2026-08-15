<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\Exceptions\VersionConflict;
use App\Application\Surveys\LockVersion;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bloqueo optimista del borrador.
 *
 * Decision del area usuaria, 14 ago 2026: dos administradores editando el
 * mismo borrador no pueden pisarse en silencio.
 */
final class LockVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_borrador_nuevo_empieza_en_cero(): void
    {
        $version = SurveyVersion::factory()->create();

        $this->assertSame(0, (int) $version->lock_version);
    }

    public function test_reclamar_incrementa_la_version(): void
    {
        $version = SurveyVersion::factory()->create();

        $nueva = app(LockVersion::class)->claim($version, 0);

        $this->assertSame(1, $nueva);
        $this->assertSame(1, (int) $version->fresh()->lock_version);
    }

    public function test_reclamar_con_una_version_vieja_lanza_conflicto(): void
    {
        $version = SurveyVersion::factory()->create();

        // Alguien guardo mientras tanto.
        app(LockVersion::class)->claim($version, 0);

        $this->expectException(VersionConflict::class);

        app(LockVersion::class)->claim($version->fresh(), 0);
    }

    public function test_el_conflicto_lleva_el_estado_actual(): void
    {
        // Para que la pantalla pueda mostrar lo que hay ahora sin una segunda
        // peticion, y para que el cliente sepa contra que numero reintentar.
        $version = SurveyVersion::factory()->create();
        app(LockVersion::class)->claim($version, 0);

        try {
            app(LockVersion::class)->claim($version->fresh(), 0);
            $this->fail('Se esperaba un conflicto.');
        } catch (VersionConflict $conflicto) {
            $this->assertSame(0, $conflicto->expected);
            $this->assertSame(1, $conflicto->actual);
            $this->assertSame($version->id, $conflicto->current->id);
        }
    }

    public function test_de_dos_escrituras_simultaneas_solo_gana_una(): void
    {
        /*
         * El nucleo del bloqueo optimista.
         *
         * La comprobacion y el incremento ocurren en UNA sentencia
         * condicionada, no en un leer-comparar-escribir: entre la lectura y
         * la escritura cabe otra peticion entera, y ahi es donde este
         * mecanismo dejaria de proteger sin dar ningun aviso.
         *
         * Se simula con dos instancias del mismo registro, que es lo que
         * tendrian dos peticiones distintas.
         */
        $version = SurveyVersion::factory()->create();

        $primera = SurveyVersion::query()->findOrFail($version->id);
        $segunda = SurveyVersion::query()->findOrFail($version->id);

        app(LockVersion::class)->claim($primera, 0);

        $this->expectException(VersionConflict::class);

        app(LockVersion::class)->claim($segunda, 0);
    }

    public function test_reintentar_con_la_version_nueva_funciona(): void
    {
        /*
         * "Sobrescribir lo del otro" no se salta la comprobacion: relee y
         * reintenta con el numero nuevo. No existe puerta trasera, y es
         * deliberado — un parametro que se saltara el bloqueo acabaria
         * usandose desde otro sitio "porque da menos problemas".
         */
        $version = SurveyVersion::factory()->create();
        app(LockVersion::class)->claim($version, 0);

        $actual = $version->fresh();
        $nueva = app(LockVersion::class)->claim($actual, (int) $actual->lock_version);

        $this->assertSame(2, $nueva);
    }
}
