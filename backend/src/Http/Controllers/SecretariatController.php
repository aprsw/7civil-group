<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\LetterRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\SecretariatFolderRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;
use SevenCivilGroup\Services\LetterAccess;
use SevenCivilGroup\Services\LetterService;
use SevenCivilGroup\View\LetterTransformer;

/**
 * دبیرخانهٔ رسمی — register incoming letters arriving from outside (scan +
 * clerk-typed registration number) and route them to a staff member, and
 * register outgoing letters dispatched to external parties. Both flow through
 * the normal letter engine so attachments and the recipient's mailbox work as
 * usual, but the registration number is entered by the clerk: the system only
 * guarantees it is unique and shows the last number used.
 *
 * Folders (بخش‌بندی) are defined by the admin only (secretariat.manage); any
 * secretariat user may still file a letter under an existing folder.
 */
final class SecretariatController extends Controller
{
    public function __construct(
        private readonly LetterService $service,
        private readonly LetterRepositoryInterface $letters,
        private readonly LetterTransformer $transformer,
        private readonly LetterAccess $visibility,
        private readonly AccessControl $access,
        private readonly SecretariatFolderRepositoryInterface $folders,
    ) {
    }

    /** GET /api/secretariat?box=in|out — the registered letters + folders. */
    public function index(Request $request): Response
    {
        $me = $this->gate($request);
        $box = $request->query('box', 'in') === 'out' ? 'out' : 'in';
        $visible = $this->visibility->filter($me, $this->letters->all());
        $items = array_values(array_filter($visible, static fn ($l) => $l->secretariat === $box));

        // Attach each letter's folder so the UI can group/filter (تفکیک).
        $folderMap = $this->folders->letterFolderMap();
        $rows = array_map(function ($l) use ($folderMap) {
            $row = $this->transformer->transform($l);
            $row['folder_id'] = $folderMap[$l->id] ?? null;

            return $row;
        }, $items);

        return Response::ok([
            'items' => $rows,
            'box' => $box,
            'folders' => $this->folders->all(),
            'last_number' => $this->letters->lastSecretariatNumber($box),
            'last_number_any' => $this->letters->lastSecretariatNumber(null),
            'can_manage_folders' => $this->canManageFolders($me),
        ]);
    }

    /** GET /api/secretariat/last-number?box=in|out — the last registered number. */
    public function lastNumber(Request $request): Response
    {
        $this->gate($request);
        $box = $request->query('box', 'in') === 'out' ? 'out' : 'in';

        return Response::ok([
            'box' => $box,
            'last_number' => $this->letters->lastSecretariatNumber($box),
            'last_number_any' => $this->letters->lastSecretariatNumber(null),
        ]);
    }

    /** GET /api/secretariat/check-number?number=… — is this number free? */
    public function checkNumber(Request $request): Response
    {
        $this->gate($request);
        $number = $this->normalizeNumber((string) $request->query('number', ''));

        return Response::ok([
            'number' => $number,
            'available' => $number !== '' && !$this->letters->numberExists($this->numberForms($number)),
        ]);
    }

    /** GET /api/secretariat/folders — the shared folders. */
    public function folders(Request $request): Response
    {
        $this->gate($request);

        return Response::ok(['folders' => $this->folders->all()]);
    }

