<?php

declare(strict_types=1);

namespace SevenCivilGroup\View;

use SevenCivilGroup\Domain\Entities\Letter;
use SevenCivilGroup\Domain\Repositories\RoleRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\WorkflowRepositoryInterface;
use SevenCivilGroup\Services\LetterService;
use SevenCivilGroup\Support\Jalali;

/**
 * View layer (the "V" in MVC-R): turns a Letter entity into the JSON shape the
 * Vue client expects — Jalali dates, tone/label pairs, and the workflow-derived
 * stepper stages.
 */
final class LetterTransformer
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly RoleRepositoryInterface $roles,
    ) {
    }

    /** @return array<string, mixed> */
    public function transform(Letter $letter): array
    {
        $jalali = Jalali::fromGregorian($letter->createdAt);
        $hasAttachment = $letter->attachment !== '' && $letter->attachment !== '—';
        [$stageLabel, $currentRole, $nextRole] = $this->workflowStage($letter);

        return [
            'id' => $letter->id,
            'number' => $letter->number,
            'subject' => $letter->subject,
            'body' => $letter->body,
            'body2' => $letter->body2,
            'direction' => $letter->direction->value,
            'direction_label' => $letter->direction->label(),
            'scope' => ['value' => $letter->scope->value, 'label' => $letter->scope->label(), 'tone' => $letter->scope->tone()],
            'confidentiality' => ['value' => $letter->confidentiality->value, 'label' => $letter->confidentiality->label(), 'tone' => $letter->confidentiality->tone()],
            'priority' => ['tone' => $letter->priority->tone(), 'label' => $letter->priority->label(), 'value' => $letter->priority->value],
            'kind' => ['tone' => $letter->kind->tone(), 'label' => $letter->kind->label(), 'value' => $letter->kind->value],
            'status' => ['tone' => $letter->status->tone(), 'label' => $letter->status->label(), 'value' => $letter->status->value],
            'paper_size' => $letter->paperSize->value,
            'stage' => $letter->stage,
            'stages' => $this->stagesFor($letter),
            'workflow_id' => $letter->workflowId,
            'is_workflow' => $letter->workflowId !== null,
            'letter_type_label' => $letter->workflowId !== null ? 'گردش‌کاری' : 'عادی',
            // Workflow status = the current design step's title + who acts next.
            'stage_label' => $stageLabel,
            'current_role' => $currentRole,
            'next_role' => $nextRole,
            'secretariat' => $letter->secretariat,
            'from' => $letter->fromLabel,
            'to' => $letter->toLabel,
            'sender_id' => $letter->senderId,
            'recipient_id' => $letter->recipientId,
            'recipient_department_id' => $letter->recipientDepartmentId,
            'external_recipient' => $letter->externalRecipient,
            'reply_to_id' => $letter->replyToId,
            'thread_id' => $letter->threadId,
            'is_reply' => $letter->replyToId !== null,
            'attachment' => $letter->attachment,
            'attachment_short' => $hasAttachment ? 'دارد' : 'ندارد',
            'has_file' => $letter->attachmentPath !== null,
            'attachment_size_label' => $letter->attachmentSize !== null ? $this->humanSize($letter->attachmentSize) : null,
            'sign_name' => $letter->signName,
            'sign_role' => $letter->signRole,
            'department_id' => $letter->departmentId,
            'date' => $jalali->format('Y/m/d'),
            'date_long' => $jalali->format('l j F Y') . ' — ' . $jalali->format('H:i'),
        ];
    }

    /**
     * @param list<Letter> $letters
     * @return list<array<string, mixed>>
     */
    public function collection(array $letters): array
    {
        return array_map($this->transform(...), $letters);
    }

    /**
     * For workflow letters: [current step title, role acting now, next role].
     * Non-workflow letters get [null, null, null].
     *
     * @return array{0:?string,1:?string,2:?string}
     */
    private function workflowStage(Letter $letter): array
    {
        if ($letter->workflowId === null) {
            return [null, null, null];
        }
        $wf = $this->workflows->find($letter->workflowId);
        if ($wf === null || $wf->steps === []) {
            return [null, null, null];
        }
        $steps = $wf->steps;
        $idx = max(0, min($letter->stage, count($steps) - 1));
        $roleName = fn (?int $rid) => $rid === null ? 'دبیرخانه / سیستم' : ($this->roles->find($rid)?->name ?? null);
        $stageLabel = $steps[$idx]->label ?? null;
        $current = isset($steps[$idx]) ? $roleName($steps[$idx]->roleId) : null;
        $next = isset($steps[$idx + 1]) ? $roleName($steps[$idx + 1]->roleId) : null;

        return [$stageLabel, $current, $next];
    }

    /** @return list<string> */
    private function stagesFor(Letter $letter): array
    {
        if ($letter->workflowId !== null) {
            $wf = $this->workflows->find($letter->workflowId);
            if ($wf !== null && $wf->steps !== []) {
                return array_map(static fn ($s) => $s->label, $wf->steps);
            }
        }

        return LetterService::STAGES;
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
}
