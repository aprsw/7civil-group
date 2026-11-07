<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Enums;

/**
 * Letter priority. `tone` maps to the design-system Tag colour used by the UI.
 */
enum LetterPriority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'عادی',
            self::Urgent => 'فوری',
            self::Info => 'اطلاع',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Normal => 'neutral',
            self::Urgent => 'gold',
            self::Info => 'teal',
        };
    }
}
