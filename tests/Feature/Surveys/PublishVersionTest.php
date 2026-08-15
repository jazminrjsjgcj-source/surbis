<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\Exceptions\VersionNotEditable;
use App\Application\Surveys\Exceptions\VersionNotPublishable;
use App\Application\Surveys\PublishVersion;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Publicar una version. RF-AO-PUB-005, 006, 007 · RNF-AO-PUB-001 a 004.
 */
final class PublishVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_version_completa_se_publica(): void
    {
        [$version, $autor] = $this->borradorCompleto();

        $publicada = app(PublishVersion::class)->execute($version, $autor);

        $this->assertSame('published', $publicada->status->value);
        $this->assertNotNull($publicada->published_at);
        $this->assertSame($autor->id, $publicada->published_by);
    }

    public function test_el_borrador_se_convierte_y_deja_de_existir(): void
    {
        /*
         * Decision del area usuaria: el borrador SE CONVIERTE, no se copia.
         *
         * Es lo que hace que "inmutable" signifique algo. Si siguiera vivo,
         * se podria editar exactamente el contenido que la gente ya esta
         * contestando.
         */
        [$version, $autor] = $this->borradorCompleto();
        $survey = $version->survey;

        app(PublishVersion::class)->execute($version, $autor);

        $this->assertNull($survey->fresh()->draft);
        $this->assertSame(1, $survey->fresh()->publishedVersion->version_number);
    }

    public function test_la_encuesta_pasa_a_publicada(): void
    {
        [$version, $autor] = $this->borradorCompleto();

        app(PublishVersion::class)->execute($version, $autor);

        $this->assertSame('published', $version->survey->fresh()->status->value);
    }

    public function test_publicar_archiva_la_version_anterior_sin_borrarla(): void
    {
        /*
         * Las respuestas que contestaron la version anterior siguen
         * apuntando a ella: su contenido tiene que seguir ahi para poder
         * leerlas. RF-GEN-010 y RNF-DAT-009.
         */
        [$version, $autor] = $this->borradorCompleto();
        $survey = $version->survey;

        $anterior = SurveyVersion::factory()->for($survey)->published(9)->create([
            'organization_id' => $survey->organization_id,
        ]);

        app(PublishVersion::class)->execute($version, $autor);

        $this->assertSame('archived', $anterior->fresh()->status->value);
        $this->assertDatabaseHas('survey_versions', ['id' => $anterior->id]);
    }

    public function test_una_version_sin_preguntas_no_se_publica(): void
    {
        // RF-AO-PUB-005. Sin preguntas no hay nada que contestar.
        $autor = Membership::factory()->create()->user;
        $survey = Survey::factory()->create();
        $version = SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $survey->organization_id,
        ]);

        try {
            app(PublishVersion::class)->execute($version, $autor);
            $this->fail('Se esperaba que no se pudiera publicar.');
        } catch (VersionNotPublishable $bloqueo) {
            $this->assertSame('no_questions', $bloqueo->problems->first()->key);
        }
    }

    public function test_una_pregunta_sin_texto_bloquea_y_dice_donde(): void
    {
        /*
         * RF-AO-PUB-006 pide UBICACION. "Hay un error en la encuesta"
         * obligaria a buscarlo a mano entre veinte preguntas.
         */
        [$version, $autor] = $this->borradorCompleto();

        SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $version->organization_id,
            'type' => QuestionType::LongText,
            'text' => '',
            'position' => 2,
        ]);

        try {
            app(PublishVersion::class)->execute($version->fresh(), $autor);
            $this->fail('Se esperaba que no se pudiera publicar.');
        } catch (VersionNotPublishable $bloqueo) {
            $problema = $bloqueo->problems->firstWhere('key', 'question_without_text');

            $this->assertNotNull($problema);
            $this->assertSame(2, $problema->questionPosition);
        }
    }

    public function test_una_pregunta_de_eleccion_con_una_sola_opcion_bloquea(): void
    {
        // Menos de dos opciones no es una eleccion.
        $autor = Membership::factory()->create()->user;
        $survey = Survey::factory()->create();
        $version = SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $survey->organization_id,
        ]);

        $question = SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $survey->organization_id,
            'type' => QuestionType::SingleChoice,
            'text' => '¿Que tal?',
            'position' => 1,
        ]);

        SurveyQuestionOption::factory()->for($question, 'question')->create([
            'organization_id' => $survey->organization_id,
            'value' => 'unica',
            'position' => 1,
        ]);

        try {
            app(PublishVersion::class)->execute($version, $autor);
            $this->fail('Se esperaba que no se pudiera publicar.');
        } catch (VersionNotPublishable $bloqueo) {
            $problema = $bloqueo->problems->firstWhere('key', 'too_few_options');

            $this->assertNotNull($problema);
            $this->assertSame(1, $problema->questionPosition);
            $this->assertSame(2, $problema->replacements['min']);
        }
    }

    public function test_una_opcion_sin_nombre_bloquea(): void
    {
        /*
         * RF-AO-BLD-005: la etiqueta es el nombre accesible. Una opcion sin
         * nombre no se puede elegir con lector de pantalla, y eso no puede
         * llegar a produccion.
         */
        [$version, $autor] = $this->borradorCompleto();

        $version->questions->first()->options->first()->forceFill(['label' => ''])->save();

        try {
            app(PublishVersion::class)->execute($version->fresh(), $autor);
            $this->fail('Se esperaba que no se pudiera publicar.');
        } catch (VersionNotPublishable $bloqueo) {
            $this->assertNotNull($bloqueo->problems->firstWhere('key', 'option_without_label'));
        }
    }

    public function test_una_version_ya_publicada_no_se_publica_dos_veces(): void
    {
        // RF-AO-PUB-007: al publicar deja de ser editable, y eso incluye
        // volver a publicarla.
        [$version, $autor] = $this->borradorCompleto();

        app(PublishVersion::class)->execute($version, $autor);

        $this->expectException(VersionNotEditable::class);

        app(PublishVersion::class)->execute($version->fresh(), $autor);
    }

    public function test_un_bloqueo_no_deja_nada_a_medias(): void
    {
        // RNF-AO-PUB-003: publicar es atomico. Ni la version ni la encuesta
        // cambian si la comprobacion falla.
        $autor = Membership::factory()->create()->user;
        $survey = Survey::factory()->create();
        $version = SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $survey->organization_id,
        ]);

        try {
            app(PublishVersion::class)->execute($version, $autor);
        } catch (VersionNotPublishable) {
            // Esperado.
        }

        $this->assertSame('draft', $version->fresh()->status->value);
        $this->assertSame('draft', $survey->fresh()->status->value);
    }

    public function test_publicar_queda_auditado(): void
    {
        // RNF-AO-PUB-004: quien publico, cuando y que version.
        [$version, $autor] = $this->borradorCompleto();

        app(PublishVersion::class)->execute($version, $autor);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'survey_version.published',
            'user_id' => $autor->id,
        ]);
    }

    /** @return array{0: SurveyVersion, 1: User} */
    private function borradorCompleto(): array
    {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $question = SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $membership->organization_id,
            'type' => QuestionType::SingleChoice,
            'text' => '¿Como te atendieron?',
            'position' => 1,
        ]);

        foreach ([['Bien', 'bien'], ['Mal', 'mal']] as $indice => [$label, $value]) {
            SurveyQuestionOption::factory()->for($question, 'question')->create([
                'organization_id' => $membership->organization_id,
                'label' => $label,
                'value' => $value,
                'position' => $indice + 1,
            ]);
        }

        return [$version->fresh(), $membership->user];
    }
}
