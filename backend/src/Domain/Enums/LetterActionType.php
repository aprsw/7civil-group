<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Enums;

/**
 * The kind of action recorded in a letter's history (گردش نامه).
 */
enum LetterActionType: string
{
    case Created = 'created';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Replied = 'replied';
    case Referred = 'referred';
    case Opened = 'opened';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'ثبت و ایجاد نامه',
            self::Sent => 'ارسال نامه',
            self::Approved => 'تأیید و ارجاع',
            self::Rejected => 'عدم تأیید (بازگشت)',
            self::Replied => 'ثبت پاسخ',
            self::Referred => 'ارجاع نامه',
            self::Opened => 'مشاهدهٔ نامه',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Created, self::Sent, self::Replied, self::Referred, self::Opened => 'brand',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
