<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\SettingsRepositoryInterface;
use ArtaPharma\Services\AccessControl;
use ArtaPharma\Services\NumberingService;
use ArtaPharma\Support\Jalali;

/**
 * Organisation-wide settings — currently the letter numbering pattern.
 * Restricted to the senior admin (settings.manage → CEO).
 */
final class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly NumberingService $numbering,
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

        $pattern = trim((string) $request->input('letter_number_pattern', ''));
        if ($pattern === '') {
            throw new ValidationException(['letter_number_pattern' => 'الگوی شماره‌گذاری الزامی است.']);
        }
        // Must include a unique component so two letters never share a number.
        if (!str_contains($pattern, '{seq}') && !str_contains($pattern, '{id}')) {
            throw new ValidationException(['letter_number_pattern' => 'الگو باید شامل {seq} یا {id} باشد تا شماره‌ها یکتا بمانند.']);
        }

        $this->settings->set(NumberingService::KEY, $pattern);

        return Response::ok($this->present(), 'تنظیمات ذخیره شد.');
    }

    /** @return array<string,mixed> */
    private function present(): array
    {
        return [
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
