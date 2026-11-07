<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Enums;

/**
 * The expected-action classification of a letter (نوع کارتابل). Drives the
 * received/sent sub-folders: همه / اقدام / اطلاع / امضا / پاسخ / پیگیری.
 */
enum LetterKind: string
{
    case Action = 'action';     // جهت اقدام
    case Info = 'info';         // جهت اطلاع
    case Sign = 'sign';         // جهت امضا
    case Reply = 'reply';       // جهت پاسخ
    case FollowUp = 'followup'; // جهت پیگیری

    public function label(): string
    {
        return match ($this) {
            self::Action => 'جهت اقدام',
            self::Info => 'جهت اطلاع',
            self::Sign => 'جهت امضا',
            self::Reply => 'جهت پاسخ',
            self::FollowUp => 'جهت پیگیری',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Action => 'gold',
            self::Info => 'brand',
            self::Sign => 'teal',
            self::Reply => 'brand',
            self::FollowUp => 'gold',
        };
    }
}
