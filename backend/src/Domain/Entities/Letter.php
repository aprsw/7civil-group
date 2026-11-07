<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Entities;

use SevenCivilGroup\Domain\Enums\Confidentiality;
use SevenCivilGroup\Domain\Enums\LetterDirection;
use SevenCivilGroup\Domain\Enums\LetterKind;
use SevenCivilGroup\Domain\Enums\LetterPriority;
use SevenCivilGroup\Domain\Enums\LetterScope;
use SevenCivilGroup\Domain\Enums\LetterStatus;
use SevenCivilGroup\Domain\Enums\PaperSize;

/**
 * A letter (نامه) flowing through the cartable and its workflow.
 *
 * `stage` is the 0-based index into the steps of the attached workflow
 * (or the default 5-step chain when no workflow is chosen).
 */
final class Letter
{
    public function __construct(
        public readonly int $id,
        public readonly string $number,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $body2,
        public readonly LetterDirection $direction,
        public readonly LetterScope $scope,
        public readonly Confidentiality $confidentiality,
        public readonly LetterPriority $priority,
        public readonly LetterKind $kind,
        public readonly LetterStatus $status,
        public readonly PaperSize $paperSize,
        public readonly int $stage,
        public readonly ?int $workflowId,
        public readonly string $fromLabel,
        public readonly string $toLabel,
        public readonly ?int $senderId,
        public readonly ?int $recipientId,
        public readonly ?int $recipientDepartmentId,
        public readonly string $externalRecipient,
        public readonly int $departmentId,
        public readonly string $attachment,
        public readonly string $signName,
        public readonly string $signRole,
        public readonly string $createdAt, // Gregorian ISO — converted to Jalali by the View layer
        public readonly ?int $replyToId = null,
        public readonly ?int $threadId = null,
        public readonly ?string $attachmentPath = null,
        public readonly ?string $attachmentMime = null,
        public readonly ?int $attachmentSize = null,
        public readonly ?string $secretariat = null, // null | 'in' | 'out'
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $intOrNull = static fn (string $k) => isset($row[$k]) && $row[$k] !== null ? (int) $row[$k] : null;

        return new self(
            (int) $row['id'],
            (string) $row['number'],
            (string) $row['subject'],
            (string) ($row['body'] ?? ''),
            (string) ($row['body2'] ?? ''),
            LetterDirection::from((string) $row['direction']),
            LetterScope::from((string) ($row['scope'] ?? 'internal')),
            Confidentiality::from((string) ($row['confidentiality'] ?? 'normal')),
            LetterPriority::from((string) $row['priority']),
            LetterKind::from((string) ($row['kind'] ?? 'action')),
            LetterStatus::from((string) $row['status']),
            PaperSize::from((string) ($row['paper_size'] ?? 'a4')),
            (int) ($row['stage'] ?? 0),
            $intOrNull('workflow_id'),
            (string) ($row['from_label'] ?? ''),
            (string) ($row['to_label'] ?? ''),
            $intOrNull('sender_id'),
            $intOrNull('recipient_id'),
            $intOrNull('recipient_department_id'),
            (string) ($row['external_recipient'] ?? ''),
            (int) ($row['department_id'] ?? 0),
            (string) ($row['attachment'] ?? '—'),
            (string) ($row['sign_name'] ?? ''),
            (string) ($row['sign_role'] ?? ''),
            (string) ($row['created_at'] ?? 'now'),
            $intOrNull('reply_to_id'),
            $intOrNull('thread_id'),
            isset($row['attachment_path']) ? (string) $row['attachment_path'] : null,
            isset($row['attachment_mime']) ? (string) $row['attachment_mime'] : null,
            $intOrNull('attachment_size'),
            isset($row['secretariat']) && $row['secretariat'] !== null && $row['secretariat'] !== '' ? (string) $row['secretariat'] : null,
        );
    }
}
