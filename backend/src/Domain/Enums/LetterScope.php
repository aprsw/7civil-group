<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Enums;

/**
 * Whether the correspondence stays inside the organisation or is addressed to
 * an outside party (a supplier, a ministry, another company …).
 */
enum LetterScope: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'داخل‌سازمانی',
            self::External => 'خارج‌سازمانی',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Internal => 'teal',
            self::External => 'gold',
        };
    }
}
