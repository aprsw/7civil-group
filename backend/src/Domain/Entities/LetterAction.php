<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Entities;

use SevenCivilGroup\Domain\Enums\LetterActionType;

/**
 * One entry in a letter's audit trail / history (چه کسی، کِی، چه اقدامی).
 */
final class LetterAction
{
    public function __construct(
        public readonly int $id,
        public readonly int $letterId,
        public readonly ?int $userId,
        public readonly LetterActionType $action,
        public readonly ?int $fromStage,
        public readonly ?int $toStage,
        public readonly string $note,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['letter_id'],
            isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            LetterActionType::from((string) $row['action']),
            isset($row['from_stage']) && $row['from_stage'] !== null ? (int) $row['from_stage'] : null,
            isset($row['to_stage']) && $row['to_stage'] !== null ? (int) $row['to_stage'] : null,
            (string) ($row['note'] ?? ''),
            (string) $row['created_at'],
        );
    }
}
