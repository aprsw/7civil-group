<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;

/**
 * Handles letter attachment uploads (multipart). Files are stored under
 * storage/uploads with a random name; the metadata returned here is attached
 * to the letter when it is composed. Download happens via LetterController.
 */
final class AttachmentController extends Controller
{
    private const MAX_BYTES = 8 * 1024 * 1024; // 8 MB
    private const ALLOWED = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'zip',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg',
    ];

    public function __construct(private readonly string $uploadDir)
    {
    }

    /** POST /api/attachments (multipart, field "file") */
    public function store(Request $request): Response
    {
        $this->user($request); // must be authenticated

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
            throw new ValidationException(['file' => 'نوع فایل مجاز نیست (' . implode(', ', self::ALLOWED) . ').']);
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $this->uploadDir . DIRECTORY_SEPARATOR . $stored;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $target)
            : rename($file['tmp_name'], $target); // fallback for CLI/test uploads
        if (!$moved) {
            throw new ValidationException(['file' => 'ذخیرهٔ فایل ناموفق بود.']);
        }

        return Response::created([
            'attachment' => $original,
            'attachment_path' => $stored,
            'attachment_mime' => (string) ($file['type'] ?: 'application/octet-stream'),
            'attachment_size' => (int) $file['size'],
        ], 'فایل پیوست بارگذاری شد.');
    }
}
