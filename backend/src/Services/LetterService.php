<?php

declare(strict_types=1);

namespace SevenCivilGroup\Services;

use SevenCivilGroup\Core\Exceptions\NotFoundException;
use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Domain\Entities\Letter;
use SevenCivilGroup\Domain\Entities\User;
use SevenCivilGroup\Domain\Enums\Confidentiality;
use SevenCivilGroup\Domain\Enums\LetterDirection;
use SevenCivilGroup\Domain\Enums\LetterKind;
use SevenCivilGroup\Domain\Enums\LetterPriority;
use SevenCivilGroup\Domain\Enums\LetterScope;
use SevenCivilGroup\Domain\Enums\LetterActionType;
use SevenCivilGroup\Domain\Enums\LetterStatus;
use SevenCivilGroup\Domain\Enums\PaperSize;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Enums\ReferralType;
use SevenCivilGroup\Domain\Repositories\DepartmentRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterActionRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterAttachmentRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterReadRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterRecipientRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\UserRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\WorkflowRepositoryInterface;
use SevenCivilGroup\Support\Jalali;

/**
 * Letter use-cases: compose & send (with scope, confidentiality, recipient,
 * chosen workflow and reply threading), then approve / reject along the steps
 * of the letter's workflow.
 */
final class LetterService
{
    /** Default chain used when a letter is not bound to a custom workflow. */
    public const STAGES = ['پیش‌نویس', 'تأیید', 'امضا', 'ارسال', 'بایگانی'];

    public function __construct(
        private readonly LetterRepositoryInterface $letters,
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly UserRepositoryInterface $users,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly LetterActionRepositoryInterface $actions,
        private readonly LetterRecipientRepositoryInterface $recipients,
        private readonly RecipientPolicy $policy,
        private readonly LetterAttachmentRepositoryInterface $attachments,
        private readonly LetterReadRepositoryInterface $reads,
        private readonly \SevenCivilGroup\Domain\Repositories\LetterApproverRepositoryInterface $approvers,
        private readonly \SevenCivilGroup\Domain\Repositories\LetterNoteRepositoryInterface $notes,
    ) {
    }

    /** @return list<array<string,mixed>> the editable notes delivered with a letter */
    public function notesFor(int $letterId): array
    {
        return $this->notes->forLetter($letterId);
    }

