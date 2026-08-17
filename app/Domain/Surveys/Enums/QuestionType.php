<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Enums;

/**
 * Los nueve tipos de pregunta. RF-COL-015.
 *
 * El tipo decide tres cosas que el resto del sistema pregunta constantemente:
 * si admite opciones, si esas opciones puntuan, y que limites tienen sentido.
 * Tenerlas aqui evita que cada pantalla las deduzca por su cuenta y llegue a
 * conclusiones distintas.
 */
enum QuestionType: string
{
    /** Caritas. Las imagenes salen de la biblioteca multimedia, no son emojis (D-004). */
    case Smiley = 'smiley';

    /** Estrellas o similar. Puntua por posicion. */
    case Rating = 'rating';

    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case YesNo = 'yes_no';
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Number = 'number';
    case Date = 'date';

    /**
     * Si el tipo se define con una lista de opciones editables.
     *
     * yes_no NO la tiene: sus dos respuestas son fijas. Dejar que alguien
     * edite las opciones de un si/no permitiria crear un "si/no" con cuatro
     * respuestas, y entonces el tipo dejaria de significar nada.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [
            self::Smiley,
            self::Rating,
            self::SingleChoice,
            self::MultipleChoice,
        ], true);
    }

    /**
     * Las opciones que este tipo trae PUESTAS, sin poder cambiarse.
     *
     * Si/no las tiene: son siempre las mismas dos. hasOptions() dice si se
     * pueden EDITAR, que es distinto —dejar editarlas permitiria crear un
     * si/no con cuatro respuestas y entonces el tipo dejaria de significar
     * nada—.
     *
     * Pero EXISTIR tienen que existir. El renderizador pinta los botones
     * recorriendo las opciones, la analitica cuenta cuantos dijeron que si, y
     * la logica condicional necesita una opcion concreta a la que apuntar.
     * Sin ellas, una pregunta de si/no no se puede contestar.
     *
     * @return list<array{label: string, value: string, score: int}>
     */
    public function fixedOptions(): array
    {
        if ($this !== self::YesNo) {
            return [];
        }

        /*
         * "Si" primero, con la puntuacion mas alta.
         *
         * Es el mismo criterio que en el resto del sistema: las opciones se
         * declaran de mejor a peor. Un "si" no siempre es lo bueno —"¿tuvo
         * dificultades?"— pero invertirlo por pregunta no se puede decidir
         * automaticamente, y quien necesite otra cosa usa "una opcion".
         */
        return [
            ['label' => 'Si', 'value' => 'si', 'score' => 2],
            ['label' => 'No', 'value' => 'no', 'score' => 1],
        ];
    }

    /** Si el tipo trae opciones puestas que no se editan. */
    public function hasFixedOptions(): bool
    {
        return $this->fixedOptions() !== [];
    }

    /**
     * Si las respuestas de este tipo contribuyen a una puntuacion.
     *
     * Es lo que separa "satisfaccion" de "dato": un comentario libre o una
     * fecha no promedian. La analitica de la Fase 12 depende de esta
     * distincion, y calcularla alli seria repetirla.
     */
    public function isScored(): bool
    {
        return in_array($this, [
            self::Smiley,
            self::Rating,
            self::SingleChoice,
            self::YesNo,
        ], true);
    }

    public function allowsMultipleAnswers(): bool
    {
        return $this === self::MultipleChoice;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
