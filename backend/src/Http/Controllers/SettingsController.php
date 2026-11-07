<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\SettingsRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;
use SevenCivilGroup\Services\NumberingService;
use SevenCivilGroup\Services\OrgIdentity;
use SevenCivilGroup\Support\Jalali;

/**
 * Organisation-wide settings — the letter numbering pattern plus the
 * organisation's identity (نام و شعار) shown on the letterhead.
 * Restricted to the senior admin (settings.manage → CEO).
 */
final class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly NumberingService $numbering,
        private readonly OrgIdentity $identity,
        private readonly OrgLogoRepositoryInterface $logos,
        private readonly AccessControl $access,
    ) {
    }

    /** GET /api/settings */
    public function index(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::SettingsManage);

        return Response::ok($this->present());
    }

    /** PUT /api/settings */
    public function update(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::SettingsManage);

        $errors = [];

        // The numbering pattern is only touched when it was sent, so saving the
        // organisation's name alone cannot blank it out.
        if ($request->input('letter_number_pattern') !== null) {
            $pattern = trim((string) $request->input('letter_number_pattern', ''));
            if ($pattern === '') {
                $errors['letter_number_pattern'] = 'الگوی شماره‌گذاری الزامی است.';
            } elseif (!str_contains($pattern, '{seq}') && !str_contains($pattern, '{id}')) {
                // Must include a unique component so two letters never share a number.
                $errors['letter_number_pattern'] = 'الگو باید شامل {seq} یا {id} باشد تا شماره‌ها یکتا بمانند.';
            } else {
                $this->settings->set(NumberingService::KEY, $pattern);
            }
        }

        $identityInput = [];
        $identityKeys = array_merge(
            [OrgIdentity::KEY_NAME, OrgIdentity::KEY_TAGLINE, OrgIdentity::KEY_NAME_LATIN],
            OrgIdentity::LOGO_SLOTS,
        );
        foreach ($identityKeys as $key) {
            if ($request->input($key) !== null) {
                $identityInput[$key] = $request->input($key);
            }
        }
        $errors += $this->identity->save($identityInput);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return Response::ok($this->present(), 'تنظیمات ذخیره شد.');
    }

    /** @return array<string,mixed> */
    private function present(): array
    {
        return [
            'org_name' => $this->identity->name(),
            'org_tagline' => $this->identity->tagline(),
            'org_name_latin' => $this->identity->nameLatin(),
            // Which logo each slot currently uses, plus the library to pick from.
            'org_logo_letterhead' => $this->identity->logoId(OrgIdentity::KEY_LOGO_LETTERHEAD),
            'org_logo_login' => $this->identity->logoId(OrgIdentity::KEY_LOGO_LOGIN),
            'org_logo_sidebar' => $this->identity->logoId(OrgIdentity::KEY_LOGO_SIDEBAR),
            'logos' => array_map(static fn ($l) => $l->toArray(), $this->logos->all()),
            'letter_number_pattern' => $this->numbering->pattern(),
            'preview' => Jalali::toPersianDigits($this->numbering->format(11, 1)),
            'tokens' => [
                ['token' => '{seq}', 'desc' => 'شمارهٔ ترتیبی (یکتا)'],
                ['token' => '{id}', 'desc' => 'شناسهٔ نامه'],
                ['token' => '{yy}', 'desc' => 'سال جلالی (۲ رقمی)'],
                ['token' => '{yyyy}', 'desc' => 'سال جلالی (۴ رقمی)'],
                ['token' => '{mm}', 'desc' => 'ماه جلالی'],
                ['token' => '{dept}', 'desc' => 'کد واحد فرستنده'],
            ],
        ];
    }
}