    /**
     * Compose & send a letter (the editor "ارسال" flow). Sender is always the
     * authenticated author.
     *
     * @param array<string, mixed> $data
     */
    public function send(array $data, User $author): Letter
    {
        $subject = trim((string) ($data['subject'] ?? '')) ?: 'نامهٔ صادرهٔ جدید';

        $paperSize = PaperSize::tryFrom((string) ($data['paper_size'] ?? 'a4'))
            ?? throw new ValidationException(['paper_size' => 'اندازهٔ کاغذ باید A4 یا A5 باشد.']);
        $scope = LetterScope::tryFrom((string) ($data['scope'] ?? 'internal'))
            ?? throw new ValidationException(['scope' => 'دامنهٔ نامه نامعتبر است.']);
        $confidentiality = Confidentiality::tryFrom((string) ($data['confidentiality'] ?? 'normal'))
            ?? throw new ValidationException(['confidentiality' => 'سطح محرمانگی نامعتبر است.']);
        $priority = LetterPriority::tryFrom((string) ($data['priority'] ?? 'normal'))
            ?? throw new ValidationException(['priority' => 'اولویت نامعتبر است.']);
        $kind = LetterKind::tryFrom((string) ($data['kind'] ?? 'action')) ?? LetterKind::Action;
        $secretariat = in_array($data['secretariat'] ?? null, ['in', 'out'], true) ? (string) $data['secretariat'] : null;

        $externalRecipient = trim((string) ($data['external_recipient'] ?? ''));
        $workflowId = $this->intOrNull($data['workflow_id'] ?? null);
        $replyToId = $this->intOrNull($data['reply_to_id'] ?? null);
        $asDraft = filter_var($data['as_draft'] ?? false, FILTER_VALIDATE_BOOL);

        // A reply may always go back to the original sender, even if the org
        // pyramid would not normally allow the replier to message them.
        $replyAllow = [];
        if ($replyToId !== null) {
            $rp = $this->letters->find($replyToId);
            if ($rp !== null && $rp->senderId !== null) {
                $replyAllow[] = $rp->senderId;
            }
        }

        // Resolve one or many recipients (people and/or units). Legacy single
        // fields are folded in so older clients keep working.
        [$personIds, $deptIds] = $this->collectTargets($data, $author, $replyAllow);
        // رونوشت (CC) — internal people who also receive a copy.
        $ccIds = $scope === LetterScope::Internal
            ? $this->validPersonIds($this->idList($data['cc_ids'] ?? null), $author, $replyAllow)
            : [];
        $ccIds = array_values(array_diff($ccIds, $personIds));

        // Workflow letters route through a per-letter approver chain. Two modes:
        //  • Predefined: the selected workflow carries an admin-defined fixed
        //    chain (specific users, in order) — it is enforced regardless of the
        //    payload, so the same process runs no matter who sends the letter.
        //  • Custom: the sender picks the approvers themselves (workflow with no
        //    predefined chain).
        $predefinedChain = [];
        if ($workflowId !== null) {
            $predefinedChain = $this->workflows->find($workflowId)?->approverIds ?? [];
        }
        $chainIds = [];
        if (!$asDraft && $workflowId !== null) {
            $chainIds = $predefinedChain !== []
                ? $predefinedChain
                : array_values(array_filter(array_map('intval', (array) ($data['approver_ids'] ?? []))));
        }
        $hasChain = $chainIds !== [];

        // First person / unit populate the legacy columns (kept for broadcast
        // detection and backward-compatible access checks).
        $recipientId = $scope === LetterScope::External ? null : ($personIds[0] ?? ($hasChain ? $chainIds[0] : null));
        $recipientDeptId = $scope === LetterScope::External ? null : ($deptIds[0] ?? null);

        // A draft may be saved with an incomplete recipient set; a real send
        // requires at least one addressee consistent with the scope — unless it
        // is a workflow letter, whose chain of approvers is the routing.
        if ($hasChain) {
            $names = array_values(array_filter(array_map(fn ($id) => $this->users->find($id)?->name, $chainIds)));
            $toLabel = 'گردش تأیید: ' . implode(' ← ', $names);
        } else {
            $toLabel = $asDraft
                ? ($this->buildRecipientsLabel($scope, $personIds, $deptIds, $externalRecipient) ?: '— پیش‌نویس —')
                : $this->resolveRecipientsLabel($scope, $personIds, $deptIds, $externalRecipient);
        }

        // Thread: replies inherit the parent's thread; new letters open their own.
        $threadId = null;
        if ($replyToId !== null) {
            $parent = $this->letters->find($replyToId)
                ?? throw new NotFoundException('نامهٔ مرجع برای پاسخ یافت نشد.');
            $threadId = $parent->threadId ?? $parent->id;
        }

        // Collect up to 10 attachments (many file types). The first one also
        // populates the legacy single-attachment columns for list views.
        $attachmentList = $this->collectAttachments($data);
        $first = $attachmentList[0] ?? null;

        $stageCount = $this->stepCount($workflowId);

        // Internal letters enter the workflow awaiting the recipient's action
        // (they surface in the recipient's کارتابل). External letters are simply
        // dispatched (terminal — nothing more to route inside the org).
        $isInternal = $scope === LetterScope::Internal;
        if ($asDraft) {
            $direction = LetterDirection::Draft;
            $status = LetterStatus::Draft;
            $stage = 0;
        } else {
            $direction = LetterDirection::Outgoing;
            // Normal internal letters need no approval — they are delivered
            // (تأیید‌شده) straight away. Workflow letters await the first step.
            $status = $this->initialStatus($isInternal, $workflowId);
            $stage = $isInternal ? 0 : min(1, max(0, $stageCount - 1));
        }

        // دبیرخانه hands in the registration number typed by the clerk; every
        // other flow leaves it empty so the repository auto-numbers the row.
        $manualNumber = trim((string) ($data['number'] ?? ''));

        $letter = new Letter(
            id: 0,
            number: $manualNumber, // '' ⇒ repository derives it from the atomic row id
            subject: $subject,
            body: (string) ($data['body'] ?? 'نامهٔ صادرشده از طریق ادیتور سامانه.'),
            body2: (string) ($data['body2'] ?? ''),
            direction: $direction,
            scope: $scope,
            confidentiality: $confidentiality,
            priority: $priority,
            kind: $kind,
            status: $status,
            paperSize: $paperSize,
            stage: $stage,
            workflowId: $workflowId,
            fromLabel: $author->name . ($author->role ? ' — ' . $author->role->name : ''),
            toLabel: $toLabel,
            senderId: $author->id,
            recipientId: $recipientId,
            recipientDepartmentId: $recipientDeptId,
            externalRecipient: $externalRecipient,
            departmentId: $author->departmentId,
            attachment: $first['name'] ?? (string) ($data['attachment'] ?? '—'),
            signName: $author->name,
            signRole: $author->role?->name ?? '',
            createdAt: date('Y-m-d H:i:s'),
            replyToId: $replyToId,
            threadId: $threadId,
            secretariat: $secretariat,
            attachmentPath: $first['path'] ?? $this->stringOrNull($data['attachment_path'] ?? null),
            attachmentMime: $first['mime'] ?? $this->stringOrNull($data['attachment_mime'] ?? null),
            attachmentSize: $first['size'] ?? $this->intOrNull($data['attachment_size'] ?? null),
        );

        $saved = $this->letters->create($letter);

        // Workflow letters carry a per-letter approver chain the sender defined
        // (ordered list of any users). The chain drives routing & status.
        if ($hasChain) {
            $this->approvers->setChain($saved->id, $chainIds);
        }

        // Editable notes/documents delivered with the letter (up to 10).
        $noteList = array_values(array_filter((array) ($data['notes'] ?? []), 'is_array'));
        if ($noteList !== []) {
            $this->notes->setForLetter($saved->id, $noteList);
        }

        // Persist the full recipient set (internal letters only — external ones
        // leave the org and have no in-system addressees).
        if ($isInternal) {
            foreach ($personIds as $uid) {
                $this->recipients->add($saved->id, ['user_id' => $uid, 'kind' => 'recipient']);
            }
            foreach ($deptIds as $did) {
                $this->recipients->add($saved->id, ['department_id' => $did, 'kind' => 'recipient']);
            }
            foreach ($ccIds as $uid) {
                $this->recipients->add($saved->id, ['user_id' => $uid, 'kind' => 'cc']);
            }
        }

        // Persist every attachment row.
        foreach ($attachmentList as $a) {
            $this->attachments->add($saved->id, $a);
        }

        // Record the opening entry in the letter's history.
        $type = $replyToId !== null ? LetterActionType::Replied : LetterActionType::Created;
        $this->actions->record($saved->id, $author->id, $type, null, $saved->stage);

        return $saved;
    }

