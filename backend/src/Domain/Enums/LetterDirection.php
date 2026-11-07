<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Enums;

/**
 * Where a letter sits in the cartable: incoming, outgoing, draft or archived.
 */
enum LetterDirection: string
{
    case Incoming = 'in';
    case Outgoing = 'out';
    case Draft = 'draft';
    case Archived = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'وارده',
            self::Outgoing => 'صادره',
            self::Draft => 'پیش‌نویس',
            self::Archived => 'بایگانی',
        };
    }
}
