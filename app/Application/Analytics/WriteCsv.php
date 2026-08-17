<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use Illuminate\Support\Collection;

/**
 * Escribe un CSV que Excel abre bien en espanol.
 *
 * Suena trivial y no lo es: un CSV "correcto" se ve como una sola columna de
 * texto en un Excel configurado en espanol, y quien lo abra pensara que la
 * exportacion esta rota.
 */
final class WriteCsv
{
    /** @param Collection<int, list<string>> $rows */
    public function render(Collection $rows): string
    {
        $salida = fopen('php://temp', 'r+');

        /*
         * BOM de UTF-8 al principio.
         *
         * Sin el, Excel lee el archivo como Latin-1 y "Satisfaccion" aparece
         * con caracteres rotos. Los acentos y las enes de este sistema hacen
         * que se note en la primera fila.
         */
        fwrite($salida, "\u{FEFF}");

        foreach ($rows as $fila) {
            /*
             * Punto y coma como separador.
             *
             * En la configuracion espanola de Excel, la coma es el separador
             * DECIMAL: con comas, "4,5" se parte en dos columnas y el archivo
             * se ve descuadrado.
             */
            fputcsv($salida, $fila, separator: ';');
        }

        rewind($salida);

        $contenido = (string) stream_get_contents($salida);

        fclose($salida);

        return $contenido;
    }
}
