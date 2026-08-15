<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Import;

use App\Domain\Surveys\Enums\QuestionType;
use Illuminate\Support\Str;

/**
 * Los nombres en espanol de los tipos, y como se reconocen.
 *
 * Decision del area usuaria: "se escribiran en espanol, se reconoceran de
 * forma tolerante y se convertiran a identificadores internos estables".
 *
 * Tolerante quiere decir sin acentos, sin distinguir mayusculas y admitiendo
 * las formas que la gente escribe de verdad: "opcion multiple" y "varias
 * opciones" son lo mismo. Quien escribe una encuesta no deberia tener que
 * aprender un vocabulario exacto.
 */
final class QuestionTypeVocabulary
{
    /**
     * Cada tipo con sus formas aceptadas. La primera es la canonica: es la
     * que se muestra en la ayuda de la pantalla.
     *
     * @var array<string, list<string>>
     */
    private const NAMES = [
        'single_choice' => ['una opcion', 'opcion unica', 'seleccion unica', 'unica'],
        'multiple_choice' => ['varias opciones', 'opcion multiple', 'seleccion multiple', 'multiple'],
        'yes_no' => ['si/no', 'si o no', 'sino', 'booleano'],
        'short_text' => ['texto corto', 'texto breve', 'corto'],
        'long_text' => ['texto largo', 'comentario', 'largo', 'abierta'],
        'number' => ['numero', 'numerica', 'cantidad'],
        'date' => ['fecha'],
        'smiley' => ['caritas', 'carita', 'caras'],
        'rating' => ['estrellas', 'escala', 'calificacion', 'valoracion'],
    ];

    public function resolve(string $written): ?QuestionType
    {
        $normalized = $this->normalize($written);

        foreach (self::NAMES as $value => $names) {
            foreach ($names as $name) {
                if ($this->normalize($name) === $normalized) {
                    return QuestionType::from($value);
                }
            }
        }

        return null;
    }

    /**
     * Los nombres canonicos, para poder decir cuales existen cuando alguien
     * escribe uno que no.
     *
     * @return list<string>
     */
    public function canonicalNames(): array
    {
        return array_map(fn (array $names): string => $names[0], array_values(self::NAMES));
    }

    /**
     * Sin acentos, sin mayusculas y sin espacios de mas.
     *
     * Str::ascii y no una tabla propia: transliterar acentos a mano es de las
     * cosas que se hacen mal en los casos raros y nadie revisa.
     */
    private function normalize(string $value): string
    {
        return trim(Str::squish(Str::lower(Str::ascii($value))));
    }
}
