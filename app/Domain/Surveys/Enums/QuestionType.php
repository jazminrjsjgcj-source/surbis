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
