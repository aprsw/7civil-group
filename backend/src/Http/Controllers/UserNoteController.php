<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\NotFoundException;
use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Repositories\UserNoteRepositoryInterface;
use ArtaPharma\Support\Jalali;

/**
 * یادداشت کاربر — private per-user notes/reminders. Each user sees only their
 * own notes. A note carries a Jalali date and an optional "remind N days before"
 * window; the repository computes days-remaining / due flags for the UI alarm.
 */
final class UserNoteController extends Controller
{
    public function __construct(private readonly UserNoteRepositoryInterface $notes)
    {
    }

    /** GET /api/user-notes — the current user's notes. */
    public function index(Request $request): Response
    {
        $me = $this->user($request);

        return Response::ok(['items' => $this->notes->forUser($me->id)]);
    }

    /** POST /api/user-notes */
    public function store(Request $request): Response
    {
        $me = $this->user($request);
        $data = $this->validated($request);
        $id = $this->notes->create($me->id, $data);

        return Response::created($this->notes->find($id, $me->id), 'یادداشت ذخیره شد.');
    }

    /** PUT /api/user-notes/{id} */
    public function update(Request $request): Response
    {
        $me = $this->user($request);
        $id = (int) $request->attribute('id');
        if ($this->notes->find($id, $me->id) === null) {
            throw new NotFoundException('یادداشت یافت نشد.');
        }
        $this->notes->update($id, $me->id, $this->validated($request));

        return Response::ok($this->notes->find($id, $me->id), 'یادداشت به‌روزرسانی شد.');
    }

    /** DELETE /api/user-notes/{id} */
    public function destroy(Request $request): Response
    {
        $me = $this->user($request);
        $id = (int) $request->attribute('id');
        if ($this->notes->find($id, $me->id) === null) {
            throw new NotFoundException('یادداشت یافت نشد.');
        }
        $this->notes->delete($id, $me->id);

        return Response::ok(['deleted' => true], 'یادداشت حذف شد.');
    }

    /**
     * @return array{title:string,description:string,note_date:string,note_gdate:string,remind_days_before:int}
     */
    private function validated(Request $request): array
    {
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            throw new ValidationException(['title' => 'عنوان یادداشت الزامی است.']);
        }

        $jalali = self::normalizeDigits(trim((string) $request->input('note_date', '')));
        if (!preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $jalali, $m)) {
            throw new ValidationException(['note_date' => 'تاریخ را به‌صورت شمسی (مثلاً ۱۴۰۵/۰۶/۲۴) وارد کنید.']);
        }
        [$gy, $gm, $gd] = Jalali::jalaliToGregorian((int) $m[1], (int) $m[2], (int) $m[3]);
        $gdate = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);

        $remind = (int) $request->input('remind_days_before', 0);
        $remind = max(0, min(365, $remind));

        return [
            'title' => $title,
            'description' => trim((string) $request->input('description', '')),
            'note_date' => sprintf('%04d/%02d/%02d', (int) $m[1], (int) $m[2], (int) $m[3]),
            'note_gdate' => $gdate,
            'remind_days_before' => $remind,
        ];
    }

    private static function normalizeDigits(string $s): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return str_replace(array_merge($fa, $ar), array_merge(range(0, 9), range(0, 9)), $s);
    }
}
