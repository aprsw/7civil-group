<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Enums;

/**
 * Confidentiality classification of a letter. Drives who may read it:
 *  - Normal       : visible in the shared registry.
 *  - Confidential  : only sender, named recipient(s) and executives.
 *  - Secret        : as confidential, and hidden from broad listings.
 */
enum Confidentiality: string
{
    case Normal = 'normal';
    case Confidential = 'confidential';
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'عادی',
            self::Confidential => 'محرمانه',
            self::Secret => 'سری',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Normal => 'neutral',
            self::Confidential => 'gold',
            self::Secret => 'danger',
        };
    }

    public function isRestricted(): bool
    {
        return $this !== self::Normal;
    }
}
