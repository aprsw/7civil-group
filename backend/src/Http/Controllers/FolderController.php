<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\ForbiddenException;
use SevenCivilGroup\Core\Exceptions\NotFoundException;
use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Repositories\FolderRepositoryInterface;

/**
 * Per-user mailbox folders (بایگانی + custom). Each user files their own copy
 * of a letter; folders never auto-collect — letters move only on request.
 */
final class FolderController extends Controller
{
    public function __construct(private readonly FolderRepositoryInterface $folders)
    {
    }

    /** GET /api/folders */
    public function index(Request $request): Response
    {
        $me = $this->user($request);

        return Response::ok(['items' => array_map(fn ($f) => [
            'id' => (int) $f['id'],
            'name' => $f['name'],
            'is_system' => (bool) $f['is_system'],
            'count' => $this->folders->countIn((int) $f['id']),
        ], $this->folders->forUser($me->id))]);
    }

    /** POST /api/folders */
    public function store(Request $request): Response
    {
        $me = $this->user($request);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'نام فولدر الزامی است.']);
        }
        $id = $this->folders->create($me->id, $name, false);

        return Response::created(['id' => $id, 'name' => $name], 'فولدر ساخته شد.');
    }

    /** PUT /api/folders/{id} */
    public function update(Request $request): Response
    {
        $me = $this->user($request);
        $folder = $this->owned($request, $me->id);
        if ((bool) $folder['is_system']) {
            throw new ForbiddenException('فولدر پیش‌فرض قابل تغییر نام نیست.');
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'نام فولدر الزامی است.']);
        }
        $this->folders->rename((int) $folder['id'], $name);

        return Response::ok(null, 'به‌روزرسانی شد.');
    }

    /** DELETE /api/folders/{id} */
    public function destroy(Request $request): Response
    {
        $me = $this->user($request);
        $folder = $this->owned($request, $me->id);
        if ((bool) $folder['is_system']) {
            throw new ForbiddenException('فولدر پیش‌فرض قابل حذف نیست.');
        }
        $this->folders->delete((int) $folder['id']);

        return Response::ok(null, 'فولدر حذف شد.');
    }

    /** POST /api/folders/{id}/letters — move one or many letters into the folder. */
    public function fileLetters(Request $request): Response
    {
        $me = $this->user($request);
        $folder = $this->owned($request, $me->id);
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('letter_ids', []))));
        if ($ids === []) {
            throw new ValidationException(['letter_ids' => 'حداقل یک نامه انتخاب کنید.']);
        }
        foreach ($ids as $lid) {
            $this->folders->fileLetter((int) $folder['id'], $lid, $me->id);
        }

        return Response::ok(['moved' => count($ids)], count($ids) . ' نامه به «' . $folder['name'] . '» منتقل شد.');
    }

    /** POST /api/folders/unfile — pull letters back out to the main mailbox. */
    public function unfile(Request $request): Response
    {
        $me = $this->user($request);
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('letter_ids', []))));
        foreach ($ids as $lid) {
            $this->folders->unfileLetter($lid, $me->id);
        }

        return Response::ok(['moved' => count($ids)], 'به صندوق بازگردانده شد.');
    }

    /** @return array<string,mixed> */
    private function owned(Request $request, int $userId): array
    {
        $folder = $this->folders->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('فولدر یافت نشد.');
        if ((int) $folder['user_id'] !== $userId) {
            throw new ForbiddenException('این فولدر متعلق به شما نیست.');
        }

        return $folder;
    }
}
