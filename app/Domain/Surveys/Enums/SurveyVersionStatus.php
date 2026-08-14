<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Enums;

enum SurveyVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
