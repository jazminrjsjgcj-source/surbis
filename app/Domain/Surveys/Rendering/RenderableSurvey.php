<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Rendering;

use App\Domain\Surveys\Enums\RenderLayout;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyVersion;

/**
 * Una version publicada, lista para pintarse. RNF-COL-012.
 *
 * EL MISMO objeto alimenta el quiosco, el QR, el enlace, el widget y la vista
 * previa. RNF-AO-BLD-004 y RNF-AO-PUB-002 prohiben dos renderizadores, y la
 * unica forma de cumplirlo de verdad es que haya una sola fuente de datos:
 * si cada canal armara los suyos, divergirian aunque el componente fuera uno.
 *
 * Lo que NO lleva: nada que el navegador no deba decidir. Ni la puntuacion de
 * las opciones, ni a quien se evalua, ni la version. RNF-COL-013.
 */
final class RenderableSurvey
{
    public function __construct(
        private readonly SurveyVersion $version,
        private readonly RenderLayout $layout,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $this->version->loadMissing([
            'survey',
            'questions.options.media',
            'questions.condition.dependsOn',
            'questions.condition.option',
        ]);

        $settings = $this->version->settings;

        return [
            'name' => $this->version->survey->name,
            'layout' => $this->layout->value,

            'introduction' => $settings->introduction,
            'thankYou' => $settings->thankYou,
            'allowBack' => $settings->allowBack,
            'commentMode' => $settings->commentMode->value,

            /*
             * El modo de identidad, para saber si se piden datos.
             *
             * RF-COL-022: la identificacion SOLO se muestra en identificado u
             * opcional. Que la pantalla no la pida no basta —el servidor
             * ademas los rechaza si llegan (RF-COL-023)— pero pedirlos en
             * anonimo seria una peticion que nunca se puede cumplir.
             */
            'identityMode' => $settings->identityMode->value,

            /*
             * Los segundos de inactividad viajan porque el quiosco los
             * necesita para reiniciar (RF-COL-012). En un enlace publico no
             * se usan: quien contesta desde su casa no comparte pantalla con
             * nadie.
             */
            'inactivitySeconds' => $settings->inactivitySeconds,

            'questions' => $this->version->questions
                ->sortBy('position')
                ->map(fn (SurveyQuestion $q): array => $this->question($q))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function question(SurveyQuestion $question): array
    {
        return [
            'ulid' => $question->ulid,
            'type' => $question->type->value,
            'text' => $question->text,
            'help' => $question->help,
            'isRequired' => $question->is_required,

            // Los limites decide el servidor cuales aplican a cada tipo: el
            // renderizador no tiene que saber que un numero lleva min y max y
            // un texto largo lleva longitudes.
            'limits' => $question->limits->toArrayFor($question->type),

            'options' => $question->options
                ->sortBy('position')
                ->map(fn ($option): array => [
                    'ulid' => $option->ulid,
                    'label' => $option->label,
                    'display' => $option->display->value,

                    /*
                     * La imagen con su nombre accesible. RNF-COL-011: todo
                     * boton con imagen necesita nombre aunque el texto
                     * visible este oculto.
                     */
                    'image' => $option->media === null ? null : [
                        'url' => $option->media->url(),
                        'alt' => $option->media->accessibleName(),
                    ],

                    /*
                     * La PUNTUACION NO VIAJA. RNF-COL-013.
                     *
                     * El navegador no decide cuanto vale una respuesta: manda
                     * que opcion se eligio, y el servidor busca su puntuacion.
                     * Enviarla seria dejar que quien contesta la cambie.
                     */
                ])
                ->values()
                ->all(),

            /*
             * La condicion, por ULID de opcion.
             *
             * Se manda porque mostrar u ocultar una pregunta tiene que ser
             * inmediato: preguntar al servidor en cada respuesta haria el
             * quiosco inusable. Que la condicion se cumpla de verdad lo
             * comprueba OTRA VEZ el servidor al recibir (Fase 9): esto es
             * para que la pantalla reaccione, no para decidir.
             */
            'condition' => $question->condition === null ? null : [
                'dependsOn' => $question->condition->dependsOn->ulid,
                'option' => $question->condition->option->ulid,
            ],
        ];
    }
}
