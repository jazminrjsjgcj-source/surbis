<?php

declare(strict_types=1);

namespace App\Application\Responses;

use App\Application\Responses\Exceptions\ResponseRejected;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Responses\BlindIndex;
use App\Domain\Responses\Models\Response;
use App\Domain\Surveys\Enums\IdentityMode;
use App\Domain\Surveys\Models\SurveyQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Guardar una respuesta. RF-COL-020 · RNF-COL-013 · RNF-AO-RES-*.
 *
 * Todo lo que importa se decide AQUI, no en el navegador:
 *
 *   la puntuacion         se busca en las opciones, no se recibe
 *   la fotografia         se copia del deployment, no se recibe
 *   la validacion         se repite entera, aunque el cliente ya validara
 *   que preguntas valen   se recalcula la logica condicional
 *
 * El cliente valida para que la pantalla reaccione rapido (RNF-COL-009), no
 * para decidir. Quien envie a mano puede saltarse esa validacion; esta no.
 */
final class SubmitResponse
{
    public function __construct(private readonly BlindIndex $blindIndex) {}

    /**
     * @param  array<string, string|list<string>>  $answers  Por ULID de pregunta.
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, consent?: bool}  $identity
     *
     * @throws ResponseRejected
     */
    public function execute(
        Deployment $deployment,
        array $answers,
        string $idempotencyKey,
        ?string $comment = null,
        array $identity = [],
    ): Response {
        /*
         * Idempotencia ANTES de nada. RNF-AO-RES-*.
         *
         * Sin conexion, el quiosco reintenta: si el primer envio llego y la
         * confirmacion no, el segundo tiene que devolver la misma respuesta,
         * no crear otra. Sin esto los resultados salen inflados y nadie lo
         * nota.
         */
        $existente = Response::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existente !== null) {
            return $existente;
        }

        if (! $deployment->isApplying()) {
            throw ResponseRejected::notApplying();
        }

        $version = $deployment->version;
        $version->loadMissing(['survey', 'questions.options', 'questions.condition.option']);

        $visibles = $this->visibleQuestions($version->questions, $answers);
        $this->validate($visibles, $answers);
        $this->validateIdentity($version->settings->identityMode, $identity);

