<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Enums;

/**
 * Workflow status of a letter. `tone` maps to the design-system Badge colour.
 */
enum LetterStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Reviewing = 'reviewing';
    case Approved = 'approved';
    case Sent = 'sent';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Pending => 'در انتظار',
            self::Reviewing => 'در حال بررسی',
            self::Approved => 'تأیید شده',
            self::Sent => 'ارسال شد',
            self::Rejected => 'عدم تأیید',
            self::Archived => 'بایگانی شده',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Rejected => 'neutral',
            self::Pending => 'warning',
            self::Reviewing => 'brand',
            self::Approved, self::Sent, self::Archived => 'success',
        };
    }
}
