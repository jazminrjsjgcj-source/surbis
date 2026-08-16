<?php

declare(strict_types=1);

namespace App\Domain\Surveys;

use App\Domain\Surveys\Enums\QuestionType;

/**
 * Los limites de una pregunta, con forma declarada por tipo.
 *
 * RF-AO-BLD-002 habla de "limites" en singular, pero cada tipo tiene los
 * suyos: un numero tiene minimo y maximo, un texto una longitud, una fecha un
 * rango, y una seleccion multiple cuantas opciones admite.
 *
 * Nueve columnas nullable de las que cada tipo usaria dos seria un esquema
 * lleno de huecos que no explica que significa cada uno. Aqui se declaran una
 * vez y de ahi salen las reglas de validacion.
 */
final class QuestionLimits
{
    public function __construct(
        // Numero
        public readonly ?float $min = null,
        public readonly ?float $max = null,

        // Texto corto y largo
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,

        // Fecha
        public readonly ?string $minDate = null,
        public readonly ?string $maxDate = null,

        // Seleccion multiple
        public readonly ?int $minSelections = null,
        public readonly ?int $maxSelections = null,

        // Rating: cuantos pasos tiene la escala
        public readonly ?int $steps = null,
    ) {}

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            min: self::float($data['min'] ?? null),
            max: self::float($data['max'] ?? null),
            minLength: self::int($data['min_length'] ?? null),
            maxLength: self::int($data['max_length'] ?? null),
            minDate: self::text($data['min_date'] ?? null),
            maxDate: self::text($data['max_date'] ?? null),
            minSelections: self::int($data['min_selections'] ?? null),
            maxSelections: self::int($data['max_selections'] ?? null),
            steps: self::int($data['steps'] ?? null),
        );
    }

    /**
     * Solo se guardan los limites que el tipo usa.
     *
     * Si una pregunta cambia de numero a texto, sus min y max dejan de tener
     * sentido: conservarlos dejaria datos que nadie lee y que alguien acabaria
     * interpretando como si significaran algo.
     *
     * @return array<string, mixed>
     */
    /**
     * Que le falta a una respuesta para cumplir estos limites.
     *
     * Devuelve una CLAVE —"min_length", "max"— o null si esta bien. La clave
     * viaja a la excepcion y de ahi a la traduccion: el dominio no habla
     * espanol, para que la API futura pueda interpretarla.
     *
     * Vive aqui y no en el caso de uso porque los limites saben validarse a
     * si mismos: quien los use no tiene que recordar que un numero mira min y
     * max y un texto mira longitudes.
     *
     * @param  string|list<string>  $answer
     */
    public function problemWith(QuestionType $type, string|array $answer): ?string
    {
        if (is_array($answer)) {
            $cuantas = count($answer);

            if ($this->minSelections !== null && $cuantas < $this->minSelections) {
                return 'min_selections';
            }

            if ($this->maxSelections !== null && $cuantas > $this->maxSelections) {
                return 'max_selections';
            }

            return null;
        }

        if ($type === QuestionType::ShortText || $type === QuestionType::LongText) {
            /*
             * mb_strlen y no strlen: con acentos y ñ, strlen cuenta BYTES.
             * "Mañana" mediria 7 y no 6, y un limite de 6 rechazaria un texto
             * valido. En espanol y en arabe eso no es un caso raro.
             */
            $largo = mb_strlen($answer);

            if ($this->minLength !== null && $largo < $this->minLength) {
                return 'min_length';
            }

            if ($this->maxLength !== null && $largo > $this->maxLength) {
                return 'max_length';
            }

            return null;
        }

        if ($type === QuestionType::Number) {
            if (! is_numeric($answer)) {
                return 'not_a_number';
            }

            $numero = (float) $answer;

            if ($this->min !== null && $numero < $this->min) {
                return 'min';
            }

            if ($this->max !== null && $numero > $this->max) {
                return 'max';
            }

            return null;
        }

        if ($type === QuestionType::Date) {
            // Comparacion de cadenas ISO: "2026-08-17" ordena bien como texto,
            // asi que no hace falta convertir a fecha para comparar.
            if ($this->minDate !== null && $answer < $this->minDate) {
                return 'min_date';
            }

            if ($this->maxDate !== null && $answer > $this->maxDate) {
                return 'max_date';
            }
        }

        return null;
    }

    public function toArrayFor(QuestionType $type): array
    {
        $todos = [
            'min' => $this->min,
            'max' => $this->max,
            'min_length' => $this->minLength,
            'max_length' => $this->maxLength,
            'min_date' => $this->minDate,
            'max_date' => $this->maxDate,
            'min_selections' => $this->minSelections,
            'max_selections' => $this->maxSelections,
            'steps' => $this->steps,
        ];

        $aplicables = array_flip(self::keysFor($type));

        return array_filter(
            array_intersect_key($todos, $aplicables),
            fn (mixed $valor): bool => $valor !== null,
        );
    }

    /**
     * Que claves tienen sentido para cada tipo.
     *
     * @return list<string>
     */
    public static function keysFor(QuestionType $type): array
    {
        return match ($type) {
            QuestionType::Number => ['min', 'max'],
            QuestionType::ShortText, QuestionType::LongText => ['min_length', 'max_length'],
            QuestionType::Date => ['min_date', 'max_date'],
            QuestionType::MultipleChoice => ['min_selections', 'max_selections'],
            QuestionType::Rating => ['steps'],

            // smiley, single_choice y yes_no no tienen limites configurables:
            // su forma la dan sus opciones.
            default => [],
        };
    }

    private static function float(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private static function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private static function text(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}
