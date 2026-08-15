<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\Exceptions\VersionConflict;
use App\Application\Surveys\Exceptions\VersionNotEditable;
use App\Application\Surveys\SaveBuilderState;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Guardado del borrador completo. RF-AO-BLD-001 a 003 y 010.
 */
final class BuilderStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_preguntas_y_opciones_en_orden(): void
    {
        $version = SurveyVersion::factory()->create();

        app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('Primera', options: [
                $this->opcion('Buena', 'buena', 5),
                $this->opcion('Mala', 'mala', 1),
            ]),
            $this->pregunta('Segunda'),
        ]);

        $this->assertSame(
            ['Primera' => 1, 'Segunda' => 2],
            SurveyQuestion::query()->orderBy('position')->pluck('position', 'text')->all(),
        );

        $this->assertSame(
            ['buena', 'mala'],
            SurveyQuestionOption::query()->orderBy('position')->pluck('value')->all(),
        );
    }

    public function test_lo_que_no_llega_en_la_lista_se_retira(): void
    {
        // El cliente manda el borrador ENTERO: lo que falta es porque se
        // borro, no porque se olvidara.
        $version = SurveyVersion::factory()->create();

        $lock = app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('Primera'),
            $this->pregunta('Segunda'),
        ]);

        $primera = SurveyQuestion::query()->where('text', 'Primera')->firstOrFail();

        app(SaveBuilderState::class)->execute($version->fresh(), $lock, [
            $this->pregunta('Primera', ulid: $primera->ulid),
        ]);

        $this->assertSame(1, SurveyQuestion::query()->count());
    }

    public function test_una_pregunta_existente_conserva_su_ulid(): void
    {
        /*
         * Se actualiza la fila en lugar de borrar y recrear. El ulid es lo
         * que las respuestas guardadas usaran para referirse a ella:
         * recrearla romperia ese vinculo aunque el texto fuera identico.
         */
        $version = SurveyVersion::factory()->create();
        $lock = app(SaveBuilderState::class)->execute($version, 0, [$this->pregunta('Original')]);

        $antes = SurveyQuestion::query()->firstOrFail();

        app(SaveBuilderState::class)->execute($version->fresh(), $lock, [
            $this->pregunta('Reescrita', ulid: $antes->ulid),
        ]);

        $despues = SurveyQuestion::query()->firstOrFail();

        $this->assertSame($antes->ulid, $despues->ulid);
        $this->assertSame('Reescrita', $despues->text);
    }

    public function test_un_conflicto_no_escribe_nada(): void
    {
        /*
         * El bloqueo se reclama DENTRO de la transaccion y antes de tocar
         * ninguna pregunta. Si otra peticion gano, la transaccion revierte
         * entera: un guardado a medias seria peor que uno rechazado.
         */
        $version = SurveyVersion::factory()->create();
        $lock = app(SaveBuilderState::class)->execute($version, 0, [$this->pregunta('Original')]);

        $this->assertSame(1, SurveyQuestion::query()->count());

        try {
            // Con el lock viejo: alguien guardo mientras tanto.
            app(SaveBuilderState::class)->execute($version->fresh(), $lock - 1, [
                $this->pregunta('Intrusa'),
                $this->pregunta('Otra intrusa'),
            ]);
            $this->fail('Se esperaba un conflicto.');
        } catch (VersionConflict) {
            // Nada cambio.
            $this->assertSame(1, SurveyQuestion::query()->count());
            $this->assertSame('Original', SurveyQuestion::query()->firstOrFail()->text);
        }
    }

    public function test_una_version_publicada_no_se_guarda(): void
    {
        // RF-AO-PUB-007 y RF-AO-BLD-009.
        $version = SurveyVersion::factory()->published(1)->create();

        $this->expectException(VersionNotEditable::class);

        app(SaveBuilderState::class)->execute($version, 0, [$this->pregunta('Intrusa')]);
    }

    public function test_no_se_repiten_valores_dentro_de_una_pregunta(): void
    {
        // RF-AO-BLD-010, con el valor repetido en el mensaje.
        $version = SurveyVersion::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('buena');

        app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('Con repetidos', options: [
                $this->opcion('Buena', 'buena', 5),
                $this->opcion('Muy buena', 'buena', 4),
            ]),
        ]);
    }

    public function test_dos_preguntas_si_pueden_tener_el_mismo_valor(): void
    {
        // La unicidad es POR pregunta. Dos preguntas distintas pueden ofrecer
        // "buena", y deben: son escalas independientes.
        $version = SurveyVersion::factory()->create();

        app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('Primera', options: [$this->opcion('Buena', 'buena', 5)]),
            $this->pregunta('Segunda', options: [$this->opcion('Buena', 'buena', 5)]),
        ]);

        $this->assertSame(2, SurveyQuestionOption::query()->where('value', 'buena')->count());
    }

    public function test_cambiar_a_un_tipo_sin_opciones_borra_las_que_hubiera(): void
    {
        $version = SurveyVersion::factory()->create();
        $lock = app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('Antes', options: [$this->opcion('Buena', 'buena', 5)]),
        ]);

        $question = SurveyQuestion::query()->firstOrFail();

        app(SaveBuilderState::class)->execute($version->fresh(), $lock, [
            $this->pregunta('Ahora', ulid: $question->ulid, type: QuestionType::LongText),
        ]);

        $this->assertSame(0, SurveyQuestionOption::query()->count());
    }

    public function test_un_tipo_desconocido_se_rechaza(): void
    {
        $version = SurveyVersion::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(SaveBuilderState::class)->execute($version, 0, [
            [...$this->pregunta('Rara'), 'type' => 'telepatia'],
        ]);
    }

    /** @return array<string, mixed> */
    private function pregunta(
        string $texto,
        ?string $ulid = null,
        QuestionType $type = QuestionType::SingleChoice,
        array $options = [],
    ): array {
        return [
            'ulid' => $ulid,
            'type' => $type->value,
            'text' => $texto,
            'help' => null,
            'is_required' => false,
            'limits' => [],
            'options' => $options,
        ];
    }

    /** @return array<string, mixed> */
    private function opcion(string $label, string $value, int $score): array
    {
        return [
            'ulid' => null,
            'label' => $label,
            'value' => $value,
            'score' => $score,
            'display' => 'text',
            'appearance' => null,
        ];
    }
}