        return DB::transaction(function () use (
            $deployment, $version, $visibles, $answers, $idempotencyKey, $comment, $identity
        ): Response {
            $response = Response::query()->create([
                ...$this->references($deployment),
                ...$this->snapshots($deployment, $version),
                ...$this->identityColumns($version->settings->identityMode, $identity),

                'comment' => $comment,
                'idempotency_key' => $idempotencyKey,
                'submitted_at' => now(),
            ]);

            $puntos = $this->storeAnswers($response, $visibles, $answers);

            /*
             * La puntuacion se guarda YA CALCULADA.
             *
             * Leerla de las opciones al consultar significaria que editar una
             * escala cambia retroactivamente todos los resultados historicos.
             */
            $response->forceFill([
                'score' => $puntos['score'],
                'max_score' => $puntos['max'],
            ])->save();

            return $response;
        });
    }

    /**
     * Que preguntas cuentan, segun lo contestado.
     *
     * Se recalcula la logica condicional EN EL SERVIDOR aunque el cliente ya
     * la aplicara: quien envie a mano podria mandar respuestas a preguntas
     * que nunca se le mostraron.
     *
     * @param  Collection<int, SurveyQuestion>  $questions
     * @param  array<string, string|list<string>>  $answers
     * @return Collection<int, SurveyQuestion>
     */
    private function visibleQuestions(Collection $questions, array $answers): Collection
    {
        $porUlid = $questions->keyBy('ulid');

        return $questions->sortBy('position')->filter(
            function (SurveyQuestion $question) use ($porUlid, $answers): bool {
                $condition = $question->condition;

                if ($condition === null) {
                    return true;
                }

                $origen = $porUlid->get($condition->dependsOn->ulid);
                $respuesta = $answers[$origen?->ulid ?? ''] ?? null;

                if ($respuesta === null) {
                    return false;
                }

                $esperada = $condition->option->ulid;

                return is_array($respuesta)
                    ? in_array($esperada, $respuesta, true)
                    : $respuesta === $esperada;
            }
        )->values();
    }

    /**
     * @param  Collection<int, SurveyQuestion>  $visibles
     * @param  array<string, string|list<string>>  $answers
     */
    private function validate(Collection $visibles, array $answers): void
    {
        $ulidsVisibles = $visibles->pluck('ulid')->all();

        // Contestar algo que no se mostraba es senal de envio manipulado.
        foreach (array_keys($answers) as $ulid) {
            if (! in_array($ulid, $ulidsVisibles, true)) {
                throw ResponseRejected::hiddenQuestion(0);
            }
        }

        foreach ($visibles as $question) {
            $respuesta = $answers[$question->ulid] ?? null;
            $vacia = $respuesta === null || $respuesta === '' || $respuesta === [];

            if ($vacia && $question->is_required) {
                throw ResponseRejected::invalidAnswer($question->position, 'required');
            }

            if ($vacia) {
                continue;
            }

            $problema = $question->limits->problemWith($question->type, $respuesta);

            if ($problema !== null) {
                throw ResponseRejected::invalidAnswer($question->position, $problema);
            }
        }
    }

    /**
     * RF-COL-023 y RF-COL-024.
     *
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, consent?: bool}  $identity
     */
    private function validateIdentity(IdentityMode $mode, array $identity): void
    {
        $hayDatos = ($identity['name'] ?? null) !== null
            || ($identity['email'] ?? null) !== null
            || ($identity['phone'] ?? null) !== null;

        if (! $hayDatos) {
            return;
        }

        /*
         * En modo anonimo NO se aceptan datos, aunque lleguen.
         *
         * Si alguien los envia a mano, guardarlos convertiria una encuesta
         * anonima en identificada sin que nadie lo decidiera. RF-COL-023.
         */
        if ($mode === IdentityMode::Anonymous) {
            throw ResponseRejected::identityNotAllowed();
        }

        // RF-COL-024: con datos personales, consentimiento explicito.
        if (($identity['consent'] ?? false) !== true) {
            throw ResponseRejected::consentMissing();
        }
    }

    /** @return array<string, mixed> */
    private function references(Deployment $deployment): array
    {
        $device = $deployment->device;

        return [
            'organization_id' => $deployment->organization_id,
            'deployment_id' => $deployment->id,
            'survey_version_id' => $deployment->survey_version_id,

            /*
             * La ubicacion sale del DEPLOYMENT, no del navegador.
             *
             * RNF-COL-013: el navegador no decide sucursal ni organizacion. Si
             * las mandara, bastaria con cambiarlas para atribuir respuestas a
             * otra oficina.
             *
             * Un dispositivo aporta su sucursal y su area: es donde esta.
             */
            'branch_id' => $deployment->branch_id ?? $device?->branch_id,
            'area_id' => $deployment->area_id ?? $device?->area_id,
            'device_id' => $deployment->device_id,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshots(Deployment $deployment, $version): array
    {
        $device = $deployment->device;
        $branch = $deployment->branch ?? $device?->branch;

        return [
            /*
             * Como se llamaban las cosas AHORA.
             *
             * Si una sucursal se renombra, una respuesta de hoy se dio con el
             * nombre de hoy. Comparar periodos con los nombres cambiando bajo
             * los pies produce informes que mienten sin avisar.
             */
            'organization_name' => $deployment->organization->name,
            'branch_name' => $branch?->name,
            'area_name' => ($deployment->area ?? $device?->area)?->name,
            'device_name' => $device?->name,
            'staff_member_name' => null,
            'survey_version_number' => $version->version_number,
            'survey_name' => $version->survey->name,
            'channel' => $deployment->channel->value,
            'identity_mode' => $version->settings->identityMode,
        ];
    }

    /**
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, consent?: bool}  $identity
     * @return array<string, mixed>
     */
    private function identityColumns(IdentityMode $mode, array $identity): array
    {
        if ($mode === IdentityMode::Anonymous) {
            // Ni siquiera se guarda la fecha de consentimiento: no hay nada
            // que consentir.
            return [];
        }

        $email = $identity['email'] ?? null;
        $phone = $identity['phone'] ?? null;

        return [
            'respondent_name' => $identity['name'] ?? null,
            'respondent_email' => $email,
            'respondent_phone' => $phone,

            // Los indices ciegos, para poder buscar sin descifrar.
            'respondent_email_index' => $this->blindIndex->ofNullable($email),
            'respondent_phone_index' => $this->blindIndex->ofNullable($phone),

            'consent_given_at' => ($identity['consent'] ?? false) === true ? now() : null,
        ];
    }

    /**
     * @param  Collection<int, SurveyQuestion>  $visibles
     * @param  array<string, string|list<string>>  $answers
     * @return array{score: ?int, max: ?int}
     */
    private function storeAnswers(Response $response, Collection $visibles, array $answers): array
    {
        $score = 0;
        $max = 0;
        $puntua = false;

        foreach ($visibles as $posicion => $question) {
            $respuesta = $answers[$question->ulid] ?? null;

            if ($respuesta === null || $respuesta === '' || $respuesta === []) {
                continue;
            }

            $elegidas = is_array($respuesta) ? $respuesta : [$respuesta];

            if ($question->type->hasOptions()) {
                foreach ($elegidas as $ulidOpcion) {
                    $option = $question->options->firstWhere('ulid', $ulidOpcion);

                    if ($option === null) {
                        continue;
                    }

                    $response->answers()->create([
                        'survey_question_id' => $question->id,
                        'option_id' => $option->id,
                        'question_text' => $question->text,
                        'question_type' => $question->type,
                        'option_label' => $option->label,
                        'value' => null,

                        /*
                         * La puntuacion sale de la OPCION guardada, nunca de
                         * lo que mando el navegador. RNF-COL-013.
                         */
                        'score' => $option->score,
                        'position' => $posicion + 1,
                    ]);

                    if ($option->score !== null) {
                        $score += $option->score;
                        $puntua = true;
                    }
                }

                if ($question->type->isScored()) {
                    $max += (int) $question->options->max('score');
                }

                continue;
            }

            $response->answers()->create([
                'survey_question_id' => $question->id,
                'option_id' => null,
                'question_text' => $question->text,
                'question_type' => $question->type,
                'option_label' => null,
                'value' => (string) $respuesta,
                'score' => null,
                'position' => $posicion + 1,
            ]);
        }

        return $puntua
            ? ['score' => $score, 'max' => $max]
            : ['score' => null, 'max' => null];
    }
}
