<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Surveys\OpenDraft;
use App\Domain\Identity\Models\Membership;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RF-AO-SUR-001 a 008 · RNF-AO-SUR-003 · RNF-AO-PUB-001 · RNF-GEN-005.
 */
final class SurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_una_encuesta_crea_su_primera_version(): void
    {
        // RF-AO-SUR-006. Una encuesta sin ninguna version es un registro que
        // no se puede editar ni publicar: aparece en la lista y no lleva a
        // ningun sitio.
        $admin = $this->admin();

        $this->post(route('admin.surveys.store'), [
            'name' => 'Satisfaccion en ventanilla',
        ])->assertRedirect();

        $survey = Survey::query()->firstOrFail();

        $this->assertSame($admin->organization_id, $survey->organization_id);
        $this->assertSame(1, $survey->versions()->count());
        $this->assertSame(1, $survey->draft->version_number);
    }

    public function test_la_creacion_es_transaccional(): void
    {
        // RNF-AO-SUR-003. Si la version fallara, la encuesta no debe quedarse.
        $admin = $this->admin();

        $this->assertSame(0, Survey::query()->count());
        $this->assertSame(0, SurveyVersion::query()->count());

        $this->post(route('admin.surveys.store'), ['name' => 'Prueba']);

        // Las dos, o ninguna.
        $this->assertSame(1, Survey::query()->count());
        $this->assertSame(1, SurveyVersion::query()->count());
    }

    public function test_solo_puede_haber_un_borrador_vivo(): void
    {
        /*
         * RF-AO-SUR-007, traducido a un indice parcial de PostgreSQL.
         *
         * Sin el, dos pestanas abiertas crean dos borradores y nadie se
         * entera hasta que uno de los dos se pierde al publicar.
         */
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();

        SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $admin->organization_id,
            'version_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $admin->organization_id,
            'version_number' => 2,
        ]);
    }

    public function test_abrir_borrador_no_toca_la_version_publicada(): void
    {
        /*
         * La regla que sostiene el versionado. Si el borrador modificara la
         * version publicada, las respuestas ya guardadas cambiarian de
         * significado: alguien contesto a una pregunta y el informe mostraria
         * otra.
         */
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();

        $publicada = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $admin->organization_id,
            'settings' => ['identity_mode' => 'anonymous'],
        ]);

        $this->post(route('admin.surveys.draft', $survey))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $publicada->refresh();

        $this->assertSame('published', $publicada->status->value);
        $this->assertSame(1, $publicada->version_number);
        $this->assertSame(['identity_mode' => 'anonymous'], $publicada->settings);

        $borrador = $survey->fresh()->draft;
        $this->assertSame(2, $borrador->version_number);

        // Y parte de la configuracion anterior: empezar en blanco obligaria a
        // reescribir todos los ajustes para cambiar una coma.
        $this->assertSame(['identity_mode' => 'anonymous'], $borrador->settings);
    }

    public function test_abrir_borrador_dos_veces_devuelve_el_mismo(): void
    {
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $primero = app(OpenDraft::class)->execute($survey);
        $segundo = app(OpenDraft::class)->execute($survey->fresh());

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(2, SurveyVersion::query()->count());
    }

    public function test_el_numero_de_version_es_unico_por_encuesta(): void
    {
        // RNF-AO-PUB-001. Dos encuestas distintas SI pueden tener una v1.
        $admin = $this->admin();

        $primera = Survey::factory()->for($admin->organization)->create();
        $segunda = Survey::factory()->for($admin->organization)->create();

        SurveyVersion::factory()->for($primera)->published(1)->create([
            'organization_id' => $admin->organization_id,
        ]);
        SurveyVersion::factory()->for($segunda)->published(1)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $this->assertSame(2, SurveyVersion::query()->where('version_number', 1)->count());
    }

    public function test_el_listado_solo_muestra_encuestas_de_la_organizacion(): void
    {
        // RNF-GEN-005.
        $admin = $this->admin();

        Survey::factory()->for($admin->organization)->create(['name' => 'Encuesta propia']);
        Survey::factory()->create(['name' => 'Encuesta ajena']);

        $this->get(route('admin.surveys.index'))
            ->assertOk()
            ->assertSee('Encuesta propia')
            ->assertDontSee('Encuesta ajena');
    }

    public function test_no_se_edita_una_encuesta_ajena(): void
    {
        $this->admin();
        $ajena = Survey::factory()->create();

        $this->get(route('admin.surveys.edit', $ajena))->assertForbidden();
        $this->put(route('admin.surveys.update', $ajena), ['name' => 'Secuestrada'])
            ->assertForbidden();
    }

    public function test_una_encuesta_archivada_no_se_edita(): void
    {
        // RF-AO-PUB-008: archivar impide nuevas aplicaciones, y editarla
        // seria preparar una aplicacion que no puede ocurrir.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->archived()->create();

        $this->put(route('admin.surveys.update', $survey), ['name' => 'Cambiada'])
            ->assertForbidden();

        $this->assertNotSame('Cambiada', $survey->fresh()->name);
    }

    public function test_archivar_conserva_la_encuesta_y_sus_versiones(): void
    {
        // RF-AO-PUB-008 y RF-GEN-010.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $this->post(route('admin.surveys.archive', $survey))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('archived', $survey->fresh()->status->value);
        $this->assertSame(1, SurveyVersion::query()->count());
    }

    public function test_activar_devuelve_al_estado_que_corresponde(): void
    {
        // Una encuesta con version publicada vuelve a publicada, no a
        // borrador: su estado lo dicen sus versiones, no el ultimo clic.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->archived()->create();
        SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $this->post(route('admin.surveys.activate', $survey));

        $this->assertSame('published', $survey->fresh()->status->value);
    }

    public function test_un_colaborador_no_entra_al_listado(): void
    {
        // RA-002 y RA-005.
        $membership = Membership::factory()->collaborator()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.surveys.index'))->assertForbidden();
    }

    public function test_el_listado_no_dispara_una_consulta_por_fila(): void
    {
        // RNF-AO-SUR-001: paginar en servidor y no cargar lo que no se
        // muestra. El umbral vigila que no crezca con las filas.
        $admin = $this->admin();
        Survey::factory()->for($admin->organization)->count(15)->create();

        $consultas = 0;
        DB::listen(function () use (&$consultas): void {
            $consultas++;
        });

        $this->get(route('admin.surveys.index'))->assertOk();

        $this->assertLessThan(20, $consultas, "El listado hizo {$consultas} consultas.");
    }

    public function test_la_navegacion_lleva_a_las_encuestas(): void
    {
        $this->admin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.surveys.index'), false);
    }

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
