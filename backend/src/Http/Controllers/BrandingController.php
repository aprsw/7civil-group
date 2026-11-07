<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\NotFoundException;
use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;
use SevenCivilGroup\Services\OrgIdentity;

/**
 * The organisation's brand: its name/شعار and the logo library.
 *
 * `show()` is deliberately public (no authentication) so the login screen can
 * render the right company's logo before anyone signs in; it exposes nothing
 * but those public strings and image URLs. Every mutating action requires
 * settings.manage.
 */
final class BrandingController extends Controller
{
    /** Raster formats only — SVG is excluded because it can carry script. */
    private const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    private const MAX_BYTES = 4 * 1024 * 1024; // 4 MB

    public function __construct(
        private readonly OrgIdentity $identity,
        private readonly OrgLogoRepositoryInterface $logos,
        private readonly AccessControl $access,
        private readonly string $logoDir,
    ) {
    }

    /** GET /api/branding — public. */
    public function show(Request $request): Response
    {
        return Response::ok($this->identity->all());
    }

    /** GET /api/branding/logos — the library, for the settings screen. */
    public function logos(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::SettingsManage);

        return Response::ok(array_map(static fn ($l) => $l->toArray(), $this->logos->all()));
    }

    /** POST /api/branding/logos — upload a new logo (multipart "file" + "title"). */
    public function store(Request $request): Response
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::SettingsManage);

        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'فایلی دریافت نشد یا آپلود ناموفق بود.']);
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new ValidationException(['file' => 'حجم لوگو نباید بیش از ۴ مگابایت باشد.']);
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::IMAGE_EXT, true)) {
            throw new ValidationException(['file' => 'فقط تصویر مجاز است (' . implode(', ', self::IMAGE_EXT) . ').']);
        }
        // Trust the bytes, not the extension: reject anything that is not a
        // real raster image (a renamed .html would otherwise be served back).
        if (@getimagesize((string) $file['tmp_name']) === false) {
            throw new ValidationException(['file' => 'فایل یک تصویر معتبر نیست.']);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        }
        if (mb_strlen($title) > 150) {
            throw new ValidationException(['title' => 'نام لوگو نباید بیش از ۱۵۰ نویسه باشد.']);
        }

        if (!is_dir($this->logoDir) && !mkdir($this->logoDir, 0775, true) && !is_dir($this->logoDir)) {
            throw new ValidationException(['file' => 'پوشهٔ لوگوها قابل ایجاد نیست.']);
        }
        $stored = 'l' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $this->logoDir . DIRECTORY_SEPARATOR . $stored;

        $moved = is_uploaded_file((string) $file['tmp_name'])
            ? move_uploaded_file((string) $file['tmp_name'], $target)
            : rename((string) $file['tmp_name'], $target);
        if (!$moved) {
            throw new ValidationException(['file' => 'ذخیرهٔ لوگو ناموفق بود.']);
        }

        $logo = $this->logos->create($title, $stored, (string) ($file['type'] ?: 'image/' . $ext), (int) $file['size'], $me->id);

        return Response::created($logo->toArray(), 'لوگو افزوده شد.');
    }

    /** PUT /api/branding/logos/{id} — rename. */
    public function update(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::SettingsManage);

        $logo = $this->logos->find((int) $request->attribute('id'));
        if ($logo === null) {
            throw new NotFoundException('لوگو یافت نشد.');
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            throw new ValidationException(['title' => 'نام لوگو الزامی است.']);
        }
        if (mb_strlen($title) > 150) {
            throw new ValidationException(['title' => 'نام لوگو نباید بیش از ۱۵۰ نویسه باشد.']);
        }

        return Response::ok($this->logos->rename($logo->id, $title)?->toArray(), 'نام لوگو ذخیره شد.');
    }

    /** DELETE /api/branding/logos/{id} — remove from the library and from disk. */
    public function destroy(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::SettingsManage);

        $logo = $this->logos->find((int) $request->attribute('id'));
        if ($logo === null) {
            throw new NotFoundException('لوگو یافت نشد.');
        }
        // A slot pointing at this logo resolves to null afterwards (OrgIdentity
        // re-checks the row), so the UI silently falls back — no dangling image.
        $this->logos->delete($logo->id);
        // basename() keeps a crafted stored-path from escaping the logo folder.
        $file = $this->logoDir . DIRECTORY_SEPARATOR . basename($logo->path);
        if (is_file($file)) {
            @unlink($file);
        }

        return Response::ok(null, 'لوگو حذف شد.');
    }
}
