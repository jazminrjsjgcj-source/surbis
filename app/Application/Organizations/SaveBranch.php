<?php

declare(strict_types=1);

namespace App\Application\Organizations;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Organization;

/**
 * Crear y editar en una sola clase.
 *
 * Dos clases con el mismo cuerpo y una linea de diferencia serian dos sitios
 * donde recordar la misma regla. Lo que cambia es si la sucursal ya existe, y
 * eso es un parametro, no una jerarquia.
 */
final class SaveBranch
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /** @param array{name: string, code: string} $attributes */
    public function create(Organization $organization, array $attributes): Branch
    {
        $branch = new Branch($attributes);
        $branch->organization()->associate($organization);
        $branch->save();

        $this->audit->record('branch.created', $branch, [
            'code' => $branch->code,
        ]);

        return $branch;
    }

    /** @param array{name: string, code: string} $attributes */
    public function update(Branch $branch, array $attributes): Branch
    {
        $before = ['name' => $branch->name, 'code' => $branch->code];

        $branch->fill($attributes)->save();

        // El contexto guarda el antes y el despues: una auditoria que solo
        // dice "se edito" no sirve para reconstruir nada.
        $this->audit->record('branch.updated', $branch, [
            'code_before' => $before['code'],
            'code_after' => $branch->code,
            'name_before' => $before['name'],
            'name_after' => $branch->name,
        ]);

        return $branch;
    }
}
