<?php

declare(strict_types=1);

namespace SevenCivilGroup\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Jalali (Solar Hijri / Persian) calendar value object — written from scratch,
 * no external library. Implements the exact Gregorian <-> Jalali conversion
 * (Behdad Esfahbod's well-known algorithm) plus Persian-digit formatting.
 *
 * Usage:
 *   Jalali::now()->format('Y/m/d');            // ۱۴۰۵/۰۲/۰۲
 *   Jalali::fromGregorian('2026-07-04')->format('l j F Y'); // جمعه ۱۳ تیر ۱۴۰۵
 */
final class Jalali
{
    private const MONTHS = [
        1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];

    private const WEEKDAYS = [
        // keyed by ISO-8601 day-of-week (1 = Monday ... 7 = Sunday)
        6 => 'شنبه', 7 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه',
        3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه',
    ];

    private function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
        public readonly int $hour = 0,
        public readonly int $minute = 0,
        private readonly int $isoWeekday = 6,
    ) {
    }

    public static function now(string $timezone = 'Asia/Tehran'): self
    {
        return self::fromDateTime(new DateTimeImmutable('now', new DateTimeZone($timezone)));
    }

    public static function fromGregorian(string $date, string $timezone = 'Asia/Tehran'): self
    {
        return self::fromDateTime(new DateTimeImmutable($date, new DateTimeZone($timezone)));
    }

    public static function fromDateTime(DateTimeImmutable $dt): self
    {
        [$jy, $jm, $jd] = self::gregorianToJalali(
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j'),
        );

        return new self($jy, $jm, $jd, (int) $dt->format('G'), (int) $dt->format('i'), (int) $dt->format('N'));
    }

    /**
     * Format tokens: Y y m d j n H i  and text tokens F (month name), l (weekday).
     * Numeric output uses Persian digits.
     */
    public function format(string $pattern = 'Y/m/d'): string
    {
        $replacements = [
            'Y' => (string) $this->year,
            'y' => substr((string) $this->year, -2),
            'm' => str_pad((string) $this->month, 2, '0', STR_PAD_LEFT),
            'n' => (string) $this->month,
            'd' => str_pad((string) $this->day, 2, '0', STR_PAD_LEFT),
            'j' => (string) $this->day,
            'H' => str_pad((string) $this->hour, 2, '0', STR_PAD_LEFT),
            'i' => str_pad((string) $this->minute, 2, '0', STR_PAD_LEFT),
            'F' => self::MONTHS[$this->month],
            'l' => self::WEEKDAYS[$this->isoWeekday],
        ];

        $out = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            $out .= $replacements[$char] ?? $char;
        }

        return self::toPersianDigits($out);
    }

    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
            'formatted' => $this->format('Y/m/d'),
            'long' => $this->format('l j F Y'),
        ];
    }

    public static function toPersianDigits(string $input): string
    {
        return strtr($input, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    // ── Core conversion (Esfahbod algorithm) ─────────────────────────────

    /** @return array{0:int,1:int,2:int} [jy, jm, jd] */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        // Cumulative days elapsed before each Gregorian month (non-leap baseline).
        $gDaysToMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $gDaysToMonth[$gm - 1];

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /** @return array{0:int,1:int,2:int} [gy, gm, gd] */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd
            + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0);
        $sal_a = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        while ($gm < 13 && $gd > $sal_a[$gm]) {
            $gd -= $sal_a[$gm];
            $gm++;
        }

        return [$gy, $gm, $gd];
    }
}
