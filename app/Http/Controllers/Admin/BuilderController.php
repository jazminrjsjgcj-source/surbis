<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Surveys\Exceptions\VersionConflict;
use App\Application\Surveys\Exceptions\VersionNotEditable;
use App\Application\Surveys\OpenDraft;
use App\Application\Surveys\SaveBuilderState;
use App\Domain\Surveys\Enums\OptionDisplay;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\QuestionLimits;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BuilderStateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

/**
 * Constructor de preguntas. RF-AO-BLD-001 a 010.
 */
final class BuilderController extends Controller
{
    public function edit(Survey $survey, OpenDraft $open): InertiaResponse
    {
        $this->authorize('view', $survey);

        /*
         * Se abre el borrador al entrar. Quien viene a editar preguntas no
         * tiene por que saber que antes hay que crear una version.
         *
         * Si la encuesta esta archivada, la Policy ya lo impidio: update()
         * rechaza las archivadas, y aqui se usa view() porque una encuesta
         * publicada SI se puede abrir, en solo lectura. RF-AO-BLD-009.
         */
        $version = $survey->isArchived()
            ? $survey->publishedVersion ?? $survey->versions()->latest('version_number')->first()
            : $open->execute($survey);

        return Inertia::render('Admin/Builder', [
            'survey' => [
                'ulid' => $survey->ulid,
                'name' => $survey->name,
            ],
            'version' => $this->serialize($version),

            // RF-AO-BLD-009: el cliente necesita saberlo para no ofrecer
            // controles que el servidor va a rechazar. Ocultarlos no es
            // autorizar —eso lo hace BuilderGuard— pero ofrecer un boton que
            // siempre falla es una promesa falsa.
            'readOnly' => ! $version->isEditable(),

            'questionTypes' => $this->questionTypes(),
            'optionDisplays' => array_map(
                fn (OptionDisplay $d): string => $d->value,
                OptionDisplay::cases(),
            ),
        ]);
    }

    public function update(
        BuilderStateRequest $request,
        Survey $survey,
        OpenDraft $open,
        SaveBuilderState $save,
    ): JsonResponse {
        $this->authorize('update', $survey);

        $version = $open->execute($survey);

        try {
            $lock = $save->execute(
                $version,
                $request->integer('lock_version'),
                $request->array('questions'),
            );
        } catch (VersionConflict $conflicto) {
            /*
             * 409 con el estado actual del servidor.
             *
             * Se manda entero para que la pantalla pueda mostrar lo que hay
             * ahora sin una segunda peticion, y para que "sobrescribir" sea
             * releer y reintentar con el numero nuevo. No hay forma de
             * saltarse la comprobacion, ni siquiera desde aqui.
             */
            return response()->json([
                'message' => $conflicto->getMessage(),
                'expected' => $conflicto->expected,
                'actual' => $conflicto->actual,
                'version' => $this->serialize($conflicto->current->fresh()),
            ], Response::HTTP_CONFLICT);
        } catch (VersionNotEditable $bloqueado) {
            return response()->json(['message' => $bloqueado->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (InvalidArgumentException $invalido) {
            return response()->json(['message' => $invalido->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'lock_version' => $lock,
            'version' => $this->serialize($version->fresh()),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(SurveyVersion $version): array
    {
        $version->load(['questions.options', 'questions.condition.dependsOn', 'questions.condition.option']);

        return [
            'ulid' => $version->ulid,
            'number' => $version->version_number,
            'status' => $version->status->value,
            'lock_version' => (int) $version->lock_version,
            'questions' => $version->questions->map(fn ($question): array => [
                'ulid' => $question->ulid,
                'type' => $question->type->value,
                'text' => $question->text,
                'help' => $question->help,
                'is_required' => $question->is_required,
                'limits' => $question->limits->toArrayFor($question->type),

                /*
                 * La condicion viaja por ULID, no por id.
                 *
                 * El cliente reordena y crea preguntas antes de que existan
                 * en la base: los ids no le sirven para referenciarlas entre
                 * si, y exponerlos ademas revelaria cuantas preguntas hay en
                 * el sistema.
                 */
                'condition' => $question->condition === null ? null : [
                    'depends_on_ulid' => $question->condition->dependsOn->ulid,
                    'option_ulid' => $question->condition->option->ulid,
                ],
                'options' => $question->options->map(fn ($option): array => [
                    'ulid' => $option->ulid,
                    'label' => $option->label,
                    'value' => $option->value,
                    'score' => $option->score,
                    'display' => $option->display->value,
                    'appearance' => $option->appearance,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Los tipos, con lo que el cliente necesita saber de cada uno.
     *
     * Se manda desde el servidor en lugar de duplicarlo en TypeScript: si
     * React decidiera por su cuenta que tipos admiten opciones, esa lista y
     * la de QuestionType divergirian, y el constructor ofreceria opciones que
     * el servidor descarta al guardar.
     *
     * @return list<array<string, mixed>>
     */
    private function questionTypes(): array
    {
        return array_map(fn (QuestionType $type): array => [
            'value' => $type->value,
            'has_options' => $type->hasOptions(),
            'is_scored' => $type->isScored(),
            'limit_keys' => QuestionLimits::keysFor($type),
        ], QuestionType::cases());
    }
}
