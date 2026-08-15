<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Application\Surveys\Exceptions\VersionConflict;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueo optimista sobre el borrador.
 *
 * Toda escritura del constructor pasa por aqui. La comprobacion y el
 * incremento ocurren en UNA sentencia condicionada, no en un leer-comparar-
 * escribir: entre la lectura y la escritura cabe otra peticion entera, y ahi
 * es donde el bloqueo optimista deja de proteger sin dar ningun aviso.
 */
final class LockVersion
{
    /**
     * Reclama el borrador para esta escritura y devuelve el numero nuevo.
     *
     * @throws VersionConflict
     */
    public function claim(SurveyVersion $version, int $expected): int
    {
        /*
         * UPDATE ... WHERE lock_version = ? es lo que hace atomica la
         * comprobacion. PostgreSQL garantiza que solo una de dos peticiones
         * simultaneas afecte a la fila; la otra recibe cero filas afectadas y
         * sabe que perdio.
         */
        $afectadas = DB::table('survey_versions')
            ->where('id', $version->id)
            ->where('lock_version', $expected)
            ->update([
                'lock_version' => $expected + 1,
                'updated_at' => now(),
            ]);

        if ($afectadas === 0) {
            $actual = $version->fresh();

            throw new VersionConflict(
                expected: $expected,
                actual: (int) $actual->lock_version,
                current: $actual,
            );
        }

        $version->forceFill(['lock_version' => $expected + 1]);

        return $expected + 1;
    }
}
