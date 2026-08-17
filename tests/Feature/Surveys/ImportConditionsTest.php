<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Domain\Surveys\Import\QuestionTextParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Condiciones al importar desde texto.
 *
 * Sintaxis acordada con el area usuaria, 19 ago 2026:
 *
 *     [opcionales, texto largo, si "Si" en la anterior]
 *
 * Solo la pregunta ANTERIOR. Una de seguimiento va justo detras de la que la
 * dispara, y permitir señalar preguntas lejanas obligaria a inventar una
 * forma de nombrarlas: numeros que se rompen al reordenar, o etiquetas que
 * hay que declarar antes.
 */
final class ImportConditionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_condicion_se_reconoce(): void
    {
        $texto = "[obligatorias, si/no]\n"
            ."¿Tuviste alguna dificultad?\n"
            ."[opcionales, texto largo, si \"Si\" en la anterior]\n"
            .'¿Que paso?';

        $preguntas = app(QuestionTextParser::class)->parse($texto);

        $this->assertCount(2, $preguntas);
        $this->assertNull($preguntas[0]->conditionOnPreviousOption);
        $this->assertSame('Si', $preguntas[1]->conditionOnPreviousOption);
    }

    public function test_las_comillas_tipograficas_tambien_valen(): void
    {
        /*
         * Quien escribe el texto lo hace en un procesador que cambia las
         * comillas solo. Rechazarlo por eso seria hacer perder el tiempo a
         * alguien que lo escribio bien.
         */
        $texto = "[obligatorias, si/no]\n"
            ."¿Tuviste dificultades?\n"
            ."[opcionales, texto largo, si \u{201C}Si\u{201D} en la anterior]\n"
            .'¿Cual?';

        $preguntas = app(QuestionTextParser::class)->parse($texto);

        $this->assertSame('Si', $preguntas[1]->conditionOnPreviousOption);
    }

    public function test_una_opcion_que_no_existe_da_error_diciendo_cuales_hay(): void
    {
        // Un "no se puede" sin alternativas obliga a adivinar.
        $texto = "[obligatorias, una opcion: Excelente / Regular / Mal]\n"
            ."¿Que tal?\n"
            ."[opcionales, texto largo, si \"Pesimo\" en la anterior]\n"
            .'¿Por que?';

        $problemas = app(QuestionTextParser::class)->problems($texto);

        $this->assertCount(1, $problemas);
        $this->assertSame('condition_option_not_found', $problemas[0]->key);

        /*
         * Linea 4, la de la PREGUNTA, no la 3 de su cabecera.
         *
         * La condicion se comprueba contra lo que hay antes, y eso solo se
         * sabe al leer la pregunta. Señalar la cabecera diria donde se
         * escribio la condicion; señalar la pregunta dice cual no se va a
         * poder mostrar, que es lo que hay que arreglar.
         */
        $this->assertSame(4, $problemas[0]->line);
        $this->assertStringContainsString('Excelente', $problemas[0]->replacements['known']);
    }

    public function test_no_se_puede_condicionar_a_un_texto_libre(): void
    {
        // Una pregunta de texto libre no tiene opciones que elegir.
        $texto = "[opcionales, texto largo]\n"
            ."Cuentanos algo\n"
            ."[opcionales, texto corto, si \"Si\" en la anterior]\n"
            .'¿Y algo mas?';

        $problemas = app(QuestionTextParser::class)->problems($texto);

        $this->assertSame('condition_previous_has_no_options', $problemas[0]->key);
    }

    public function test_la_primera_pregunta_no_puede_ser_condicional(): void
    {
        // No hay nada anterior a lo que condicionar.
        $texto = "[opcionales, texto largo, si \"Si\" en la anterior]\n"
            .'¿Que paso?';

        $problemas = app(QuestionTextParser::class)->problems($texto);

        $this->assertSame('condition_without_previous', $problemas[0]->key);
    }

    public function test_condicional_no_es_un_tipo(): void
    {
        /*
         * Lo que el area usuaria escribio la primera vez. "Condicional" dice
         * CUANDO se muestra, no COMO se contesta: el tipo sigue siendo texto
         * largo.
         */
        $texto = "[obligatorias, si/no]\n"
            ."¿Dificultades?\n"
            ."[condicional si responde Si, texto largo]\n"
            .'¿Cuales?';

        $problemas = app(QuestionTextParser::class)->problems($texto);

        $this->assertSame('unknown_type', $problemas[0]->key);
        $this->assertSame(3, $problemas[0]->line);
    }
}
