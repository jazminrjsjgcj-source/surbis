<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Import\ImportProblem;
use App\Domain\Surveys\Import\ParsedQuestion;
use App\Domain\Surveys\Import\QuestionTextParser;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Importar preguntas desde texto. TASK-025 · D-027.
 *
 * Sin base de datos: analizar un texto es una funcion pura. El ANEXO 1
 * seccion 70 lo pide explicitamente —una prueba que se puede escribir sin
 * base de datos se escribe sin base de datos— y aqui se nota: estas 14
 * pruebas tardan milisegundos.
 */
final class QuestionTextParserTest extends TestCase
{
    public function test_un_bloque_da_su_tipo_a_todas_sus_preguntas(): void
    {
        // Es lo que hace util la importacion: en una encuesta de satisfaccion
        // la misma escala se repite en ocho preguntas.
        $questions = $this->parse(<<<'TEXTO'
            [obligatorias, una opcion: Si / Mas o menos / No]
            ¿Te atendieron con amabilidad?
            ¿El tiempo de espera fue razonable?
            TEXTO);

        $this->assertCount(2, $questions);

        foreach ($questions as $question) {
            $this->assertSame(QuestionType::SingleChoice, $question->type);
            $this->assertTrue($question->isRequired);
            $this->assertCount(3, $question->options);
        }
    }

    public function test_las_puntuaciones_bajan_de_mejor_a_peor(): void
    {
        /*
         * Decision del area usuaria: las opciones se declaran de mejor a peor
         * y reciben puntuaciones descendentes.
         *
         * El maximo depende de cuantas haya, asi que una escala de tres y
         * otra de cinco no son comparables directamente: eso se normaliza en
         * la analitica, no aqui.
         */
        $questions = $this->parse(<<<'TEXTO'
            [obligatorias, una opcion: Muy bien / Bien / Regular / Mal]
            ¿Como te atendieron?
            TEXTO);

        $this->assertSame(
            [4, 3, 2, 1],
            array_column($questions->first()->options, 'score'),
        );
    }

    public function test_el_valor_se_deriva_de_la_etiqueta(): void
    {
        // Y despues permanece estable: cambiar el texto de una opcion no debe
        // cambiar lo que quedo guardado en las respuestas.
        $questions = $this->parse(<<<'TEXTO'
            [una opcion: Muy satisfecho / Mas o menos / Nada satisfecho]
            ¿Que tal?
            TEXTO);

        $this->assertSame(
            ['muy-satisfecho', 'mas-o-menos', 'nada-satisfecho'],
            array_column($questions->first()->options, 'value'),
        );
    }

    public function test_varios_bloques_conviven_con_ajustes_distintos(): void
    {
        $questions = $this->parse(<<<'TEXTO'
            [obligatorias, una opcion: Si / No]
            ¿Volverias?

            [opcionales, texto largo]
            ¿Algo que mejorarias?
            TEXTO);

        $this->assertCount(2, $questions);

        $this->assertTrue($questions[0]->isRequired);
        $this->assertSame(QuestionType::SingleChoice, $questions[0]->type);

        $this->assertFalse($questions[1]->isRequired);
        $this->assertSame(QuestionType::LongText, $questions[1]->type);
        $this->assertSame([], $questions[1]->options);
    }

    public function test_los_tipos_se_reconocen_sin_acentos_ni_mayusculas(): void
    {
        // Quien escribe una encuesta no deberia tener que aprender un
        // vocabulario exacto.
        foreach (['Texto Largo', 'texto largo', 'COMENTARIO', 'abierta'] as $escrito) {
            $questions = $this->parse("[opcionales, {$escrito}]\n¿Comentarios?");

            $this->assertSame(
                QuestionType::LongText,
                $questions->first()->type,
                "No reconocio: {$escrito}",
            );
        }
    }

    public function test_varias_formas_nombran_el_mismo_tipo(): void
    {
        foreach (['varias opciones', 'opcion multiple', 'seleccion multiple'] as $escrito) {
            $questions = $this->parse("[{$escrito}: Uno / Dos]\n¿Cual?");

            $this->assertSame(QuestionType::MultipleChoice, $questions->first()->type);
        }
    }