    /**
     * Normalize the attachments payload (multi + legacy single) into rows.
     *
     * @param array<string,mixed> $data
     * @return list<array{name:string,path:string,mime:?string,size:?int}>
     */
    private function collectAttachments(array $data): array
    {
        $out = [];
        $list = $data['attachments'] ?? null;
        if (is_array($list)) {
            foreach ($list as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $path = trim((string) ($a['attachment_path'] ?? $a['path'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $out[] = [
                    'name' => (string) ($a['attachment'] ?? $a['name'] ?? 'پیوست'),
                    'path' => $path,
                    'mime' => $this->stringOrNull($a['attachment_mime'] ?? $a['mime'] ?? null),
                    'size' => $this->intOrNull($a['attachment_size'] ?? $a['size'] ?? null),
                ];
                if (count($out) >= 10) {
                    break;
                }
            }
        }

        // Backward-compatible single-attachment payload.
        $single = trim((string) ($data['attachment_path'] ?? ''));
        if ($out === [] && $single !== '') {
            $out[] = [
                'name' => (string) ($data['attachment'] ?? 'پیوست'),
                'path' => $single,
                'mime' => $this->stringOrNull($data['attachment_mime'] ?? null),
                'size' => $this->intOrNull($data['attachment_size'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Presented attachments for a letter's detail view.
     *
     * @return list<array<string,mixed>>
     */
    public function attachmentsFor(int $letterId): array
    {
        return array_map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'mime' => $a->mime,
            'size_label' => $a->size !== null ? $this->humanSize($a->size) : null,
            // Opaque storage token — lets a draft resend its attachments; the
            // download endpoint remains auth-gated.
            'attachment' => $a->name,
            'attachment_path' => $a->path,
            'attachment_mime' => $a->mime,
            'attachment_size' => $a->size,
        ], $this->attachments->forLetter($letterId));
    }

    private function humanSize(int $bytes): string
    {
        $units = ['بایت', 'کیلوبایت', 'مگابایت'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return Jalali::toPersianDigits(number_format($n, $i === 0 ? 0 : 1)) . ' ' . $units[$i];
    }

    /**
     * Refer (ارجاع) an existing letter to more people/units with a routing
     * intent. The referred addressees see it in their کارتابل under that group.
     *
     * @param list<int> $personIds
     * @param list<int> $deptIds
     */
    public function refer(int $letterId, User $actor, array $personIds, array $deptIds, ReferralType $type, string $note = ''): Letter
    {
        $letter = $this->letters->find($letterId) ?? throw new NotFoundException('نامه یافت نشد.');
        $names = $this->deliver($letterId, $actor, $personIds, $deptIds, $type, $note);
        if ($names === []) {
            throw new ValidationException(['targets' => 'حداقل یک گیرنده برای ارجاع انتخاب کنید.']);
        }

        $summary = $type->label() . ' به ' . implode('، ', $names) . ($note !== '' ? ' — ' . $note : '');
        $this->actions->record($letterId, $actor->id, LetterActionType::Referred, $letter->stage, $letter->stage, $summary);

        return $letter;
    }

    /**
     * پاسخ — route the letter itself (with its notes/attachments) back to its
     * creator, optionally to extra people/units too. Behaves exactly like a
     * referral (the letter stays in place, a new dated delivery is added) but the
     * original sender is always included. No «پیرو» letter is created.
     *
     * @param list<int> $extraPersonIds
     * @param list<int> $extraDeptIds
     */
    public function reply(int $letterId, User $actor, array $extraPersonIds = [], array $extraDeptIds = [], string $note = ''): Letter
    {
        $letter = $this->letters->find($letterId) ?? throw new NotFoundException('نامه یافت نشد.');
        if ($letter->senderId === null) {
            throw new ValidationException(['reply' => 'این نامه فرستنده‌ای برای پاسخ ندارد.']);
        }

        // The creator is always a target; extras are added if the replier may
        // address them. The replier themselves is skipped if in the extras.
        $personIds = array_values(array_unique(array_merge([$letter->senderId], $extraPersonIds)));
        $names = $this->deliver($letterId, $actor, $personIds, $extraDeptIds, ReferralType::Reply, $note);
        if ($names === []) {
            throw new ValidationException(['reply' => 'گیرندهٔ پاسخ مشخص نشد.']);
        }

        $summary = 'پاسخ به ' . implode('، ', $names) . ($note !== '' ? ' — ' . $note : '');
        $this->actions->record($letterId, $actor->id, LetterActionType::Replied, $letter->stage, $letter->stage, $summary);

        return $letter;
    }

    /**
     * Shared delivery for refer()/reply(): add a dated referral row per target
     * and reset each target's read state so the letter surfaces as «خوانده‌نشده»
     * again — even if they had opened it before.
     *
     * @param list<int> $personIds
     * @param list<int> $deptIds
     * @return list<string> target display names
     */
    private function deliver(int $letterId, User $actor, array $personIds, array $deptIds, ReferralType $type, string $note): array
    {
        $names = [];
        // For a reply the creator must always be reachable, so skip the pyramid
        // check for reply deliveries (the sender you are replying to is allowed).
        $validPersons = $type === ReferralType::Reply ? array_values(array_unique(array_map('intval', $personIds))) : $this->validPersonIds($personIds, $actor);
        foreach ($validPersons as $uid) {
            if ($uid === $actor->id) {
                continue; // don't route back to the person doing the referral/reply
            }
            $user = $this->users->find($uid);
            if ($user === null) {
                continue;
            }
            $this->recipients->add($letterId, [
                'user_id' => $uid, 'kind' => 'referral',
                'referral_type' => $type->value, 'referred_by' => $actor->id, 'note' => $note,
            ]);
            $this->reads->clearRead($letterId, $uid); // becomes unread again
            $names[] = $user->name;
        }
        foreach ($this->validDeptIds($deptIds, $actor) as $did) {
            $dept = $this->departments->find($did);
            $this->recipients->add($letterId, [
                'department_id' => $did, 'kind' => 'referral',
                'referral_type' => $type->value, 'referred_by' => $actor->id, 'note' => $note,
            ]);
            // Every member of the unit sees it as unread again.
            foreach ($this->users->all() as $u) {
                if ($u->departmentId === $did && $u->active) {
                    $this->reads->clearRead($letterId, $u->id);
                }
            }
            $names[] = $dept?->name ?? ('واحد #' . $did);
        }

        return $names;
    }

    /** Does the user have any referral/reply delivery on this letter? */
    private function hasReferralTo(User $user, Letter $letter): bool
    {
        foreach ($this->recipients->forLetter($letter->id) as $r) {
            if (!$r->isReferral()) {
                continue;
            }
            if ($r->userId === $user->id
                || ($r->departmentId !== null && $r->departmentId === $user->departmentId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pending (unopened) referral/reply deliveries addressed to $user for a
     * letter — one row per delivery, newest last. Drives the per-referral unread
     * entries and count in the cartable.
     *
     * @return list<array{id:int,type:?string,label:?string,by:?string,at:string}>
     */
    public function pendingReferralsFor(User $user, Letter $letter): array
    {
        $out = [];
        foreach ($this->recipients->forLetter($letter->id) as $r) {
            if (!$r->isReferral() || $r->readAt !== null) {
                continue;
            }
            $targetsUser = $r->userId === $user->id
                || ($r->departmentId !== null && $r->departmentId === $user->departmentId);
            if (!$targetsUser) {
                continue;
            }
            $by = $r->referredBy !== null ? $this->users->find($r->referredBy)?->name : null;
            $out[] = [
                'id' => $r->id,
                'type' => $r->referralType?->value,
                'label' => $r->referralType?->label(),
                'by' => $by,
                'at' => $r->createdAt,
            ];
        }

        return $out;
    }

    /** Save changes to an existing draft (keeps it a draft). */
    public function updateDraft(int $id, array $data, User $author): Letter
    {
        return $this->writeDraft($this->guardDraft($id, $author), $data, $author, false);
    }

    /** Finalize a draft into a real, routed letter (ارسال پیش‌نویس). */
    public function finalizeDraft(int $id, array $data, User $author): Letter
    {
        return $this->writeDraft($this->guardDraft($id, $author), $data, $author, true);
    }

    private function guardDraft(int $id, User $author): Letter
    {
        $letter = $this->letters->find($id) ?? throw new NotFoundException('پیش‌نویس یافت نشد.');
        if ($letter->senderId !== $author->id || $letter->status !== LetterStatus::Draft) {
            throw new ValidationException(['id' => 'این نامه پیش‌نویسِ شما نیست.']);
        }

        return $letter;
    }

    /** @param array<string,mixed> $data */
    private function writeDraft(Letter $letter, array $data, User $author, bool $finalize): Letter
    {
        $scope = LetterScope::tryFrom((string) ($data['scope'] ?? $letter->scope->value)) ?? $letter->scope;
        $confidentiality = Confidentiality::tryFrom((string) ($data['confidentiality'] ?? 'normal')) ?? Confidentiality::Normal;
        $priority = LetterPriority::tryFrom((string) ($data['priority'] ?? 'normal')) ?? LetterPriority::Normal;
        $paperSize = PaperSize::tryFrom((string) ($data['paper_size'] ?? 'a4')) ?? PaperSize::A4;
        $external = trim((string) ($data['external_recipient'] ?? ''));
        $workflowId = $this->intOrNull($data['workflow_id'] ?? null);

        [$personIds, $deptIds] = $this->collectTargets($data, $author);
        $isInternal = $scope === LetterScope::Internal;

        $toLabel = $finalize
            ? $this->resolveRecipientsLabel($scope, $personIds, $deptIds, $external)
            : ($this->buildRecipientsLabel($scope, $personIds, $deptIds, $external) ?: '— پیش‌نویس —');

        if ($finalize) {
            $direction = LetterDirection::Outgoing;
            $status = $this->initialStatus($isInternal, $workflowId);
            $stage = $isInternal ? 0 : min(1, max(0, $this->stepCount($workflowId) - 1));
        } else {
            $direction = LetterDirection::Draft;
            $status = LetterStatus::Draft;
            $stage = 0;
        }

        $fields = [
            'subject' => trim((string) ($data['subject'] ?? $letter->subject)) ?: 'نامهٔ بدون موضوع',
            'body' => (string) ($data['body'] ?? $letter->body),
            'body2' => (string) ($data['body2'] ?? $letter->body2),
            'scope' => $scope->value,
            'confidentiality' => $confidentiality->value,
            'priority' => $priority->value,
            'kind' => (LetterKind::tryFrom((string) ($data['kind'] ?? $letter->kind->value)) ?? $letter->kind)->value,
            'paper_size' => $paperSize->value,
            'workflow_id' => $workflowId,
            'to_label' => $toLabel,
            'external_recipient' => $external,
            'recipient_id' => $scope === LetterScope::External ? null : ($personIds[0] ?? null),
            'recipient_department_id' => $scope === LetterScope::External ? null : ($deptIds[0] ?? null),
            'direction' => $direction->value,
            'status' => $status->value,
            'stage' => $stage,
        ];
        // Rebuild attachments from the latest payload; first one fills the
        // legacy columns.
        $attachmentList = $this->collectAttachments($data);
        $first = $attachmentList[0] ?? null;
        $fields['attachment'] = $first['name'] ?? '—';
        $fields['attachment_path'] = $first['path'] ?? null;
        $fields['attachment_mime'] = $first['mime'] ?? null;
        $fields['attachment_size'] = $first['size'] ?? null;

        $this->letters->update($letter->id, $fields);

        // Rebuild the recipient set from the latest selection.
        $this->recipients->deleteForLetter($letter->id);
        if ($isInternal) {
            foreach ($personIds as $uid) {
                $this->recipients->add($letter->id, ['user_id' => $uid, 'kind' => 'recipient']);
            }
            foreach ($deptIds as $did) {
                $this->recipients->add($letter->id, ['department_id' => $did, 'kind' => 'recipient']);
            }
        }

        $this->attachments->deleteForLetter($letter->id);
        foreach ($attachmentList as $a) {
            $this->attachments->add($letter->id, $a);
        }

        // Rebuild the editable "فایل‌های پیوستی" notes from the latest payload.
        $this->notes->setForLetter(
            $letter->id,
            array_values(array_filter((array) ($data['notes'] ?? []), 'is_array')),
        );

        if ($finalize) {
            $this->actions->record($letter->id, $author->id, LetterActionType::Sent, 0, $stage);
        }

        return $this->letters->find($letter->id) ?? $letter;
    }

    /** The status a freshly-sent letter should carry. */
    private function initialStatus(bool $isInternal, ?int $workflowId): LetterStatus
    {
        if (!$isInternal) {
            return LetterStatus::Sent; // external dispatch
        }

        // Normal internal letter → «ارسال شد» (delivered, no pending action).
        // Workflow internal letter → awaiting the first approver.
        return $workflowId === null ? LetterStatus::Sent : LetterStatus::Pending;
    }

    /**
     * Who may delete a letter:
     *   • The senior admin (executive oversight) — always, even if read.
     *   • The sender — only while NO recipient has opened it yet.
     */
    public function canDelete(User $user, Letter $letter): bool
    {
        if ($user->role?->has(Permission::DepartmentsView) ?? false) {
            return true; // مدیر ارشد — بدونِ قید
        }

        return $letter->senderId === $user->id
            && $letter->status !== LetterStatus::Archived
            && !$this->reads->anyRead($letter->id);
    }

    /** Back-compat alias. */
    public function canRecall(User $user, Letter $letter): bool
    {
        return $this->canDelete($user, $letter);
    }

    public function recall(int $id, User $user): void
    {
        $letter = $this->letters->find($id) ?? throw new NotFoundException('نامه یافت نشد.');
        if (!$this->canDelete($user, $letter)) {
            if ($this->reads->anyRead($letter->id)) {
                throw new \SevenCivilGroup\Core\Exceptions\ForbiddenException('حداقل یکی از گیرندگان نامه را مشاهده کرده و امکان حذف وجود ندارد.');
            }
            throw new \SevenCivilGroup\Core\Exceptions\ForbiddenException('اجازهٔ حذف این نامه را ندارید.');
        }
        $this->letters->delete($id);
    }

    /** Mark a letter read by the current addressee (idempotent; logs once). */
    public function markRead(User $user, Letter $letter): void
    {
        // The sender viewing their own letter is not a receipt — unless a reply
        // was routed back to them (then they are a genuine recipient).
        if ($letter->senderId === $user->id && !$this->hasReferralTo($user, $letter)) {
            return;
        }
        // Opening clears every pending referral/reply delivery for this user, so
        // all its «خوانده‌نشده» entries collapse to read at once.
        $this->recipients->markRead($letter->id, $user->id);
        if ($this->reads->markRead($letter->id, $user->id)) {
            $this->actions->record($letter->id, $user->id, LetterActionType::Opened, $letter->stage, $letter->stage);
        }
    }

    public function approve(int $id, User $actor, string $note = ''): Letter
    {
        $letter = $this->letters->find($id) ?? throw new NotFoundException('نامه یافت نشد.');

        // Per-letter approver chain (new model): approve the current step and
        // auto-advance; the last approval marks the letter تأیید‌شده.
        if ($this->approvers->hasChain($id)) {
            $current = $this->approvers->currentPending($id);
            if ($current === null) {
                return $letter; // already finished
            }
            $this->approvers->markApproved((int) $current['id'], $note);
            $this->actions->record($id, $actor->id, LetterActionType::Approved, $letter->stage, $letter->stage + 1, $note);
            $next = $this->approvers->currentPending($id);
            $status = $next === null ? LetterStatus::Approved : LetterStatus::Pending;
            $stage = $next === null ? (int) ($current['position'] ?? 0) + 1 : (int) $next['position'];

            return $this->letters->updateStatus($id, $status->value, $stage) ?? $letter;
        }

        $last = $this->stepCount($letter->workflowId) - 1;
        $next = min($letter->stage + 1, $last);

        // Archive when: acting at the final step, OR advancing into a final step
        // that has no assigned actor (a system "بایگانی" step is automatic — a
        // real final approver, by contrast, must still sign before archival).
        $lastIsSystem = $this->roleAt($letter, $last) === null;
        $reachedEnd = $letter->stage >= $last || ($next >= $last && $lastIsSystem);

        if ($reachedEnd) {
            $stage = $last;
            $status = LetterStatus::Archived;
        } else {
            $stage = $next;
            $status = LetterStatus::Approved;
        }

        $updated = $this->letters->updateStatus($id, $status->value, $stage) ?? $letter;
        $this->actions->record($id, $actor->id, LetterActionType::Approved, $letter->stage, $stage, $note);

        return $updated;
    }

    public function reject(int $id, User $actor, string $note = ''): Letter
    {
        $letter = $this->letters->find($id) ?? throw new NotFoundException('نامه یافت نشد.');

        // Per-letter chain: a rejection at any step stops the letter — it does
        // NOT advance to the next approver.
        if ($this->approvers->hasChain($id)) {
            $current = $this->approvers->currentPending($id);
            if ($current !== null) {
                $this->approvers->markRejected((int) $current['id'], $note);
            }
            $this->actions->record($id, $actor->id, LetterActionType::Rejected, $letter->stage, $letter->stage, $note);

            return $this->letters->updateStatus($id, LetterStatus::Rejected->value, $letter->stage) ?? $letter;
        }

        $stage = max($letter->stage - 1, 0);
        $updated = $this->letters->updateStatus($id, LetterStatus::Rejected->value, $stage) ?? $letter;
        $this->actions->record($id, $actor->id, LetterActionType::Rejected, $letter->stage, $stage, $note);

        return $updated;
    }

    /** @return list<\SevenCivilGroup\Domain\Entities\LetterAction> */
    public function history(int $letterId): array
    {
        return $this->actions->forLetter($letterId);
    }

    /**
     * The presented approver chain (name/role/status per step).
     *
     * @return list<array<string,mixed>>
     */
    public function approverChain(int $letterId): array
    {
        $labels = ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'];

        return array_map(function ($r) use ($labels) {
            $u = $this->users->find((int) $r['user_id']);

            return [
                'user_id' => (int) $r['user_id'],
                'name' => $u?->name ?? 'کاربر',
                'role' => $u?->role?->name,
                'position' => (int) $r['position'],
                'status' => $r['status'],
                'status_label' => $labels[$r['status']] ?? $r['status'],
                'note' => $r['note'] ?? null,
            ];
        }, $this->approvers->forLetter($letterId));
    }

    /**
     * Overall workflow status for a chained letter: در انتظار تأیید [name] /
     * تأیید شده / رد شده توسط [name]. Falls back to the plain status otherwise.
     *
     * @return array{label:string,tone:string,current:?string}
     */
    public function workflowStatus(Letter $letter): array
    {
        if ($letter->workflowId === null || !$this->approvers->hasChain($letter->id)) {
            return ['label' => $letter->status->label(), 'tone' => $letter->status->tone(), 'current' => null];
        }
        foreach ($this->approvers->forLetter($letter->id) as $r) {
            if ($r['status'] === 'rejected') {
                $name = $this->users->find((int) $r['user_id'])?->name ?? 'کاربر';

                return ['label' => 'رد شده توسط ' . $name, 'tone' => 'neutral', 'current' => $name];
            }
        }
        $current = $this->approvers->currentPending($letter->id);
        if ($current === null) {
            return ['label' => 'تأیید شده', 'tone' => 'success', 'current' => null];
        }
        $name = $this->users->find((int) $current['user_id'])?->name ?? 'کاربر';

        return ['label' => 'در انتظار تأیید ' . $name, 'tone' => 'warning', 'current' => $name];
    }

    /**
     * Presented recipients + referrals for a letter's detail view.
     *
     * @return list<array<string,mixed>>
     */
    public function recipientsFor(int $letterId): array
    {
        $readers = $this->reads->readersOf($letterId);

        return array_map(function ($r) use ($readers) {
            $target = $r->userId !== null
                ? ($this->users->find($r->userId)?->name ?? 'کاربر حذف‌شده')
                : ($r->departmentId !== null ? ($this->departments->find($r->departmentId)?->name ?? 'واحد') : '—');
            $read = $r->userId !== null && in_array($r->userId, $readers, true);

            return [
                'target' => $target,
                'user_id' => $r->userId,
                'department_id' => $r->departmentId,
                'is_department' => $r->departmentId !== null,
                'kind' => $r->kind,
                'referral_type' => $r->referralType?->value,
                'referral_label' => $r->referralType?->label(),
                'referral_tone' => $r->referralType?->tone(),
                'referred_by' => $r->referredBy !== null ? ($this->users->find($r->referredBy)?->name ?? null) : null,
                'note' => $r->note,
                'read' => $read,
                'read_at' => null,
            ];
        }, $this->recipients->forLetter($letterId));
    }

    /** Statuses in which a letter is still moving through its workflow. */
    private const ACTIVE = [LetterStatus::Pending, LetterStatus::Reviewing, LetterStatus::Approved, LetterStatus::Rejected];

    /** The role responsible for the letter's *current* workflow step (null → registry/addressee). */
    public function currentStepRoleId(Letter $letter): ?int
    {
        return $this->roleAt($letter, $letter->stage);
    }

    /** The role assigned to an arbitrary step index (null → system/registry step). */
    private function roleAt(Letter $letter, int $index): ?int
    {
        if ($letter->workflowId === null) {
            return null;
        }
        $wf = $this->workflows->find($letter->workflowId);
        if ($wf === null || $wf->steps === []) {
            return null;
        }
        $idx = max(0, min($index, count($wf->steps) - 1));

        return $wf->steps[$idx]->roleId;
    }

    /** Completion percentage for the letter's own workflow (0–100). */
    public function progressPercent(Letter $letter): int
    {
        $steps = max(1, $this->stepCount($letter->workflowId) - 1);

        return (int) round(min($letter->stage, $steps) / $steps * 100);
    }

    /**
     * Is it *this* user's turn to act? — the heart of the کارتابل.
     * True when the letter is still active AND the user owns the current step
     * (by workflow role), or — when the step has no assigned role — the letter
     * is addressed to the user or their unit.
     */
    public function isMyTurn(User $user, Letter $letter): bool
    {
        if (!in_array($letter->status, self::ACTIVE, true)) {
            return false;
        }
        // Normal (non-workflow) letters carry no approval turn — they are simply
        // delivered to the recipient's cartable to read/reply/refer.
        if ($letter->workflowId === null) {
            return false;
        }
        // Per-letter approver chain: my turn iff I am the current pending approver
        // and the letter is still in progress (a rejection stops the chain).
        if ($this->approvers->hasChain($letter->id)) {
            if ($letter->status !== LetterStatus::Pending) {
                return false;
            }
            $current = $this->approvers->currentPending($letter->id);

            return $current !== null && (int) $current['user_id'] === $user->id;
        }
        $roleId = $this->currentStepRoleId($letter);
        if ($roleId !== null) {
            return $user->role?->id === $roleId;
        }

        return $this->isAddressee($user, $letter);
    }

    /** Is the user an original recipient or referral target of the letter? */
    public function isAddressee(User $user, Letter $letter): bool
    {
        foreach ($this->recipients->forLetter($letter->id) as $r) {
            if ($r->userId === $user->id) {
                return true;
            }
            if ($r->departmentId !== null && $r->departmentId === $user->departmentId) {
                return true;
            }
        }

        // Legacy single-recipient columns (seeded / pre-Phase-3 letters).
        return $letter->recipientId === $user->id
            || ($letter->recipientDepartmentId !== null && $letter->recipientDepartmentId === $user->departmentId);
    }

    /**
     * Why (and whether) this letter belongs in the user's کارتابل — returns the
     * grouping key: 'workflow' (their approval step), 'primary' (they are an
     * original recipient), or a ReferralType value (اقدام/اطلاع/استحضار).
     * Returns null when the letter is not in the user's cartable.
     */
    public function cartableGroup(User $user, Letter $letter): ?string
    {
        if ($letter->status === LetterStatus::Draft) {
            return null;
        }

        // A workflow letter awaiting THIS user's approval step stays in the
        // cartable until they act (regardless of whether they've opened it).
        if ($this->approvers->hasChain($letter->id)) {
            $current = $this->approvers->currentPending($letter->id);
            if ($current !== null && (int) $current['user_id'] === $user->id
                && $letter->status === LetterStatus::Pending) {
                return 'workflow';
            }
        } else {
            $roleId = $this->currentStepRoleId($letter);
            if ($roleId !== null && $user->role?->id === $roleId && in_array($letter->status, self::ACTIVE, true)) {
                return 'workflow';
            }
        }

        // Received/referred letters live in «کارتابل من» only until opened; once
        // read they move to «وارده».
        if ($this->reads->isReadBy($letter->id, $user->id)) {
            return null;
        }

        $primary = false;
        foreach ($this->recipients->forLetter($letter->id) as $r) {
            $mine = $r->userId === $user->id
                || ($r->departmentId !== null && $r->departmentId === $user->departmentId);
            if (!$mine) {
                continue;
            }
            // A referral's intent takes precedence over a plain recipient row.
            if ($r->kind === 'referral' && $r->referralType !== null) {
                return $r->referralType->value;
            }
            $primary = true;
        }
        if ($primary) {
            return 'primary';
        }

        // Legacy columns.
        if ($letter->recipientId === $user->id
            || ($letter->recipientDepartmentId !== null && $letter->recipientDepartmentId === $user->departmentId)) {
            return 'primary';
        }

        return null;
    }

    /**
     * Is this letter in the user's «وارده» (received) box — i.e. they are an
     * addressee (recipient or referral target), read or unread, and not the
     * sender. Drafts never appear here.
     */
    public function isRecipientOf(User $user, Letter $letter): bool
    {
        if ($letter->status === LetterStatus::Draft) {
            return false;
        }
        // A referral/reply delivery addressed to this user counts even when the
        // user is the letter's own creator (a reply routes back to them).
        if ($this->hasReferralTo($user, $letter)) {
            return true;
        }
        if ($letter->senderId === $user->id) {
            return false;
        }
        if ($this->isAddressee($user, $letter)) {
            return true;
        }
        // Workflow approver-chain members receive the letter too.
        foreach ($this->approvers->forLetter($letter->id) as $a) {
            if ((int) $a['user_id'] === $user->id) {
                return true;
            }
        }

        return false;
    }

    /** Has this user opened the letter? */
    public function isReadByUser(User $user, Letter $letter): bool
    {
        return $this->reads->isReadBy($letter->id, $user->id);
    }

    /**
     * Has any addressee opened the letter yet? Powers the sender's «ارسالی ›
     * خوانده‌نشده» box. External letters leave the organisation and have no
     * in-system reader, so they are never counted as unread.
     */
    public function isReadByAnyRecipient(Letter $letter): bool
    {
        if ($letter->scope === LetterScope::External) {
            return true;
        }

        return $this->reads->anyRead($letter->id);
    }

    /**
     * May the user approve/reject now? — either it is their turn, or they are an
     * executive (مدیر عامل) exercising override authority. Only on active letters.
     */
    public function canApprove(User $user, Letter $letter): bool
    {
        if (!in_array($letter->status, self::ACTIVE, true)) {
            return false;
        }
        // Only workflow letters have an approve/sign chain.
        if ($letter->workflowId === null) {
            return false;
        }

        return $this->isMyTurn($user, $letter) || ($user->role?->has(Permission::DepartmentsView) ?? false);
    }

    /**
     * Gather and validate the letter's recipients from the request. Accepts
     * both the multi-value fields (recipient_ids / recipient_department_ids)
     * and the legacy single fields.
     *
     * @param array<string,mixed> $data
     * @return array{0:list<int>,1:list<int>} [personIds, deptIds]
     */
    private function collectTargets(array $data, User $author, array $allowIds = []): array
    {
        $scope = LetterScope::tryFrom((string) ($data['scope'] ?? 'internal'));
        if ($scope === LetterScope::External) {
            return [[], []]; // external recipients live in the free-text field
        }

        $personIds = $this->idList($data['recipient_ids'] ?? null);
        $deptIds = $this->idList($data['recipient_department_ids'] ?? null);

        $legacyPerson = $this->intOrNull($data['recipient_id'] ?? null);
        if ($legacyPerson !== null && !in_array($legacyPerson, $personIds, true)) {
            $personIds[] = $legacyPerson;
        }
        $legacyDept = $this->intOrNull($data['recipient_department_id'] ?? null);
        if ($legacyDept !== null && !in_array($legacyDept, $deptIds, true)) {
            $deptIds[] = $legacyDept;
        }

        return [$this->validPersonIds($personIds, $author, $allowIds), $this->validDeptIds($deptIds, $author)];
    }

    /**
     * @param list<int> $ids
     * @param list<int> $allowIds ids exempt from the pyramid check (e.g. reply target)
     * @return list<int>
     */
    private function validPersonIds(array $ids, User $author, array $allowIds = []): array
    {
        $valid = [];
        foreach (array_unique($ids) as $id) {
            $user = $this->users->find($id);
            if ($user === null || !$user->active) {
                throw new ValidationException(['recipient_ids' => 'یکی از گیرندگان انتخاب‌شده نامعتبر یا غیرفعال است.']);
            }
            if (!in_array($id, $allowIds, true) && !$this->policy->canMessage($author, $user)) {
                throw new ValidationException(['recipient_ids' => 'اجازهٔ ارسال به «' . $user->name . '» را ندارید.']);
            }
            $valid[] = $id;
        }

        return $valid;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function validDeptIds(array $ids, User $author): array
    {
        $valid = [];
        foreach (array_unique($ids) as $id) {
            if ($this->departments->find($id) === null) {
                throw new ValidationException(['recipient_department_ids' => 'یکی از واحدهای گیرنده یافت نشد.']);
            }
            if (!$this->policy->canMessageDepartment($author, $id)) {
                $name = $this->departments->find($id)?->name ?? ('#' . $id);
                throw new ValidationException(['recipient_department_ids' => 'اجازهٔ ارسال به واحد «' . $name . '» را ندارید.']);
            }
            $valid[] = $id;
        }

        return $valid;
    }

    /**
     * Build a human-readable recipients label — returns '' when there are none
     * (does not throw; used for drafts).
     *
     * @param list<int> $personIds
     * @param list<int> $deptIds
     */
    private function buildRecipientsLabel(LetterScope $scope, array $personIds, array $deptIds, string $external): string
    {
        if ($scope === LetterScope::External) {
            return $external;
        }

        $labels = [];
        foreach ($personIds as $id) {
            $user = $this->users->find($id);
            if ($user !== null) {
                $labels[] = $user->name . ($user->role ? ' — ' . $user->role->name : '');
            }
        }
        foreach ($deptIds as $id) {
            $dept = $this->departments->find($id);
            if ($dept !== null) {
                $labels[] = $dept->name;
            }
        }

        if ($labels === []) {
            return '';
        }
        if (count($labels) <= 2) {
            return implode('، ', $labels);
        }

        return implode('، ', array_slice($labels, 0, 2))
            . ' و ' . Jalali::toPersianDigits((string) (count($labels) - 2)) . ' گیرندهٔ دیگر';
    }

    /**
     * As buildRecipientsLabel, but the recipient is mandatory (real send).
     *
     * @param list<int> $personIds
     * @param list<int> $deptIds
     */
    private function resolveRecipientsLabel(LetterScope $scope, array $personIds, array $deptIds, string $external): string
    {
        if ($scope === LetterScope::External) {
            if ($external === '') {
                throw new ValidationException(['external_recipient' => 'گیرندهٔ خارج‌سازمانی را وارد کنید.']);
            }

            return $external;
        }

        $label = $this->buildRecipientsLabel($scope, $personIds, $deptIds, $external);
        if ($label === '') {
            throw new ValidationException(['recipient_id' => 'گیرندهٔ نامه را مشخص کنید (شخص یا واحد).']);
        }

        return $label;
    }

    /**
     * @return list<int>
     */
    private function idList(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $item) {
            $id = $this->intOrNull($item);
            if ($id !== null) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    private function stepCount(?int $workflowId): int
    {
        if ($workflowId !== null) {
            $wf = $this->workflows->find($workflowId);
            if ($wf !== null && $wf->steps !== []) {
                return count($wf->steps);
            }
        }

        return count(self::STAGES);
    }

    private function intOrNull(mixed $v): ?int
    {
        return ($v === null || $v === '' || $v === 0 || $v === '0') ? null : (int) $v;
    }

    private function stringOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }
}
