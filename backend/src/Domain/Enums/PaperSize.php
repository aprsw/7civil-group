<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Enums;

/**
 * Paper size chosen in the pre-send modal (A4 / A5).
 */
enum PaperSize: string
{
    case A4 = 'a4';
    case A5 = 'a5';

    public function dimensions(): string
    {
        return match ($this) {
            self::A4 => '۲۱۰ × ۲۹۷ میلی‌متر',
            self::A5 => '۱۴۸ × ۲۱۰ میلی‌متر',
        };
    }
}
