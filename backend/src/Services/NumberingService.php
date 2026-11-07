<?php

declare(strict_types=1);

namespace SevenCivilGroup\Services;

use SevenCivilGroup\Domain\Repositories\DepartmentRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\SettingsRepositoryInterface;
use SevenCivilGroup\Support\Jalali;

/**
 * Builds a letter's official registration number from an admin-configurable
 * pattern. Supported tokens:
 *   {seq}   sequential number (base 76712 + row id — never collides)
 *   {id}    raw row id
 *   {yy}    2-digit Jalali year        {yyyy} 4-digit Jalali year
 *   {mm}    2-digit Jalali month
 *   {dept}  sender's department code
 */
final class NumberingService
{
    public const KEY = 'letter_number_pattern';
    public const DEFAULT = '5/1/{seq}';

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly DepartmentRepositoryInterface $departments,
    ) {
    }

    public function pattern(): string
    {
        $p = trim((string) $this->settings->get(self::KEY, self::DEFAULT));

        return $p !== '' ? $p : self::DEFAULT;
    }

    public function format(int $id, int $departmentId): string
    {
        // Work in Latin digits so token substitution is unambiguous, then
        // convert the whole number to Persian digits at the very end.
        $j = Jalali::fromGregorian(date('Y-m-d H:i:s'))->toArray();
        $year = (int) $j['year'];
        $deptCode = $this->departments->find($departmentId)?->code ?? '—';

        $number = strtr($this->pattern(), [
            '{seq}' => (string) (76712 + $id),
            '{id}' => (string) $id,
            '{yy}' => str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT),
            '{yyyy}' => (string) $year,
            '{mm}' => str_pad((string) (int) $j['month'], 2, '0', STR_PAD_LEFT),
            '{dept}' => $deptCode,
        ]);

        return Jalali::toPersianDigits($number);
    }
}
