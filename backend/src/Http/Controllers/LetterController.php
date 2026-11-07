<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\ForbiddenException;
use SevenCivilGroup\Core\Exceptions\NotFoundException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Enums\LetterDirection;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\LetterRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\UserRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;
use SevenCivilGroup\Services\LetterAccess;
use SevenCivilGroup\Services\LetterService;
use SevenCivilGroup\Support\Jalali;
use SevenCivilGroup\View\LetterTransformer;

final class LetterController extends Controller
{
    public function __construct(
        private readonly LetterRepositoryInterface $letters,
        private readonly LetterService $service,
        private readonly LetterTransformer $transformer,
        private readonly AccessControl $access,
        private readonly LetterAccess $visibility,
        private readonly UserRepositoryInterface $users,
        private readonly \SevenCivilGroup\Domain\Repositories\LetterAttachmentRepositoryInterface $attachments,
        private readonly \SevenCivilGroup\Domain\Repositories\FolderRepositoryInterface $folders,
        private readonly string $uploadDir,
    ) {
    }

    /**
     * GET /api/letters?box=mine|in|out|draft — per-user boxes:
     *   mine  = routed to me and still unread (+ my pending workflow steps)
     *   in    = letters I received (read or unread) — «وارده»
     *   out   = letters I sent — «صادره»
     *   draft = my drafts
     * No box → everything I may see (used by search).
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        // Accept the legacy ?direction= param as an alias for ?box=.
        $box = (string) ($request->query('box', '') ?: $request->query('direction', ''));
        $folderParam = $request->query('folder', '');

        $all = $this->visibility->filter($user, $this->letters->all());
        $filed = $this->folders->filedLetterIds($user->id);
        $unfiled = fn ($l) => !in_array($l->id, $filed, true);

        $isDraft = fn ($l) => $l->status === \SevenCivilGroup\Domain\Enums\LetterStatus::Draft;
        // Letters registered through دبیرخانه belong to the secretariat screen,
        // not to the registering clerk's personal «ارسالی» box.
        $isSecretariat = static fn ($l) => $l->secretariat !== null;
        // Main lists exclude letters the user has manually filed into a folder.
        $mine = array_values(array_filter($all, fn ($l) => $unfiled($l) && $this->service->cartableGroup($user, $l) !== null));
        $inbox = array_values(array_filter($all, fn ($l) => $unfiled($l) && $this->service->isRecipientOf($user, $l)));
        $outbox = array_values(array_filter($all, fn ($l) => $unfiled($l) && $l->senderId === $user->id && !$isDraft($l) && !$isSecretariat($l)));
        $drafts = array_values(array_filter($all, fn ($l) => $l->senderId === $user->id && $isDraft($l)));

        // «خوانده‌نشده» sections: received letters this user has not opened, and
        // sent letters none of the addressees has opened yet.
        $inboxUnread = array_values(array_filter($inbox, fn ($l) => !$this->service->isReadByUser($user, $l)));
        $outboxUnread = array_values(array_filter($outbox, fn ($l) => !$this->service->isReadByAnyRecipient($l)));
        $read = (string) $request->query('read', '');

        // Expand each unopened inbox letter into one entry per *pending referral/
        // reply* delivery — so a letter referred to me three times without being
        // opened shows three unread entries; a plain unread letter shows once.
        $inUnreadEntries = [];
        foreach ($inboxUnread as $l) {
            $pending = $this->service->pendingReferralsFor($user, $l);
            if ($pending === []) {
                $inUnreadEntries[] = ['letter' => $l, 'ref' => null];
            } else {
                foreach ($pending as $ref) {
                    $inUnreadEntries[] = ['letter' => $l, 'ref' => $ref];
                }
            }
        }

        $counts = [
            'mine' => count($mine),
            'in' => count($inbox),
            'in_unread' => count($inUnreadEntries),
            'out' => count($outbox),
            'out_unread' => count($outboxUnread),
            'draft' => count($drafts),
        ];

        // The inbox «unread» box returns delivery-level entries (may repeat a
        // letter), so it is decorated specially and returned here.
        if ($box === 'in' && $read === 'unread' && $folderParam === '') {
            $rows = [];
            foreach ($inUnreadEntries as $e) {
                $row = $this->decorate([$e['letter']], $user)[0];
                if ($e['ref'] !== null) {
                    $row['row_key'] = 'r' . $e['ref']['id'];
                    $row['referral_by'] = $e['ref']['by'];
                    $row['referral_label'] = $e['ref']['label'];
                    $row['referral_at'] = \SevenCivilGroup\Support\Jalali::fromGregorian($e['ref']['at'])->format('Y/m/d — H:i');
                } else {
                    $row['row_key'] = 'l' . $e['letter']->id;
                }
                $rows[] = $row;
            }

            return Response::ok([
                'items' => $rows,
                'counts' => $counts,
                'folders' => array_map(fn ($f) => [
                    'id' => (int) $f['id'], 'name' => $f['name'],
                    'is_system' => (bool) $f['is_system'], 'count' => $this->folders->countIn((int) $f['id']),
                ], $this->folders->forUser($user->id)),
            ]);
        }

        if ($folderParam !== '' && ctype_digit((string) $folderParam)) {
            $ids = $this->folders->letterIdsIn((int) $folderParam);
            $items = array_values(array_filter($all, fn ($l) => in_array($l->id, $ids, true)));
        } else {
            $items = match ($box) {
                'mine' => $mine,
                'in' => $inbox,
                'out' => $read === 'unread' ? $outboxUnread : $outbox,
                'draft' => $drafts,
                default => $all,
            };
        }

        $folders = array_map(fn ($f) => [
            'id' => (int) $f['id'],
            'name' => $f['name'],
            'is_system' => (bool) $f['is_system'],
            'count' => $this->folders->countIn((int) $f['id']),
        ], $this->folders->forUser($user->id));

        return Response::ok([
            'items' => $this->decorate($items, $user),
            'counts' => $counts,
            'folders' => $folders,
        ]);
    }

    /**
     * Attach per-user action flags to each transformed letter.
     *
     * @param list<\SevenCivilGroup\Domain\Entities\Letter> $letters
     * @return list<array<string,mixed>>
     */
    private function decorate(array $letters, \SevenCivilGroup\Domain\Entities\User $user): array
    {
        return array_map(function ($l) use ($user) {
            $row = $this->transformer->transform($l);
            $row['is_my_turn'] = $this->service->isMyTurn($user, $l);
            $row['can_approve'] = $this->service->canApprove($user, $l);
            $row['cartable_group'] = $this->service->cartableGroup($user, $l);
            // A letter is deletable only while unseen (sender), or always (admin).
            // The button appears exactly when deletion is actually permitted.
            $row['can_delete'] = $this->service->canDelete($user, $l);
            $row['show_delete'] = $row['can_delete'];
            $row['is_read'] = $this->service->isReadByUser($user, $l);
            $row['is_unread'] = $this->service->isRecipientOf($user, $l) && !$row['is_read'];
            // For the sender: has anyone opened it yet? (drives «ارسالی › خوانده‌نشده»)
            $row['is_unread_by_recipients'] = $l->senderId === $user->id
                && $l->status !== \SevenCivilGroup\Domain\Enums\LetterStatus::Draft
                && !$this->service->isReadByAnyRecipient($l);
            $sndr = $l->senderId !== null ? $this->users->find($l->senderId) : null;
            $row['sender_photo_url'] = $sndr?->photoPath !== null && $sndr?->photoPath !== '' ? '/uploads/photos/' . $sndr->photoPath : null;
            $row['wf_status'] = $this->service->workflowStatus($l);
            $row['approvers'] = $this->service->approverChain($l->id);

            return $row;
        }, $letters);
    }

