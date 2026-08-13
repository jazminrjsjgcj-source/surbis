<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Roles dentro de una organizacion.
 *
 * El administrador de plataforma NO esta aqui: no pertenece a ninguna
 * organizacion cliente. Es users.is_platform_admin. RA-001.
 *
 * El acceso a identidades confidenciales tampoco es un rol: es una concesion
 * fechada y revocable en confidential_access_grants. P-005.
 */
enum MembershipRole: string
{
    case Admin = 'admin';
    case Collaborator = 'collaborator';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
