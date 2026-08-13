<?php

declare(strict_types=1);

namespace App\Application\Organizations\Exceptions;

use RuntimeException;

/**
 * RNF-AO-BRA-001: archivar una sucursal con referencias activas exige
 * resolverlas antes, no una casilla de "entiendo los riesgos".
 *
 * Lleva el recuento porque el mensaje tiene que decir CUANTAS cosas hay que
 * mover y de que tipo. "No se puede archivar" a secas obliga a buscarlo a
 * mano.
 */
final class BranchHasActiveReferences extends RuntimeException
{
    /** @param array<string, int> $references */
    public function __construct(public readonly array $references)
    {
        parent::__construct('La sucursal tiene referencias activas.');
    }
}