    /** GET /api/admin/letters — every letter (who sent to whom). Admin only. */
    public function adminAll(Request $request): Response
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::DepartmentsView);
        $all = $this->letters->all();
        usort($all, static fn ($a, $b) => $b->id <=> $a->id);

        return Response::ok(['items' => $this->decorate($all, $me)]);
    }

    /** GET /api/letters/archive */
    public function archive(Request $request): Response
    {
        $letters = $this->visibility->filter($this->user($request), $this->letters->archived());

        return Response::ok($this->transformer->collection($letters));
    }

    /** GET /api/letters/{id} — letter + its conversation thread */
    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $letter = $this->letters->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('نامه یافت نشد.');
        if (!$this->visibility->canView($user, $letter)) {
            throw new ForbiddenException('این نامه محرمانه است و برای شما قابل دسترسی نیست.');
        }

        // Opening a letter registers a read-receipt for the addressee.
        $this->service->markRead($user, $letter);

        $threadLetters = $this->visibility->filter($user, $this->letters->thread($letter->threadId ?? $letter->id));

        $payload = $this->transformer->transform($letter);
        $payload['thread'] = $this->transformer->collection($threadLetters);
        $payload['history'] = $this->history($letter->id);
        $payload['recipients'] = $this->service->recipientsFor($letter->id);
        $payload['attachments'] = $this->service->attachmentsFor($letter->id);
        $payload['notes'] = $this->service->notesFor($letter->id);
        // Reply routes the letter back to its creator — only offered to someone
        // who is not the creator (and the letter has a real internal sender).
        $payload['can_reply'] = $this->access->can($user, Permission::LettersCreate)
            && $letter->senderId !== null
            && $letter->senderId !== $user->id
            && $letter->scope !== \SevenCivilGroup\Domain\Enums\LetterScope::External;
        $payload['can_approve'] = $this->service->canApprove($user, $letter);
        $payload['can_recall'] = $this->service->canDelete($user, $letter);
        $payload['can_delete'] = $this->service->canDelete($user, $letter);
        $payload['show_delete'] = $payload['can_delete'];
        $payload['is_my_turn'] = $this->service->isMyTurn($user, $letter);
        // The signer's signature image (bottom-left of the letterhead).
        $sender = $letter->senderId !== null ? $this->users->find($letter->senderId) : null;
        $payload['sign_signature_url'] = $sender?->signaturePath !== null && $sender?->signaturePath !== ''
            ? '/uploads/signatures/' . $sender->signaturePath
            : null;
        $payload['sender_photo_url'] = $sender?->photoPath !== null && $sender?->photoPath !== ''
            ? '/uploads/photos/' . $sender->photoPath : null;
        // Dynamic signatures — the creator plus every approver (workflow), each
        // with سمت + نام و نام خانوادگی and their signature image.
        $payload['signatures'] = $this->signatures($letter, $sender);
        $payload['approvers'] = $this->service->approverChain($letter->id);
        $payload['wf_status'] = $this->service->workflowStatus($letter);
        // Anyone who can see a letter may refer it onward (place it in others'
        // cartables) — except purely external dispatches.
        $payload['can_refer'] = $letter->scope !== \SevenCivilGroup\Domain\Enums\LetterScope::External;
        $payload['cartable_group'] = $this->service->cartableGroup($user, $letter);

        return Response::ok($payload);
    }

    /**
     * The signatures that appear on the letterhead: the composer first, then
     * each approver (in workflow order). Each carries name, role and image.
     *
     * @return list<array<string,mixed>>
     */
    private function signatures(\SevenCivilGroup\Domain\Entities\Letter $letter, ?\SevenCivilGroup\Domain\Entities\User $sender): array
    {
        $sigUrl = static fn (?\SevenCivilGroup\Domain\Entities\User $u) => $u !== null && $u->signaturePath !== null && $u->signaturePath !== ''
            ? '/uploads/signatures/' . $u->signaturePath : null;

        $out = [[
            'name' => $letter->signName,
            'role' => $letter->signRole,
            'url' => $sigUrl($sender),
            'label' => 'تنظیم‌کننده',
        ]];

        // Workflow letters accumulate an approver's signature at each step.
        if ($letter->workflowId !== null) {
            foreach ($this->service->history($letter->id) as $a) {
                if ($a->action !== \SevenCivilGroup\Domain\Enums\LetterActionType::Approved || $a->userId === null) {
                    continue;
                }
                $u = $this->users->find($a->userId);
                if ($u === null || $u->id === $letter->senderId) {
                    continue;
                }
                $out[] = [
                    'name' => $u->name,
                    'role' => $u->role?->name ?? '',
                    'url' => $sigUrl($u),
                    'label' => 'تأییدکننده',
                ];
            }
        }

        return $out;
    }

    /** @return list<array<string,mixed>> the letter's audit trail, presented */
    private function history(int $letterId): array
    {
        return array_map(function ($a) {
            $j = Jalali::fromGregorian($a->createdAt);

            return [
                'action' => ['value' => $a->action->value, 'label' => $a->action->label(), 'tone' => $a->action->tone()],
                'user_name' => $a->userId !== null ? ($this->users->find($a->userId)?->name ?? 'کاربر حذف‌شده') : 'دبیرخانه / سیستم',
                'note' => $a->note,
                'date' => $j->format('Y/m/d') . ' — ' . $j->format('H:i'),
            ];
        }, $this->service->history($letterId));
    }

    /** POST /api/letters — compose & send (also used for replies via reply_to_id) */
    public function store(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::LettersCreate);

        // A reply may only be composed against a letter the author is allowed to see.
        $replyTo = $request->input('reply_to_id');
        if ($replyTo !== null && $replyTo !== '' && (int) $replyTo > 0) {
            $parent = $this->letters->find((int) $replyTo) ?? throw new NotFoundException('نامهٔ مرجع یافت نشد.');
            if (!$this->visibility->canView($user, $parent)) {
                throw new ForbiddenException('امکان پاسخ به این نامه را ندارید.');
            }
        }

        $letter = $this->service->send($request->all(), $user);

        return Response::created(
            $this->transformer->transform($letter),
            'نامه با موفقیت ارسال شد (' . strtoupper($letter->paperSize->value) . ').',
        );
    }

    /** PUT /api/letters/{id} — save a draft (as_draft=true) or finalize & send it. */
    public function update(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::LettersCreate);
        $id = (int) $request->attribute('id');
        $asDraft = filter_var($request->input('as_draft', false), FILTER_VALIDATE_BOOL);

        if ($asDraft) {
            $letter = $this->service->updateDraft($id, $request->all(), $user);

            return Response::ok($this->transformer->transform($letter), 'پیش‌نویس ذخیره شد.');
        }

        $letter = $this->service->finalizeDraft($id, $request->all(), $user);

        return Response::ok($this->transformer->transform($letter), 'نامه با موفقیت ارسال شد.');
    }

    /** DELETE /api/letters/{id} — recall/discard while still unread & unactioned. */
    public function destroy(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::LettersCreate);
        $this->service->recall((int) $request->attribute('id'), $user);

        return Response::ok(null, 'نامه حذف شد.');
    }

    /** POST /api/letters/{id}/approve */
    public function approve(Request $request): Response
    {
        $letter = $this->guardAction($request);
        $updated = $this->service->approve($letter->id, $this->user($request), trim((string) $request->input('note', '')));

        return Response::ok($this->transformer->transform($updated), 'نامه تأیید و به مرحلهٔ بعد ارجاع شد.');
    }

    /** POST /api/letters/{id}/reject */
    public function reject(Request $request): Response
    {
        $letter = $this->guardAction($request);
        $updated = $this->service->reject($letter->id, $this->user($request), trim((string) $request->input('note', '')));

        return Response::ok($this->transformer->transform($updated), 'نامه جهت اصلاح بازگردانده شد.');
    }

    /** POST /api/letters/{id}/refer — forward the letter to more people/units. */
    public function refer(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::LettersCreate);
        $letter = $this->letters->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('نامه یافت نشد.');
        if (!$this->visibility->canView($user, $letter)) {
            throw new ForbiddenException('این نامه برای شما قابل دسترسی نیست.');
        }

        $type = \SevenCivilGroup\Domain\Enums\ReferralType::tryFrom((string) $request->input('referral_type', ''))
            ?? throw new \SevenCivilGroup\Core\Exceptions\ValidationException(['referral_type' => 'نوع ارجاع را انتخاب کنید (اقدام/اطلاع/استحضار).']);

        $persons = $this->intList($request->input('recipient_ids'));
        $depts = $this->intList($request->input('recipient_department_ids'));
        $note = trim((string) $request->input('note', ''));

        $this->service->refer($letter->id, $user, $persons, $depts, $type, $note);

        return Response::ok($this->service->recipientsFor($letter->id), 'نامه با موفقیت ارجاع شد.');
    }

    /** POST /api/letters/{id}/reply — route the letter back to its creator (+ extras). */
    public function reply(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::LettersCreate);
        $letter = $this->letters->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('نامه یافت نشد.');
        if (!$this->visibility->canView($user, $letter)) {
            throw new ForbiddenException('این نامه برای شما قابل دسترسی نیست.');
        }

        $persons = $this->intList($request->input('recipient_ids'));
        $depts = $this->intList($request->input('recipient_department_ids'));
        $note = trim((string) $request->input('note', ''));

        $this->service->reply($letter->id, $user, $persons, $depts, $note);

        return Response::ok($this->service->recipientsFor($letter->id), 'پاسخ نامه ثبت و برای فرستنده ارسال شد.');
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($x) => (int) $x, $v), static fn ($x) => $x > 0));
    }

    /**
     * Ensure the current user may act on this letter *now* — it must be their
     * workflow step (or CEO override) and the letter must still be active.
     */
    private function guardAction(Request $request): \SevenCivilGroup\Domain\Entities\Letter
    {
        $user = $this->user($request);
        $letter = $this->letters->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('نامه یافت نشد.');
        // Must be allowed to SEE the letter (confidentiality) AND it must be
        // their turn — acting implies reading, so both ACLs apply.
        if (!$this->visibility->canView($user, $letter)) {
            throw new ForbiddenException('این نامه برای شما قابل دسترسی نیست.');
        }
        if (!$this->service->canApprove($user, $letter)) {
            throw new ForbiddenException('این نامه در مرحلهٔ اقدام شما نیست یا پرونده بسته شده است.');
        }

        return $letter;
    }

    /** GET /api/letters/{id}/download — stream the attachment */
    public function download(Request $request): Response
    {
        $user = $this->user($request);
        $letter = $this->letters->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('نامه یافت نشد.');
        if (!$this->visibility->canView($user, $letter)) {
            throw new ForbiddenException('دسترسی به پیوست این نامه مجاز نیست.');
        }
        if ($letter->attachmentPath === null) {
            throw new NotFoundException('این نامه پیوستی ندارد.');
        }

        $path = $this->uploadDir . DIRECTORY_SEPARATOR . basename($letter->attachmentPath);
        if (!is_readable($path)) {
            throw new NotFoundException('فایل پیوست یافت نشد.');
        }

        return Response::file($path, $letter->attachment, $letter->attachmentMime ?? 'application/octet-stream');
    }

    /** GET /api/letters/{id}/attachments/{aid}/download — stream one of many. */
    public function downloadAttachment(Request $request): Response
    {
        $user = $this->user($request);
        $letter = $this->letters->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('نامه یافت نشد.');
        if (!$this->visibility->canView($user, $letter)) {
            throw new ForbiddenException('دسترسی به پیوست این نامه مجاز نیست.');
        }
        $attachment = $this->attachments->find((int) $request->attribute('aid'));
        if ($attachment === null || $attachment->letterId !== $letter->id) {
            throw new NotFoundException('پیوست یافت نشد.');
        }
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . basename($attachment->path);
        if (!is_readable($path)) {
            throw new NotFoundException('فایل پیوست یافت نشد.');
        }

        return Response::file($path, $attachment->name, $attachment->mime ?? 'application/octet-stream');
    }

    /** GET /api/letters/{id}/notes/{nid}/download — stream a note's Office file. */
    public function downloadNote(Request $request): Response
    {
        $user = $this->user($request);
        $letter = $this->letters->find((int) $request->attribute('id'))
            ?? throw new NotFoundException('نامه یافت نشد.');
        if (!$this->visibility->canView($user, $letter)) {
            throw new ForbiddenException('دسترسی به فایل این نامه مجاز نیست.');
        }
        $nid = (int) $request->attribute('nid');
        $note = null;
        foreach ($this->service->notesFor($letter->id) as $n) {
            if ((int) ($n['id'] ?? 0) === $nid) {
                $note = $n;
                break;
            }
        }
        if ($note === null || empty($note['file_path'])) {
            throw new NotFoundException('فایل پیوستی یافت نشد.');
        }
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . basename((string) $note['file_path']);
        if (!is_readable($path)) {
            throw new NotFoundException('فایل پیوستی یافت نشد.');
        }

        return Response::file(
            $path,
            (string) ($note['file_name'] ?: $note['title']),
            (string) ($note['file_mime'] ?: 'application/octet-stream'),
        );
    }
}
