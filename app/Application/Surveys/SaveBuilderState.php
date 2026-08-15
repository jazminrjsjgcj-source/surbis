<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Application\Surveys\Exceptions\VersionConflict;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Surveys\ConditionRules;
use App\Domain\Surveys\Enums\OptionDisplay;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionCondition;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\QuestionLimits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Guarda el borrador ENTERO. RF-AO-BLD-001 a 003 y 010.
 *
 * El constructor mantiene su estado en el cliente y manda la lista completa
 * tras un segundo sin cambios. Eso hace que anadir, duplicar, reordenar y
 * borrar sean operaciones locales: aqui solo llega el resultado.
 *
 * Sustituye a SaveQuestion, DuplicateQuestion, DeleteQuestion y
 * ReorderQuestions, escritos antes de esa decision. Las REGLAS que contenian
 * siguen aqui —limites por tipo, valores unicos, opciones que desaparecen al
 * cambiar de tipo—; lo que desaparece es la granularidad, que ya no la usa
 * nadie.
 */
final class SaveBuilderState
{
    public function __construct(
        private readonly BuilderGuard $guard,
        private readonly ConditionRules $conditions,
        private readonly LockVersion $lock,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  list<array{ulid: ?string, type: string, text: string, help: ?string, is_required: bool, limits: array<string, mixed>, options: list<array{ulid: ?string, label: string, value: string, score: ?int, display: string, appearance: ?array<string, mixed>}>}>  $questions
     * @return int El lock_version nuevo.
     *
     * @throws VersionConflict
     */
    public function execute(SurveyVersion $version, int $expectedLock, array $questions): int
    {
        $this->guard->ensureEditable($version);
        $this->validate($questions);
        $this->validateConditions($questions);

        return DB::transaction(function () use ($version, $expectedLock, $questions): int {
            /*
             * El bloqueo se reclama DENTRO de la transaccion y ANTES de
             * escribir nada. Si otra peticion gano, esto lanza y la
             * transaccion revierte sin haber tocado una sola pregunta.
             */
            $nuevoLock = $this->lock->claim($version, $expectedLock);

            $existentes = SurveyQuestion::query()
                ->where('survey_version_id', $version->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('ulid');

            $conservadas = [];

            foreach ($questions as $indice => $datos) {
                $question = $this->saveQuestion($version, $existentes, $datos, $indice + 1);
                $conservadas[] = $question->ulid;

                $this->saveOptions($question, $datos['options']);
            }

            /*
             * Las condiciones se borran ANTES que las preguntas.
             *
             * depends_on_question_id es restrictOnDelete: si quedara una
             * condicion apuntando a una pregunta que se va, la base rechazaria
             * el borrado con un error de clave foranea que no dice nada util.
             *
             * Ya se comprobo en validateConditions() que ninguna condicion
             * superviviente se queda huerfana, asi que aqui solo se limpian
             * las de las preguntas que desaparecen.
             */
            SurveyQuestionCondition::query()
                ->whereIn('survey_question_id', SurveyQuestion::query()
                    ->where('survey_version_id', $version->id)
                    ->whereNotIn('ulid', $conservadas === [] ? [''] : $conservadas)
                    ->select('id'))
                ->delete();

            SurveyQuestion::query()
                ->where('survey_version_id', $version->id)
                ->whereNotIn('ulid', $conservadas === [] ? [''] : $conservadas)
                ->delete();

            $this->saveConditions($version, $questions);

            $this->audit->record('survey_version.builder_saved', $version, [
                'questions' => count($questions),
                'lock_version' => $nuevoLock,
            ]);

            return $nuevoLock;
        });
    }

    /**
     * @param  Collection<string, SurveyQuestion>  $existentes
     * @param  array<string, mixed>  $datos
     */
    private function saveQuestion(
        SurveyVersion $version,
        mixed $existentes,
        array $datos,
        int $position,
    ): SurveyQuestion {
        $type = QuestionType::from($datos['type']);

        $atributos = [
            'organization_id' => $version->organization_id,
            'type' => $type,
            'text' => $datos['text'],
            'help' => $datos['help'],
            'is_required' => $datos['is_required'],
            'limits' => QuestionLimits::fromArray($datos['limits']),
            'position' => $position,
        ];

        $ulid = $datos['ulid'] ?? null;

        if ($ulid !== null && $existentes->has($ulid)) {
            /** @var SurveyQuestion $question */
            $question = $existentes[$ulid];
            $tipoAnterior = $question->type;

            $question->forceFill($atributos)->save();

            /*
             * Cambiar a un tipo sin opciones borra las que hubiera. Un texto
             * libre con cuatro opciones colgando es un registro que nadie sabe
             * interpretar: no se muestran, pero estan.
             */
            if ($tipoAnterior->hasOptions() && ! $type->hasOptions()) {
                $question->options()->delete();
            }

            return $question;
        }

        return SurveyQuestion::query()->create([
            'survey_version_id' => $version->id,
            ...$atributos,
        ]);
    }

    /** @param list<array<string, mixed>> $options */
    private function saveOptions(SurveyQuestion $question, array $options): void
    {
        if (! $question->hasOptions()) {
            // Ya se borraron al cambiar de tipo; aqui solo se evita crearlas.
            return;
        }

        $existentes = $question->options()->get()->keyBy('ulid');
        $conservadas = [];

        foreach ($options as $indice => $datos) {
            $atributos = [
                'organization_id' => $question->organization_id,
                'label' => $datos['label'],
                'value' => $datos['value'],
                'score' => $datos['score'],
                'display' => OptionDisplay::from($datos['display']),

                /*
                 * La imagen se resuelve por ULID contra lo que ESTA
                 * organizacion puede usar.
                 *
                 * Sin usableBy(), un ULID de otra organizacion se guardaria
                 * sin mas: bastaria con enviarlo a mano para mostrar una
                 * imagen ajena en una encuesta propia. RNF-GEN-005.
                 */
                'media_id' => $this->resolveMedia($question->organization_id, $datos['media_ulid'] ?? null),
                'appearance' => $datos['appearance'],
                'position' => $indice + 1,
            ];

            $ulid = $datos['ulid'] ?? null;

            if ($ulid !== null && $existentes->has($ulid)) {
                /*
                 * Se actualiza la fila en lugar de borrar y recrear: su ulid
                 * es lo que las respuestas guardadas usan para referirse a
                 * ella, y recrearla romperia ese vinculo aunque el contenido
                 * fuera identico.
                 */
                $existentes[$ulid]->forceFill($atributos)->save();
                $conservadas[] = $ulid;

                continue;
            }

            $question->options()->create($atributos);
        }

        $question->options()
            ->whereNotIn('ulid', $conservadas === [] ? [''] : $conservadas)
            ->whereIn('ulid', $existentes->keys())
            ->delete();
    }

    /**
     * Ninguna condicion puede apuntar hacia delante. RF-AO-BLD-007.
     *
     * Decision del area usuaria: las condiciones NUNCA se eliminan solas al
     * reordenar. El movimiento se rechaza, aqui y en el constructor.
     *
     * Retirar una condicion sin que nadie lo pida es perder trabajo ajeno, y
     * el aviso llegaria cuando ya no se puede deshacer.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    private function validateConditions(array $questions): void
    {
        $rotas = $this->conditions->forwardConditions($questions);

        if ($rotas->isEmpty()) {
            return;
        }

        $posiciones = $rotas->pluck('position')->implode(', ');

        throw new InvalidArgumentException(
            "Este orden dejaria condiciones apuntando hacia delante en las preguntas: {$posiciones}."
        );
    }

    /**
     * Las condiciones, despues de que existan todas las preguntas.
     *
     * Van al final porque una condicion referencia OTRA pregunta: hasta que
     * no se han guardado todas, la mitad de los ulid no existen todavia.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    private function saveConditions(SurveyVersion $version, array $questions): void
    {
        $guardadas = SurveyQuestion::query()
            ->where('survey_version_id', $version->id)
            ->with('options')
            ->get()
            ->keyBy('position');

        foreach ($questions as $indice => $datos) {
            $question = $guardadas->get($indice + 1);

            if ($question === null) {
                continue;
            }

            $condition = $datos['condition'] ?? null;

            if ($condition === null) {
                $question->condition()?->delete();

                continue;
            }

            $origen = $guardadas->first(
                fn (SurveyQuestion $candidata): bool => $candidata->ulid === $condition['depends_on_ulid']
            );

            $opcion = $origen?->options->firstWhere('ulid', $condition['option_ulid']);

            /*
             * Si la opcion elegida ya no existe —alguien la borro en el mismo
             * guardado— la condicion se descarta en lugar de guardar una
             * referencia rota. Es el unico caso en que una condicion
             * desaparece sola, y ocurre porque su objeto desaparecio.
             */
            if ($origen === null || $opcion === null) {
                $question->condition()?->delete();

                continue;
            }

            SurveyQuestionCondition::query()->updateOrCreate(
                ['survey_question_id' => $question->id],
                [
                    'organization_id' => $version->organization_id,
                    'depends_on_question_id' => $origen->id,
                    'option_id' => $opcion->id,
                ],
            );
        }
    }

