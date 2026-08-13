<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\StaffMember;

/**
 * Una fila de la lista de personas.
 *
 * La pantalla mezcla dos entidades que no comparten columnas:
 *
 *   membership    una cuenta que inicia sesion. Tiene correo, rol y estado.
 *   staff_member  una persona a la que se evalua. Puede no tener nada de eso.
 *
 * Sin esta clase, la plantilla tendria que preguntar en cada celda "y esto
 * que es", y acabaria llena de guiones donde el dato no aplica. Un guion no
 * dice nada: no distingue "no tiene" de "no se sabe" de "no aplica".
 *
 * Aqui cada fila responde lo que la pantalla necesita saber, y cuando algo no
 * aplica se dice con palabras.
 */
final class PersonRow
{
    private function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?Membership $membership,
        public readonly ?StaffMember $staffMember,
        public readonly ?string $branchName,
        public readonly ?string $areaName,
    ) {}

    public static function fromMembership(Membership $membership): self
    {
        return new self(
            key: 'm'.$membership->id,
            name: $membership->user->name,
            email: $membership->user->email,
            membership: $membership,
            staffMember: $membership->staffMember,
            branchName: $membership->branch?->name,
            areaName: $membership->area?->name,
        );
    }

    public static function fromStaffMember(StaffMember $staff): self
    {
        return new self(
            key: 's'.$staff->id,
            name: trim($staff->first_name.' '.$staff->last_name),
            email: null,
            membership: null,
            staffMember: $staff,
            branchName: $staff->branch?->name,
            areaName: $staff->area?->name,
        );
    }

    public function hasAccount(): bool
    {
        return $this->membership !== null;
    }

    public function isEvaluated(): bool
    {
        return $this->staffMember !== null;
    }
}
