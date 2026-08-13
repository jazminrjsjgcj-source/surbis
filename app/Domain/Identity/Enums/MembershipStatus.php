<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