    /** POST /api/secretariat/folders — create a folder (admin only). */
    public function createFolder(Request $request): Response
    {
        $me = $this->gateFolderAdmin($request);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'نام بخش الزامی است.']);
        }
        $id = $this->folders->create($name, $me->id);

        return Response::created(['id' => $id, 'name' => $name], 'بخش ساخته شد.');
    }

    /** PUT /api/secretariat/folders/{id} — rename a folder (admin only). */
    public function renameFolder(Request $request): Response
    {
        $this->gateFolderAdmin($request);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'نام بخش الزامی است.']);
        }
        $this->folders->rename((int) $request->attribute('id'), $name);

        return Response::ok(['id' => (int) $request->attribute('id'), 'name' => $name], 'بخش ویرایش شد.');
    }

    /** DELETE /api/secretariat/folders/{id} — delete a folder (admin only; letters keep, unfiled). */
    public function deleteFolder(Request $request): Response
    {
        $this->gateFolderAdmin($request);
        $this->folders->delete((int) $request->attribute('id'));

        return Response::ok(['deleted' => true], 'بخش حذف شد.');
    }

    /** PUT /api/secretariat/{id}/folder — file a letter under a folder (or none). */
    public function fileLetter(Request $request): Response
    {
        $this->gate($request);
        $letterId = (int) $request->attribute('id');
        $folderId = $request->input('folder_id', null);
        $folderId = ($folderId === null || $folderId === '' || (int) $folderId === 0) ? null : (int) $folderId;
        $this->letters->update($letterId, ['secretariat_folder_id' => $folderId]);

        return Response::ok(['id' => $letterId, 'folder_id' => $folderId], 'نامه در بخش قرار گرفت.');
    }

    /** POST /api/secretariat/incoming — register an external letter + route it. */
    public function incoming(Request $request): Response
    {
        $me = $this->gate($request);
        $external = trim((string) $request->input('external_party', ''));
        $subject = trim((string) $request->input('subject', ''));
        $recipientIds = array_values(array_filter(array_map('intval', (array) $request->input('recipient_ids', []))));
        if ($external === '') {
            throw new ValidationException(['external_party' => 'نام فرستندهٔ خارج از سازمان الزامی است.']);
        }
        if ($recipientIds === []) {
            throw new ValidationException(['recipient_ids' => 'گیرندهٔ داخلی برای ارجاع نامه انتخاب کنید.']);
        }
        $number = $this->requireUniqueNumber($request);

        $letter = $this->service->send([
            'number' => $number,
            'subject' => $subject ?: ('نامهٔ وارده از ' . $external),
            'scope' => 'internal',
            'recipient_ids' => $recipientIds,
            'kind' => (string) $request->input('kind', 'action'),
            'priority' => (string) $request->input('priority', 'normal'),
            'body' => (string) $request->input('body', ''),
            'external_recipient' => $external,
            'secretariat' => 'in',
            'attachments' => (array) $request->input('attachments', []),
        ], $me);

        $this->applyFolder($request, $letter->id);

        return Response::created($this->transformer->transform($letter), 'نامهٔ وارده با شمارهٔ ' . $letter->number . ' ثبت و ارجاع شد.');
    }

    /** POST /api/secretariat/outgoing — register an outgoing external letter. */
    public function outgoing(Request $request): Response
    {
        $me = $this->gate($request);
        $external = trim((string) $request->input('external_party', ''));
        $subject = trim((string) $request->input('subject', ''));
        if ($external === '') {
            throw new ValidationException(['external_party' => 'نام گیرندهٔ خارج از سازمان الزامی است.']);
        }
        $number = $this->requireUniqueNumber($request);

        $letter = $this->service->send([
            'number' => $number,
            'subject' => $subject ?: ('نامهٔ صادره به ' . $external),
            'scope' => 'external',
            'external_recipient' => $external,
            'kind' => (string) $request->input('kind', 'action'),
            'priority' => (string) $request->input('priority', 'normal'),
            'body' => (string) $request->input('body', ''),
            'secretariat' => 'out',
            'attachments' => (array) $request->input('attachments', []),
        ], $me);

        $this->applyFolder($request, $letter->id);

        return Response::created($this->transformer->transform($letter), 'نامهٔ صادره با شمارهٔ ' . $letter->number . ' ثبت شد.');
    }

    private function gate(Request $request): \SevenCivilGroup\Domain\Entities\User
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::SecretariatUse);

        return $me;
    }

    /**
     * Folder (بخش‌بندی) management is reserved for the admin: either the
     * dedicated secretariat.manage permission or the access administrator
     * (roles.manage) — never a plain secretariat clerk.
     */
    private function canManageFolders(\SevenCivilGroup\Domain\Entities\User $me): bool
    {
        return $this->access->can($me, Permission::SecretariatManage)
            || $this->access->can($me, Permission::RolesManage);
    }

    private function gateFolderAdmin(Request $request): \SevenCivilGroup\Domain\Entities\User
    {
        $me = $this->gate($request);
        if (!$this->canManageFolders($me)) {
            throw new \SevenCivilGroup\Core\Exceptions\ForbiddenException('بخش‌بندی دبیرخانه فقط توسط مدیر سامانه قابل تغییر است.');
        }

        return $me;
    }

    /**
     * Read the clerk-typed registration number, normalise its digits and make
     * sure no other letter already carries it.
     */
    private function requireUniqueNumber(Request $request): string
    {
        $number = $this->normalizeNumber((string) $request->input('number', ''));
        if ($number === '') {
            throw new ValidationException(['number' => 'شمارهٔ نامه را وارد کنید.']);
        }
        if (mb_strlen($number) > 60) {
            throw new ValidationException(['number' => 'شمارهٔ نامه حداکثر ۶۰ نویسه است.']);
        }
        if ($this->letters->numberExists($this->numberForms($number))) {
            $last = $this->letters->lastSecretariatNumber(null);
            throw new ValidationException(['number' => 'شمارهٔ «' . $number . '» قبلاً ثبت شده است.' . ($last !== null ? ' آخرین شمارهٔ ثبت‌شده: ' . $last : '')]);
        }

        return $number;
    }

    /** Trim, collapse inner whitespace and store with Persian digits (like auto numbers). */
    private function normalizeNumber(string $raw): string
    {
        $n = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        // Arabic-Indic digits (٠-٩) → Latin, then everything → Persian digits.
        $n = strtr($n, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return \SevenCivilGroup\Support\Jalali::toPersianDigits($n);
    }

    /**
     * Every digit-form of a number that must be treated as the same value
     * when checking uniqueness (Persian and Latin digits).
     *
     * @return list<string>
     */
    private function numberForms(string $number): array
    {
        $latin = strtr($number, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);

        return array_values(array_unique([$number, $latin]));
    }

    /** File a freshly-registered letter under the chosen folder, if any. */
    private function applyFolder(Request $request, int $letterId): void
    {
        $folderId = $request->input('folder_id', null);
        if ($folderId !== null && $folderId !== '' && (int) $folderId > 0) {
            $this->letters->update($letterId, ['secretariat_folder_id' => (int) $folderId]);
        }
    }
}
