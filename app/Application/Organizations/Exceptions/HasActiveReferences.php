<?php

declare(strict_types=1);

namespace App\Application\Organizations\Exceptions;

use RuntimeException;

/**
 * Archivar algo que todavia tiene cosas colgando exige resolverlas antes, no
 * una casilla de "entiendo los riesgos". RNF-AO-BRA-001.
 *
 * Una sola excepcion para sucursales y areas: el problema es el mismo y el
 * mensaje se construye igual. Sustituye a BranchHasActiveReferences, que solo
 * servia para uno de los dos.
 *
 * Lleva el recuento porque el mensaje tiene que decir CUANTAS cosas hay que
 * mover y de que tipo. "No se puede archivar" a secas obliga a buscarlo a
 * mano.
 */
final class HasActiveReferences extends RuntimeException
{
    /** @param array<string, int> $references */
    public function __construct(public readonly array $references)
    {
        parent::__construct('El registro tiene referencias activas.');
    }
}
