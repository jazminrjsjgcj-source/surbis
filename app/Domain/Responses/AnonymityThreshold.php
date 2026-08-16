<?php

declare(strict_types=1);

namespace App\Domain\Responses;

use App\Domain\Organizations\Models\Organization;

/**
 * El umbral de anonimato. RNF-AO-RES-003.
 *
 * EL PROBLEMA QUE RESUELVE, y no es evidente: una encuesta anonima deja de
 * serlo por deduccion. Si una ventanilla tiene tres respuestas y alguien
 * filtra por esa ventanilla y ese dia, sabe quien atendia. No hace falta un
 * nombre para identificar a una persona.
 *
 * Por eso el umbral NO puede ser solo visual. Si el listado ocultara filas
 * pero la exportacion las trajera, el agujero seguiria ahi: tiene que vivir
 * en la consulta.
 *
 * Cinco por defecto, decision del area usuaria. Configurable por
 * organizacion: un ayuntamiento de tres ventanillas y uno de doscientas no
 * corren el mismo riesgo.
 */
final class AnonymityThreshold
{
    public const DEFAULT = 5;

    /** Nunca por debajo de esto, aunque alguien lo configure a 1. */
    private const MINIMUM = 2;

    public function of(Organization $organization): int
    {
        $configurado = $organization->settings['anonymity_threshold'] ?? null;

        if (! is_int($configurado)) {
            return self::DEFAULT;
        }

        /*
         * Un umbral de 1 no protege de nada, y de 0 tampoco.
         *
         * Se admite bajarlo —hay organizaciones pequenas donde 5 dejaria
         * todo oculto— pero no anularlo: eso convertiria una encuesta
         * declarada anonima en identificable, que es exactamente lo que el
         * umbral existe para impedir.
         */
        return max(self::MINIMUM, $configurado);
    }

    /**
     * Si un grupo de respuestas se puede mostrar con detalle.
     *
     * Se usa en el listado y en la exportacion por igual: si solo mirara la
     * pantalla, exportar seria la puerta de atras.
     */
    public function allows(Organization $organization, int $count): bool
    {
        return $count >= $this->of($organization);
    }
}
