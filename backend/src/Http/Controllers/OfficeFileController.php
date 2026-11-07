<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\NotFoundException;
use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\OfficeFileRepositoryInterface;
use ArtaPharma\Services\AccessControl;

/**
 * Admin-managed Word/Excel library. Managers (templates.manage) upload and
 * delete the files; any authenticated user lists and downloads them so the
 * compose «فایل‌های پیوستی» picker can offer only these vetted files instead of
 * arbitrary files from the user's own drive.
 */
final class OfficeFileController extends Controller
{
    private const MAX_BYTES = 8 * 1024 * 1024; // 8 MB
    private const ALLOWED = ['xlsx', 'xls', 'csv', 'docx', 'doc', 'rtf'];

    public function __construct(
        private readonly OfficeFileRepositoryInterface $files,
        private readonly AccessControl $access,
        private readonly string $uploadDir,
    ) {
    }

    /** GET /api/office-files — list the library (any authenticated user). */
    public function index(Request $request): Response
    {
        $this->user($request);

        return Response::ok(['items' => $this->files->all()]);
    }

    /** POST /api/office-files — upload a Word/Excel file (managers only). */
    public function store(Request $request): Response
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::TemplatesManage);

        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'فایلی دریافت نشد یا آپلود ناموفق بود.']);
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new ValidationException(['file' => 'حجم فایل نباید بیش از ۸ مگابایت باشد.']);
        }
        $original = basename((string) $file['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED, true)) {
            throw new ValidationException(['file' => 'فقط فایل Word یا Excel مجاز است (' . implode(', ', self::ALLOWED) . ').']);
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $this->uploadDir . DIRECTORY_SEPARATOR . $stored;
        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $target)
            : rename($file['tmp_name'], $target);
        if (!$moved) {
            throw new ValidationException(['file' => 'ذخیرهٔ فایل ناموفق بود.']);
        }

        $id = $this->files->create([
            'name' => $original,
            'path' => $stored,
            'mime' => (string) ($file['type'] ?: 'application/octet-stream'),
            'size' => (int) $file['size'],
            'uploaded_by' => $me->id,
        ]);

        return Response::created($this->files->find($id), 'فایل به کتابخانه افزوده شد.');
    }

    /** DELETE /api/office-files/{id} — remove a library file (managers only). */
    public function destroy(Request $request): Response
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::TemplatesManage);

        $file = $this->files->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('فایل یافت نشد.');
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . basename((string) $file['path']);
        if (is_file($path)) {
            @unlink($path);
        }
        $this->files->delete((int) $file['id']);

        return Response::ok(['deleted' => true], 'فایل حذف شد.');
    }

    /** GET /api/office-files/{id}/download — stream the raw file for the editor. */
    public function download(Request $request): Response
    {
        $this->user($request);
        $file = $this->files->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('فایل یافت نشد.');
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . basename((string) $file['path']);
        if (!is_readable($path)) {
            throw new NotFoundException('فایل یافت نشد.');
        }

        return Response::file($path, (string) $file['name'], (string) ($file['mime'] ?: 'application/octet-stream'));
    }
}