    public function test_un_tipo_desconocido_detiene_todo_y_dice_cuales_existen(): void
    {
        /*
         * Se detiene la importacion entera. Importar a medias deja una
         * encuesta que hay que revisar completa para saber que entro.
         *
         * Y el mensaje lleva la lista de tipos: decir solo "tipo desconocido"
         * obliga a adivinar cual escribir.
         */
        $problems = $this->problems(<<<'TEXTO'
            [obligatorias, telepatia]
            ¿En que estoy pensando?
            TEXTO);

        $problema = $problems->firstWhere('key', 'unknown_type');

        $this->assertNotNull($problema);
        $this->assertSame(1, $problema->line);
        $this->assertSame('telepatia', $problema->replacements['written']);
        $this->assertStringContainsString('texto largo', $problema->replacements['known']);
    }

    public function test_un_bloque_sin_cerrar_se_senala_con_su_linea(): void
    {
        $problems = $this->problems(<<<'TEXTO'
            [obligatorias, una opcion: Si / No
            ¿Volverias?
            TEXTO);

        $problema = $problems->firstWhere('key', 'unclosed_block');

        $this->assertNotNull($problema);
        $this->assertSame(1, $problema->line);
    }

    public function test_una_pregunta_antes_del_primer_bloque_se_rechaza(): void
    {
        /*
         * Se podria suponer un tipo por defecto, pero entonces la pregunta
         * entraria con un tipo que nadie pidio y habria que revisarlas todas
         * para descubrirlo.
         */
        $problems = $this->problems(<<<'TEXTO'
            ¿Esta pregunta de que tipo es?

            [una opcion: Si / No]
            ¿Y esta?
            TEXTO);

        $problema = $problems->firstWhere('key', 'question_without_block');

        $this->assertNotNull($problema);
        $this->assertSame(1, $problema->line);
    }

    public function test_un_tipo_con_opciones_sin_declararlas_se_rechaza(): void
    {
        // Menos de dos opciones no es una eleccion.
        $problems = $this->problems("[una opcion]\n¿Cual prefieres?");

        $this->assertNotNull($problems->firstWhere('key', 'block_without_options'));
    }

    public function test_un_bloque_sin_tipo_se_rechaza(): void
    {
        $problems = $this->problems("[obligatorias]\n¿De que tipo?");

        $this->assertNotNull($problems->firstWhere('key', 'block_without_type'));
    }

    public function test_un_texto_vacio_no_importa_nada(): void
    {
        $this->assertNotNull($this->problems('   ')->firstWhere('key', 'nothing_to_import'));
    }

    public function test_las_lineas_en_blanco_no_estorban(): void
    {
        // La gente separa bloques con lineas vacias, y debe poder.
        $questions = $this->parse("\n\n[una opcion: Si / No]\n\n¿Volverias?\n\n");

        $this->assertCount(1, $questions);
    }

    public function test_el_resultado_encaja_con_el_guardado_del_constructor(): void
    {
        /*
         * Lo importado se guarda por la MISMA via que lo escrito a mano:
         * SaveBuilderState. Si la importacion tuviera su propio camino de
         * escritura, sus reglas y las del constructor divergirian.
         */
        $questions = $this->parse("[obligatorias, una opcion: Si / No]\n¿Volverias?");
        $estado = $questions->first()->toBuilderState();

        /*
         * 'condition' entra en la lista con las condiciones al importar.
         *
         * Aqui llega SIEMPRE en null: toBuilderState() convierte una pregunta
         * suelta, y una condicion necesita el ULID de una opcion de otra. La
         * resuelve QuestionImportController cuando ya tiene la lista entera.
         */
        $this->assertSame(
            ['ulid', 'type', 'text', 'help', 'is_required', 'limits', 'condition', 'options'],
            array_keys($estado),
        );

        $this->assertSame(
            ['ulid', 'label', 'value', 'score', 'display', 'appearance'],
            array_keys($estado['options'][0]),
        );
    }

    /** @return Collection<int, ParsedQuestion> */
    private function parse(string $texto)
    {
        return app(QuestionTextParser::class)->parse($texto);
    }

    /** @return Collection<int, ImportProblem> */
    private function problems(string $texto)
    {
        return app(QuestionTextParser::class)->problems($texto);
    }
}
