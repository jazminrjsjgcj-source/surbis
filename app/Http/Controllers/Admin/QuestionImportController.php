<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Surveys\OpenDraft;
use App\Application\Surveys\SaveBuilderState;
use App\Domain\Surveys\Import\ImportProblem;
use App\Domain\Surveys\Import\ParsedQuestion;
use App\Domain\Surveys\Import\QuestionTextParser;
use App\Domain\Surveys\Import\QuestionTypeVocabulary;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportQuestionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Importar preguntas desde texto. TASK-025 · D-027.
 *
 * Pantalla propia y no un panel dentro del constructor: el constructor ya
 * tiene dos columnas y bastante que mirar, y esto se hace una vez al empezar
 * una encuesta, no a cada rato.
 */
final class QuestionImportController extends Controller
{
    public function create(Survey $survey, QuestionTypeVocabulary $vocabulary): InertiaResponse
    {
        $this->authorize('update', $survey);

        return Inertia::render('Admin/ImportQuestions', [
            'survey' => ['ulid' => $survey->ulid, 'name' => $survey->name],
            'action' => route('admin.surveys.import.store', $survey),
            'previewUrl' => route('admin.surveys.import.preview', $survey),
            'builderUrl' => route('admin.surveys.builder', $survey),

            // Los tipos disponibles se muestran en la ayuda. Escribirlos en
            // el componente crearia una segunda lista que se desincronizaria
            // el dia que se anada un tipo.
            'types' => $vocabulary->canonicalNames(),
        ]);
    }

    /**
     * Analiza sin guardar. RF-AO-BLD-006 en espiritu: ver antes de aplicar.
     *
     * Se separa del guardado para que la pantalla pueda enseñar lo que va a
     * entrar. Importar a ciegas y descubrir despues que el tipo era otro
     * obliga a deshacerlo todo a mano.
     */
    public function preview(
        ImportQuestionsRequest $request,
        Survey $survey,
        QuestionTextParser $parser,
    ): RedirectResponse {
        $this->authorize('update', $survey);

        $text = $request->string('text')->toString();
        $problems = $parser->problems($text);

        if ($problems->isNotEmpty()) {
            return back()->with('import_problems', $problems->map(
                fn (ImportProblem $problem): array => $problem->toArray()
            )->all())->withInput();
        }

        return back()->with('import_preview', $parser->parse($text)->map(
            fn (ParsedQuestion $question): array => [
                'type' => $question->type->value,
                'text' => $question->text,
                'is_required' => $question->isRequired,
                'options' => count($question->options),
            ]
        )->all())->withInput();
    }

    public function store(
        ImportQuestionsRequest $request,
        Survey $survey,
        QuestionTextParser $parser,
        OpenDraft $open,
        SaveBuilderState $save,
    ): RedirectResponse {
        $this->authorize('update', $survey);

        $text = $request->string('text')->toString();
        $problems = $parser->problems($text);

        if ($problems->isNotEmpty()) {
            // Si algo esta mal NO se importa nada: importar a medias deja una
            // encuesta que hay que revisar entera para saber que entro.
            return back()->with('import_problems', $problems->map(
                fn (ImportProblem $problem): array => $problem->toArray()
            )->all())->withInput();
        }

        $draft = $open->execute($survey);
        $importadas = $parser->parse($text)
            ->map(fn (ParsedQuestion $question): array => $question->toBuilderState())
            ->all();

        /*
         * Se guarda por la MISMA via que lo escrito a mano.
         *
         * SaveBuilderState aplica las reglas —valores unicos, posiciones,
         * limites por tipo— y si la importacion tuviera su propio camino de
         * escritura, sus reglas y las del constructor divergirian. Es la
         * trampa del ANEXO 1 seccion 23.
         */
        $existentes = $request->string('mode')->toString() === 'replace'
            ? []
            : $this->currentState($draft);

        $save->execute($draft, (int) $draft->lock_version, [...$existentes, ...$importadas]);

        return redirect()->route('admin.surveys.builder', $survey)
            ->with('status', __('interface.import.done', ['count' => count($importadas)]));
    }

    /**
     * Convierte "si tal opcion en la anterior" en una condicion de verdad.
     *
     * No se puede hacer en ParsedQuestion: la condicion necesita el ULID de
     * una opcion de OTRA pregunta, y ahi cada una se convierte por separado
     * sin saber que hay alrededor.
     *
     * Los ULID se generan AQUI, antes de guardar. SaveBuilderState los
     * respeta cuando vienen puestos, y necesitarlos antes es lo que obliga a
     * generarlos: una condicion apunta a una opcion que todavia no existe en
     * la base.
     *
     * @param  Collection<int, ParsedQuestion>  $analizadas
     * @return list<array<string, mixed>>
     */
    private function withConditions(Collection $analizadas): array
    {
        $estado = [];

        foreach ($analizadas as $indice => $pregunta) {
            $fila = $pregunta->toBuilderState();

            $fila['ulid'] = (string) Str::ulid();

            $fila['options'] = array_map(
                fn (array $option): array => [...$option, 'ulid' => (string) Str::ulid()],
                $fila['options'],
            );

            if ($pregunta->conditionOnPreviousOption !== null && $indice > 0) {
                $anterior = $estado[$indice - 1];

                $opcion = collect($anterior['options'])->first(
                    fn (array $o): bool => mb_strtolower($o['label'])
                        === mb_strtolower($pregunta->conditionOnPreviousOption),
                );

                if ($opcion !== null) {
                    $fila['condition'] = [
                        'depends_on_ulid' => $anterior['ulid'],
                        'option_ulid' => $opcion['ulid'],
                    ];
                }
            }

            $estado[] = $fila;
        }

        return $estado;
    }

    /**
     * Lo que ya hay en el borrador, con la forma que espera SaveBuilderState.
     *
     * @return list<array<string, mixed>>
     */
    private function currentState(SurveyVersion $draft): array
    {
        $draft->loadMissing(['questions.options']);

        return $draft->questions->map(fn ($question): array => [
            'ulid' => $question->ulid,
            'type' => $question->type->value,
            'text' => $question->text,
            'help' => $question->help,
            'is_required' => $question->is_required,
            'limits' => $question->limits->toArrayFor($question->type),
            'options' => $question->options->map(fn ($option): array => [
                'ulid' => $option->ulid,
                'label' => $option->label,
                'value' => $option->value,
                'score' => $option->score,
                'display' => $option->display->value,
                'appearance' => $option->appearance,
            ])->all(),
        ])->all();
    }
}
