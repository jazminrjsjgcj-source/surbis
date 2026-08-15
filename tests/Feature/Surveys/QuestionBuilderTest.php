<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\DeleteQuestion;
use App\Application\Surveys\DuplicateQuestion;
use App\Application\Surveys\Exceptions\VersionNotEditable;
use App\Application\Surveys\ReorderQuestions;
use App\Application\Surveys\SaveOptions;
use App\Application\Surveys\SaveQuestion;
use App\Domain\Surveys\Enums\OptionDisplay;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\QuestionLimits;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * RF-AO-BLD-001, 002, 003 y 010 · RNF-AO-BLD-002.
 *
 * Se prueban los casos de uso directamente: TASK-019 no tiene pantalla. El
 * constructor en React es TASK-020, y estas reglas tienen que estar de pie
 * antes de que exista algo que las invoque.
 */
final class QuestionBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_version_publicada_no_admite_preguntas_nuevas(): void
    {
        // RF-AO-PUB-007 y RF-AO-BLD-009. Si esto fallara, editar una encuesta
        // en uso cambiaria el significado de respuestas ya dadas.
        $version = SurveyVersion::factory()->published(1)->create();

        $this->expectException(VersionNotEditable::class);

        app(SaveQuestion::class)->create($version, $this->datos());
    }

    public function test_las_preguntas_se_numeran_en_orden(): void
    {
        $version = SurveyVersion::factory()->create();

        foreach (['Primera', 'Segunda', 'Tercera'] as $texto) {
            app(SaveQuestion::class)->create($version, $this->datos($texto));
        }

        $this->assertSame(
            [1, 2, 3],
            SurveyQuestion::query()->orderBy('position')->pluck('position')->all(),
        );
    }

    public function test_dos_preguntas_no_comparten_posicion(): void
    {
        /*
         * RNF-AO-BLD-002, garantizado por la base.
         *
         * Hace falta SET CONSTRAINTS ALL IMMEDIATE, y explicarlo importa:
         * la restriccion es DEFERRABLE INITIALLY DEFERRED —para que reordenar
         * pueda pasar por un estado intermedio con posiciones repetidas— asi
         * que NO lanza al insertar, sino al cerrar la transaccion.
         *
         * Y RefreshDatabase envuelve cada prueba en una transaccion que hace
         * rollback, con lo cual ese cierre nunca llega. Sin esta linea, la
         * prueba pasaba diciendo que no se lanzo excepcion... porque la
         * comprobacion no se habia hecho todavia.
         *
         * Es decir: la version anterior de esta prueba no probaba nada. La
         * restriccion existe y funciona; lo que fallaba era como se miraba.
         */
        $version = SurveyVersion::factory()->create();
        SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $version->organization_id,
            'position' => 1,
        ]);

        DB::statement('set constraints survey_questions_position_unique immediate');

        $this->expectException(QueryException::class);

        SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $version->organization_id,
            'position' => 1,
        ]);
    }

    public function test_reordenar_reasigna_todas_las_posiciones(): void
    {
        /*
         * La restriccion es DEFERRABLE: a mitad del reordenamiento dos
         * preguntas comparten numero, y eso es valido hasta que la
         * transaccion cierra. Sin diferirla habria que pasar por posiciones
         * negativas, que es el truco que nadie entiende medio ano despues.
         */
        $version = SurveyVersion::factory()->create();
        $ulids = [];

        foreach (['A', 'B', 'C'] as $texto) {
            $ulids[] = app(SaveQuestion::class)->create($version, $this->datos($texto))->ulid;
        }

        app(ReorderQuestions::class)->execute($version, [$ulids[2], $ulids[0], $ulids[1]]);

        $this->assertSame(
            ['C', 'A', 'B'],
            SurveyQuestion::query()->orderBy('position')->pluck('text')->all(),
        );
    }

    public function test_reordenar_exige_la_lista_completa(): void
    {
        // Aceptar una lista parcial obligaria a inventar donde van las que
        // faltan, y cualquier decision seria una invencion.
        $version = SurveyVersion::factory()->create();
        $primera = app(SaveQuestion::class)->create($version, $this->datos('A'));
        app(SaveQuestion::class)->create($version, $this->datos('B'));

        $this->expectException(InvalidArgumentException::class);

        app(ReorderQuestions::class)->execute($version, [$primera->ulid]);
    }

    public function test_borrar_renumera_las_restantes(): void
    {
        // Un hueco convierte la posicion en un numero que ya no corresponde
        // con el orden visible.
        $version = SurveyVersion::factory()->create();
        $primera = app(SaveQuestion::class)->create($version, $this->datos('A'));
        app(SaveQuestion::class)->create($version, $this->datos('B'));
        app(SaveQuestion::class)->create($version, $this->datos('C'));

        app(DeleteQuestion::class)->execute($primera);

        $this->assertSame(
            ['B' => 1, 'C' => 2],
            SurveyQuestion::query()->orderBy('position')->pluck('position', 'text')->all(),
        );
    }

    public function test_duplicar_copia_la_pregunta_con_sus_opciones(): void
    {
        $version = SurveyVersion::factory()->create();
        $original = app(SaveQuestion::class)->create($version, $this->datos('Original'));

        app(SaveOptions::class)->execute($original, [
            $this->opcion('Buena', 'buena', 5),
            $this->opcion('Mala', 'mala', 1),
        ]);

        $copia = app(DuplicateQuestion::class)->execute($original->fresh());

        $this->assertSame(2, $copia->options()->count());
        $this->assertSame(2, $copia->position);

        // El valor se copia tal cual: la unicidad es POR pregunta, y una copia
        // con valores cambiados no seria una copia.
        $this->assertSame(
            ['buena', 'mala'],
            $copia->options()->orderBy('position')->pluck('value')->all(),
        );
    }

    public function test_no_se_repiten_valores_dentro_de_una_pregunta(): void
    {
        // RF-AO-BLD-010.
        $version = SurveyVersion::factory()->create();
        $question = app(SaveQuestion::class)->create($version, $this->datos());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('repetidos');

        app(SaveOptions::class)->execute($question, [
            $this->opcion('Buena', 'buena', 5),
            $this->opcion('Muy buena', 'buena', 4),
        ]);
    }

    public function test_la_base_tambien_impide_valores_repetidos(): void
    {
        // La validacion da un mensaje util; la base da la garantia. Si alguien
        // escribe por otra via, la restriccion sigue ahi.
        $question = SurveyQuestion::factory()->create();

        SurveyQuestionOption::factory()->for($question, 'question')->create([
            'organization_id' => $question->organization_id,
            'value' => 'buena',
            'position' => 1,
        ]);

        $this->expectException(QueryException::class);

        SurveyQuestionOption::factory()->for($question, 'question')->create([
            'organization_id' => $question->organization_id,
            'value' => 'buena',
            'position' => 2,
        ]);
    }

    public function test_un_tipo_sin_opciones_las_rechaza(): void
    {
        $version = SurveyVersion::factory()->create();
        $question = app(SaveQuestion::class)->create(
            $version,
            $this->datos('Comentario', QuestionType::LongText),
        );

        $this->expectException(InvalidArgumentException::class);

        app(SaveOptions::class)->execute($question, [$this->opcion('Buena', 'buena', 5)]);
    }

    public function test_cambiar_a_un_tipo_sin_opciones_borra_las_que_hubiera(): void
    {
        // Un texto libre con cuatro opciones colgando es un registro que nadie
        // sabe interpretar.
        $version = SurveyVersion::factory()->create();
        $question = app(SaveQuestion::class)->create($version, $this->datos());

        app(SaveOptions::class)->execute($question, [
            $this->opcion('Buena', 'buena', 5),
        ]);

        $this->assertSame(1, $question->options()->count());

        app(SaveQuestion::class)->update(
            $question->fresh(),
            $this->datos('Comentario', QuestionType::LongText),
        );

        $this->assertSame(0, SurveyQuestionOption::query()->count());
    }

    public function test_los_limites_se_guardan_solo_si_el_tipo_los_usa(): void
    {
        /*
         * Una pregunta que cambia de numero a texto conserva min y max si
         * nadie los descarta. Serian datos que nadie lee y que la siguiente
         * pantalla interpretaria como si significaran algo.
         */
        $version = SurveyVersion::factory()->create();

        $question = app(SaveQuestion::class)->create($version, [
            'type' => QuestionType::Number,
            'text' => '¿Cuantos minutos esperaste?',
            'help' => null,
            'is_required' => true,
            'limits' => new QuestionLimits(min: 0, max: 120, maxLength: 500),
        ]);

        $guardado = json_decode((string) $question->fresh()->getRawOriginal('limits'), true);

        /*
         * assertEqualsCanonicalizing y no assertSame: PostgreSQL normaliza el
         * jsonb —guarda 0 donde PHP escribio 0.0 y devuelve las claves
         * ordenadas— y assertSame compara tipo Y orden. Comparar asi haria
         * fallar la prueba por como almacena la base, no por lo que guarda el
         * codigo.
         *
         * Lo que se vigila es QUE claves hay, no en que orden ni con que tipo
         * numerico exacto.
         */
        $this->assertEqualsCanonicalizing(['min' => 0, 'max' => 120], $guardado);
        $this->assertArrayNotHasKey('max_length', $guardado);
    }

    public function test_la_etiqueta_se_conserva_aunque_la_opcion_sea_solo_imagen(): void
    {
        // RF-AO-BLD-005: el nombre accesible existe siempre. Una carita sin
        // nombre no se puede elegir con lector de pantalla.
        $question = SurveyQuestion::factory()->create();

        $option = SurveyQuestionOption::factory()->for($question, 'question')->create([
            'organization_id' => $question->organization_id,
            'label' => 'Muy satisfecho',
            'display' => OptionDisplay::Image,
        ]);

        $this->assertSame('Muy satisfecho', $option->accessibleName());
    }

    /** @return array{type: QuestionType, text: string, help: null, is_required: bool, limits: QuestionLimits} */
    private function datos(string $texto = 'Pregunta', QuestionType $tipo = QuestionType::SingleChoice): array
    {
        return [
            'type' => $tipo,
            'text' => $texto,
            'help' => null,
            'is_required' => false,
            'limits' => new QuestionLimits,
        ];
    }

    /** @return array{ulid: null, label: string, value: string, score: int, display: OptionDisplay, appearance: null} */
    private function opcion(string $label, string $value, int $score): array
    {
        return [
            'ulid' => null,
            'label' => $label,
            'value' => $value,
            'score' => $score,
            'display' => OptionDisplay::Text,
            'appearance' => null,
        ];
    }
}
