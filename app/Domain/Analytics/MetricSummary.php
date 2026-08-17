<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Un indicador ya agregado, listo para mostrar.
 *
 * Existe como objeto y no como array suelto porque lleva DOS cosas que no se
 * pueden separar: el valor y si se puede enseñar. Devolver el numero y
 * confiar en que quien lo pinte compruebe el umbral es como se filtran los
 * datos sin querer.
 */
final class MetricSummary
{
    private function __construct(
        public readonly bool $available,
        public readonly ?int $responses,
        public readonly ?float $average,
        public readonly ?float $percentage,
        public readonly int $invalidated,
    ) {}

    /**
     * Por debajo del umbral: NI VALORES NI CANTIDADES EXACTAS.
     *
     * Decision del area usuaria, 18 ago 2026. Decir "datos insuficientes: hay
     * 3" ya es informacion: con dos dias de datos se deduce quien atendia esa
     * ventanilla. Ocultar tambien el numero lo cierra del todo.
     *
     * El recuento de invalidadas SI se muestra: no dice nada de quien
     * contesto, y su ausencia haria pensar que no se excluyo nada.
     */
    public static function insufficient(int $invalidated = 0): self
    {
        return new self(false, null, null, null, $invalidated);
    }

    public static function of(int $responses, int $scoreSum, int $maxScoreSum, int $scored, int $invalidated): self
    {
        /*
         * El promedio se calcula sobre las que PUNTUAN, no sobre todas.
         *
         * Una encuesta mixta —caritas y texto libre— tiene respuestas sin
         * puntuacion. Dividir entre el total las contaria como ceros.
         */
        $average = $scored > 0 ? round($scoreSum / $scored, 2) : null;

        /*
         * Y el porcentaje sobre el maximo POSIBLE, que varia.
         *
         * Una escala de tres opciones y otra de cinco no dan el mismo maximo:
         * comparar sus promedios en bruto seria comparar cosas distintas.
         */
        $percentage = $maxScoreSum > 0
            ? round(($scoreSum / $maxScoreSum) * 100, 1)
            : null;

        return new self(true, $responses, $average, $percentage, $invalidated);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'responses' => $this->responses,
            'average' => $this->average,
            'percentage' => $this->percentage,
            'invalidated' => $this->invalidated,
        ];
    }
}
