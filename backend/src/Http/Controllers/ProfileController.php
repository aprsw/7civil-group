<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\View\UserTransformer;

/**
 * Self-service profile: a user edits their own contact details and photo.
 */
final class ProfileController extends Controller
{
    private const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserTransformer $transformer,
        private readonly \ArtaPharma\Security\Contracts\HasherInterface $hasher,
        private readonly string $photoDir,
        private readonly string $signatureDir,
    ) {
    }

    /** POST /api/profile/password — the user changes their own password. */
    public function changePassword(Request $request): Response
    {
        $me = $this->user($request);
        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');

        if (!$this->hasher->verify($current, $me->passwordHash)) {
            throw new ValidationException(['current_password' => 'رمز عبور فعلی نادرست است.']);
        }
        if (mb_strlen($new) < 6) {
            throw new ValidationException(['new_password' => 'رمز عبور جدید باید حداقل ۶ نویسه باشد.']);
        }
        $this->users->update($me->id, ['password_hash' => $this->hasher->hash($new)]);

        return Response::ok(null, 'رمز عبور با موفقیت تغییر کرد.');
    }

    /** GET /api/profile */
    public function show(Request $request): Response
    {
        return Response::ok($this->transformer->transform($this->user($request), true));
    }

    /** PUT /api/profile — the user updates their own contact fields. */
    public function update(Request $request): Response
    {
        $me = $this->user($request);
        $fields = [];
        foreach (['birth_date', 'address', 'mobile', 'phone', 'description', 'email'] as $k) {
            if ($request->input($k) !== null) {
                $fields[$k] = trim((string) $request->input($k));
            }
        }
        $updated = $this->users->update($me->id, $fields) ?? $me;

        return Response::ok($this->transformer->transform($updated, true), 'پروفایل به‌روزرسانی شد.');
    }

    /** POST /api/profile/photo — upload/replace own avatar (multipart "file"). */
    public function photo(Request $request): Response
    {
        $me = $this->user($request);
        $stored = $this->storeImage($request, $this->photoDir, $me->id);
        $updated = $this->users->update($me->id, ['photo_path' => $stored]) ?? $me;

        return Response::ok($this->transformer->transform($updated, true), 'تصویر پروفایل به‌روزرسانی شد.');
    }

    /** POST /api/profile/signature — upload/replace own signature image. */
    public function signature(Request $request): Response
    {
        $me = $this->user($request);
        $stored = $this->storeImage($request, $this->signatureDir, $me->id);
        $updated = $this->users->update($me->id, ['signature_path' => $stored]) ?? $me;

        return Response::ok($this->transformer->transform($updated, true), 'تصویر امضا به‌روزرسانی شد.');
    }

    /** Validate + persist an uploaded image, returning the stored filename. */
    private function storeImage(Request $request, string $dir, int $userId): string
    {
        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'تصویری دریافت نشد.']);
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::IMAGE_EXT, true)) {
            throw new ValidationException(['file' => 'فقط تصویر مجاز است (' . implode(', ', self::IMAGE_EXT) . ').']);
        }
        if ($file['size'] > 4 * 1024 * 1024) {
            throw new ValidationException(['file' => 'حجم تصویر نباید بیش از ۴ مگابایت باشد.']);
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stored = 'u' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . DIRECTORY_SEPARATOR . $stored;
        $ok = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $target) : rename($file['tmp_name'], $target);
        if (!$ok) {
            throw new ValidationException(['file' => 'ذخیرهٔ تصویر ناموفق بود.']);
        }

        return $stored;
    }
}