    /**
     * El id de la imagen, solo si esta organizacion puede usarla.
     *
     * Recibe el organization_id y no la version: saveOptions trabaja sobre
     * una SurveyQuestion, que ya lo lleva. Pedir la version obligaba a
     * arrastrarla por una firma que no la necesita.
     */
    private function resolveMedia(int $organizationId, ?string $ulid): ?int
    {
        if ($ulid === null) {
            return null;
        }

        return MediaItem::query()
            ->usableBy($organizationId)
            ->where('ulid', $ulid)
            ->value('id');
    }

    /** @param list<array<string, mixed>> $questions */
    private function validate(array $questions): void
    {
        foreach ($questions as $datos) {
            $type = QuestionType::tryFrom($datos['type'] ?? '');

            if ($type === null) {
                throw new InvalidArgumentException("Tipo de pregunta desconocido: {$datos['type']}.");
            }

            if (! $type->hasOptions()) {
                continue;
            }

            /*
             * RF-AO-BLD-010, comprobado antes de tocar nada.
             *
             * La base tambien lo impide, y ahi esta la garantia. Aqui se mira
             * para poder decir CUAL es el valor repetido: una violacion de
             * restriccion solo dice que la hubo.
             */
            $valores = array_column($datos['options'] ?? [], 'value');
            $repetidos = array_unique(array_diff_assoc($valores, array_unique($valores)));

            if ($repetidos !== []) {
                throw new InvalidArgumentException(
                    'Hay valores de opcion repetidos en "'.$datos['text'].'": '.implode(', ', $repetidos)
                );
            }
        }
    }
}
