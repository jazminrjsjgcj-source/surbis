<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\SaveBuilderState;
use App\Domain\Identity\Models\Membership;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Import\QuestionTextParser;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las preguntas de si/no tienen sus dos opciones.
 *
 * ESTA PRUEBA NACE DE UN FALLO REAL, y anterior a la tarea que lo descubrio:
 * una pregunta de si/no se guardaba SIN NINGUNA opcion.
 *
 * El renderizador pinta los botones recorriendo `options`, asi que la
 * pregunta aparecia vacia y no se podia contestar. Nadie lo habia notado
 * porque el seeder no crea preguntas de ese tipo.
 *
 * La confusion estaba en hasOptions(): significa "se pueden EDITAR", no
 * "tiene". Si/no no deja editarlas —permitiria un si/no con cuatro
 * respuestas— pero tenerlas tiene que tenerlas.
 */
final class YesNoOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_un_si_no_crea_sus_dos_opciones(): void
    {
        $version = $this->borrador();

        app(SaveBuilderState::class)->execute($version, 0, [
            [
                'ulid' => null,
                'type' => QuestionType::YesNo->value,
                'text' => '¿Volverias?',
                'help' => null,
                'is_required' => false,
                'limits' => [],
                'options' => [],
            ],
        ]);

        $pregunta = $version->fresh()->questions()->with('options')->first();

        $this->assertCount(2, $pregunta->options);
        $this->assertSame(['Si', 'No'], $pregunta->options->pluck('label')->all());
    }

    public function test_el_cliente_no_puede_cambiar_las_opciones_de_un_si_no(): void
    {
        /*
         * Mandarlas desde el navegador permitiria un si/no con cuatro
         * respuestas por la via de atras. El servidor las impone.
         */
        $version = $this->borrador();

        app(SaveBuilderState::class)->execute($version, 0, [
            [
                'ulid' => null,
                'type' => QuestionType::YesNo->value,
                'text' => '¿Volverias?',
                'help' => null,
                'is_required' => false,
                'limits' => [],
                'options' => [
                    ['ulid' => null, 'label' => 'Quiza', 'value' => 'quiza', 'score' => 9, 'display' => 'text', 'appearance' => null],
                    ['ulid' => null, 'label' => 'Nunca', 'value' => 'nunca', 'score' => 1, 'display' => 'text', 'appearance' => null],
                    ['ulid' => null, 'label' => 'Siempre', 'value' => 'siempre', 'score' => 5, 'display' => 'text', 'appearance' => null],
                ],
            ],
        ]);

        $pregunta = $version->fresh()->questions()->with('options')->first();

        $this->assertCount(2, $pregunta->options);
        $this->assertSame(['Si', 'No'], $pregunta->options->pluck('label')->all());
    }

    public function test_las_opciones_puntuan_de_mejor_a_peor(): void
    {
        // El mismo criterio que en el resto del sistema.
        $opciones = QuestionType::YesNo->fixedOptions();

        $this->assertSame(2, $opciones[0]['score']);
        $this->assertSame(1, $opciones[1]['score']);
    }

    public function test_importar_un_si_no_tambien_las_crea(): void
    {
        $preguntas = app(QuestionTextParser::class)->parse("[obligatorias, si/no]\n¿Volverias?");

        $this->assertCount(2, $preguntas->first()->options);
    }

    public function test_se_puede_condicionar_a_un_si_no(): void
    {
        /*
         * Lo que descubrio el fallo. Sin opciones, la condicion no tenia a
         * que agarrarse y daba "la pregunta anterior no tiene opciones".
         */
        $texto = "[obligatorias, si/no]\n"
            ."¿Tuviste dificultades?\n"
            ."[opcionales, texto largo, si \"Si\" en la anterior]\n"
            .'¿Cuales?';

        $problemas = app(QuestionTextParser::class)->problems($texto);

        $this->assertCount(0, $problemas, 'Un si/no debe poder condicionar.');
    }

    private function borrador(): SurveyVersion
    {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        return SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $membership->organization_id,
        ]);
    }
}
