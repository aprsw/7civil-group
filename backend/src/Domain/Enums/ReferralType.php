<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Enums;

/**
 * The intent of a referral (ارجاع) — why a letter is placed in someone's
 * cartable. Mirrors the classic Iranian dabirkhaneh routing marks.
 */
enum ReferralType: string
{
    case Action = 'action';       // جهت اقدام — recipient must act
    case Info = 'info';           // جهت اطلاع — for the recipient's information
    case Awareness = 'awareness'; // جهت استحضار — for senior awareness
    case Consult = 'consult';     // جهت مشورت — asked for an opinion
    case FollowUp = 'followup';   // جهت پیگیری — asked to follow up
    case Reply = 'reply';         // پاسخ — a reply routed back to the sender

    public function label(): string
    {
        return match ($this) {
            self::Action => 'جهت اقدام',
            self::Info => 'جهت اطلاع',
            self::Awareness => 'جهت استحضار',
            self::Consult => 'جهت مشورت',
            self::FollowUp => 'جهت پیگیری',
            self::Reply => 'پاسخ',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Action => 'gold',
            self::Info => 'brand',
            self::Awareness => 'teal',
            self::Consult => 'brand',
            self::FollowUp => 'gold',
            self::Reply => 'teal',
        };
    }
}
