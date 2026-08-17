<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\OpenDraft;
use App\Domain\Identity\Models\Membership;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionCondition;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un borrador nuevo hereda las preguntas de la version anterior.
 *
 * Antes nacia VACIO: publicar la v1 y abrir la v2 obligaba a reescribir la
 * encuesta entera para cambiar una coma. Nadie iba a teclear veinte preguntas
 * otra vez, asi que las correcciones se quedaban sin hacer.
 *
 * Se COPIAN, no se comparten: las respuestas guardan el texto de la pregunta
 * que se hizo, y si las versiones compartieran preguntas, cambiar una en la
 * v2 cambiaria lo que dice la respuesta de quien contesto la v1.
 */
final class DraftInheritsQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_borrador_nuevo_trae_las_preguntas(): void
    {
        [$survey, $version] = $this->versionCon(3);

        $version->forceFill(['status' => 'published'])->save();

        $draft = app(OpenDraft::class)->execute($survey->fresh());

        $this->assertSame(2, $draft->version_number);
        $this->assertSame(3, $draft->questions()->count());
    }

    public function test_son_copia_s_no_las_mismas(): void
    {
        /*
         * LO QUE PROTEGE LA FOTOGRAFIA HISTORICA.
         *
         * Si fueran las mismas filas, editar el texto en la v2 cambiaria lo
         * que dice la respuesta de quien contesto la v1.
         */
        [$survey, $version] = $this->versionCon(1);
        $version->forceFill(['status' => 'published'])->save();

        $original = $version->questions()->first();
        $draft = app(OpenDraft::class)->execute($survey->fresh());
        $copia = $draft->questions()->first();

        $this->assertNotSame($original->id, $copia->id);
        $this->assertNotSame($original->ulid, $copia->ulid);
        $this->assertSame($original->text, $copia->text);

        // Y editar la copia NO toca el original.
        $copia->forceFill(['text' => 'Texto cambiado en la v2'])->save();

        $this->assertNotSame('Texto cambiado en la v2', $original->fresh()->text);
    }

    public function test_las_opciones_vienen_con_su_puntuacion(): void
    {
        [$survey, $version] = $this->versionCon(1, opciones: 3);
        $version->forceFill(['status' => 'published'])->save();

        $draft = app(OpenDraft::class)->execute($survey->fresh());
        $opciones = $draft->questions()->first()->options()->orderBy('position')->get();

        $this->assertCount(3, $opciones);
        $this->assertSame([3, 2, 1], $opciones->pluck('score')->all());
    }

    public function test_la_condicion_apunta_a_la_pregunta_copiada(): void
    {
        /*
         * LA PARTE DELICADA.
         *
         * Copiar la condicion tal cual dejaria la v2 señalando preguntas de
         * la v1: editar una de ellas cambiaria el comportamiento de la otra,
         * y eso no se ve mirando ninguna de las dos.
         */
        [$survey, $version] = $this->versionCon(2, opciones: 2);
        $preguntas = $version->questions()->orderBy('position')->get();
        $opcion = $preguntas[0]->options()->first();

        SurveyQuestionCondition::query()->create([
            'survey_question_id' => $preguntas[1]->id,
            'organization_id' => $version->organization_id,
            'depends_on_question_id' => $preguntas[0]->id,
            'option_id' => $opcion->id,
        ]);

        $version->forceFill(['status' => 'published'])->save();

        $draft = app(OpenDraft::class)->execute($survey->fresh());
        $copias = $draft->questions()->orderBy('position')->with('condition')->get();

        $condicion = $copias[1]->condition;

        $this->assertNotNull($condicion, 'La condicion se copia.');

        // Apunta a la copia, NO al original.
        $this->assertSame($copias[0]->id, $condicion->depends_on_question_id);
        $this->assertNotSame($preguntas[0]->id, $condicion->depends_on_question_id);

        // Y la opcion tambien.
        $this->assertNotSame($opcion->id, $condicion->option_id);
        $this->assertSame(
            $copias[0]->options()->first()->id,
            $condicion->option_id,
        );
    }

    public function test_la_imagen_se_comparte(): void
    {
        /*
         * Es un archivo de la biblioteca, no parte de la pregunta. Duplicarlo
         * dejaria dos copias del mismo archivo por cada version.
         */
        [$survey, $version] = $this->versionCon(1, opciones: 1);

        /*
         * A mano: MediaItem no tiene factory porque la biblioteca se siembra,
         * no se fabrica. Los campos son los que escribe StoreMediaItem.
         */
        $media = MediaItem::query()->create([
            'organization_id' => $version->organization_id,
            'name' => 'una-imagen.png',
            'path' => 'media/1/aa/una-imagen.png',
            'disk' => 'local',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
        ]);

        $version->questions()->first()->options()->first()
            ->forceFill(['media_id' => $media->id])->save();

        $version->forceFill(['status' => 'published'])->save();

        $draft = app(OpenDraft::class)->execute($survey->fresh());

        $this->assertSame(
            $media->id,
            $draft->questions()->first()->options()->first()->media_id,
        );
    }

    public function test_una_encuesta_sin_versiones_abre_un_borrador_vacio(): void
    {
        // No hay nada de lo que heredar, y eso no es un error.
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $draft = app(OpenDraft::class)->execute($survey);

        $this->assertSame(1, $draft->version_number);
        $this->assertSame(0, $draft->questions()->count());
    }

    public function test_si_ya_hay_borrador_se_devuelve_ese(): void
    {
        // Y NO se duplican sus preguntas: quien vuelve al constructor espera
        // continuar donde lo dejo.
        [$survey, $version] = $this->versionCon(2);

        $primero = app(OpenDraft::class)->execute($survey->fresh());
        $segundo = app(OpenDraft::class)->execute($survey->fresh());

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(2, $segundo->questions()->count());
    }

    /** @return array{0: Survey, 1: SurveyVersion} */
    private function versionCon(int $preguntas, int $opciones = 2): array
    {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $membership->organization_id,
        ]);

        foreach (range(1, $preguntas) as $i) {
            $pregunta = SurveyQuestion::factory()->for($version, 'version')->create([
                'organization_id' => $membership->organization_id,
                'type' => QuestionType::SingleChoice,
                'text' => "Pregunta {$i}",
                'position' => $i,
            ]);

            foreach (range(1, $opciones) as $j) {
                SurveyQuestionOption::factory()->for($pregunta, 'question')->create([
                    'organization_id' => $membership->organization_id,
                    'label' => "Opcion {$j}",
                    'value' => "opcion-{$j}",
                    'score' => $opciones - $j + 1,
                    'position' => $j,
                ]);
            }
        }

        return [$survey, $version->fresh()];
    }
}
