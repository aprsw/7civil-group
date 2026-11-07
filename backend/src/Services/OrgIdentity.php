<?php

declare(strict_types=1);

namespace SevenCivilGroup\Services;

use SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\SettingsRepositoryInterface;

/**
 * The organisation's public identity (نام، شعار و لوگو) as shown on the
 * letterhead, the login screen and the sidebar.
 *
 * The group has several member companies, each with its own logo, so the
 * admin picks per slot (letterhead / login / sidebar) which logo from the
 * library is used. An unset or dangling slot falls back to the bundled asset.
 *
 * The values live in the `settings` table so the admin can change them from
 * the panel without a redeploy; the constants below are only the fallbacks
 * used before anything has been saved.
 */
final class OrgIdentity
{
    public const KEY_NAME = 'org_name';
    public const KEY_TAGLINE = 'org_tagline';
    public const KEY_NAME_LATIN = 'org_name_latin';

    /** Logo slots — each holds the id of a row in `org_logos`. */
    public const KEY_LOGO_LETTERHEAD = 'org_logo_letterhead';
    public const KEY_LOGO_LOGIN = 'org_logo_login';
    public const KEY_LOGO_SIDEBAR = 'org_logo_sidebar';

    /** @var list<string> */
    public const LOGO_SLOTS = [self::KEY_LOGO_LETTERHEAD, self::KEY_LOGO_LOGIN, self::KEY_LOGO_SIDEBAR];

    public const DEFAULT_NAME = 'گروه متحد 7';
    public const DEFAULT_TAGLINE = '';
    public const DEFAULT_NAME_LATIN = '7CivilGroup';

    /** Hard limits so a pasted document can never become the letterhead. */
    private const MAX_NAME = 120;
    private const MAX_TAGLINE = 160;

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly OrgLogoRepositoryInterface $logos,
    ) {
    }

    public function name(): string
    {
        return $this->read(self::KEY_NAME, self::DEFAULT_NAME);
    }

    /** May be an empty string — the letterhead then shows no subtitle. */
    public function tagline(): string
    {
        $value = $this->settings->get(self::KEY_TAGLINE);

        return $value === null ? self::DEFAULT_TAGLINE : trim($value);
    }

    public function nameLatin(): string
    {
        return $this->read(self::KEY_NAME_LATIN, self::DEFAULT_NAME_LATIN);
    }

    /**
     * The id of the logo assigned to a slot, or null when none is assigned or
     * the assigned logo has since been deleted.
     */
    public function logoId(string $slot): ?int
    {
        $raw = $this->settings->get($slot);
        $id = $raw === null ? 0 : (int) trim($raw);

        return $id > 0 && $this->logos->find($id) !== null ? $id : null;
    }

    /** Browser URL of a slot's logo, or null to let the UI use its fallback. */
    public function logoUrl(string $slot): ?string
    {
        $id = $this->logoId($slot);

        return $id === null ? null : $this->logos->find($id)?->url();
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return [
            'name' => $this->name(),
            'tagline' => $this->tagline(),
            'name_latin' => $this->nameLatin(),
            'logo_letterhead_id' => $this->logoId(self::KEY_LOGO_LETTERHEAD),
            'logo_login_id' => $this->logoId(self::KEY_LOGO_LOGIN),
            'logo_sidebar_id' => $this->logoId(self::KEY_LOGO_SIDEBAR),
            'logo_letterhead_url' => $this->logoUrl(self::KEY_LOGO_LETTERHEAD),
            'logo_login_url' => $this->logoUrl(self::KEY_LOGO_LOGIN),
            'logo_sidebar_url' => $this->logoUrl(self::KEY_LOGO_SIDEBAR),
        ];
    }

    /**
     * Persist whichever of the three fields are present in $input. Returns the
     * validation errors (keyed by field) — empty when everything was saved.
     *
     * @param array<string,mixed> $input
     * @return array<string,string>
     */
    public function save(array $input): array
    {
        $errors = [];

        if (array_key_exists(self::KEY_NAME, $input)) {
            $name = trim((string) $input[self::KEY_NAME]);
            if ($name === '') {
                $errors[self::KEY_NAME] = 'نام سازمان الزامی است.';
            } elseif (mb_strlen($name) > self::MAX_NAME) {
                $errors[self::KEY_NAME] = 'نام سازمان نباید بیش از ' . self::MAX_NAME . ' نویسه باشد.';
            } else {
                $this->settings->set(self::KEY_NAME, $name);
            }
        }

        if (array_key_exists(self::KEY_TAGLINE, $input)) {
            $tagline = trim((string) $input[self::KEY_TAGLINE]);
            if (mb_strlen($tagline) > self::MAX_TAGLINE) {
                $errors[self::KEY_TAGLINE] = 'شعار نباید بیش از ' . self::MAX_TAGLINE . ' نویسه باشد.';
            } else {
                $this->settings->set(self::KEY_TAGLINE, $tagline); // '' hides it
            }
        }

        if (array_key_exists(self::KEY_NAME_LATIN, $input)) {
            $latin = trim((string) $input[self::KEY_NAME_LATIN]);
            if (mb_strlen($latin) > self::MAX_NAME) {
                $errors[self::KEY_NAME_LATIN] = 'نام لاتین نباید بیش از ' . self::MAX_NAME . ' نویسه باشد.';
            } else {
                $this->settings->set(self::KEY_NAME_LATIN, $latin);
            }
        }

        // Logo slots: an id selects a logo from the library, 0/'' clears the
        // slot so the UI falls back to the bundled asset.
        foreach (self::LOGO_SLOTS as $slot) {
            if (!array_key_exists($slot, $input)) {
                continue;
            }
            $raw = trim((string) $input[$slot]);
            if ($raw === '' || $raw === '0') {
                $this->settings->set($slot, '');
                continue;
            }
            if (!ctype_digit($raw) || $this->logos->find((int) $raw) === null) {
                $errors[$slot] = 'لوگوی انتخاب‌شده یافت نشد.';
                continue;
            }
            $this->settings->set($slot, $raw);
        }

        return $errors;
    }

    private function read(string $key, string $fallback): string
    {
        $value = trim((string) $this->settings->get($key, $fallback));

        return $value === '' ? $fallback : $value;
    }
}
