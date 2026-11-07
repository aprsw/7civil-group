<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Entities;

use ArtaPharma\Domain\Enums\ReferralType;

/**
 * A single addressee of a letter — either an original recipient (kind
 * "recipient") or a later referral (kind "referral", carrying a ReferralType).
 * Targets a person (userId) or a whole unit (departmentId).
 */
final class LetterRecipient
{
    public function __construct(
        public readonly int $id,
        public readonly int $letterId,
        public readonly ?int $userId,
        public readonly ?int $departmentId,
        public readonly string $kind,               // 'recipient' | 'referral'
        public readonly ?ReferralType $referralType,
        public readonly ?int $referredBy,
        public readonly string $note,
        public readonly ?string $readAt,
        public readonly string $createdAt,
    ) {
    }

    public function isReferral(): bool
    {
        return $this->kind === 'referral';
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $intOrNull = static fn (string $k) => isset($row[$k]) && $row[$k] !== null ? (int) $row[$k] : null;

        return new self(
            (int) $row['id'],
            (int) $row['letter_id'],
            $intOrNull('user_id'),
            $intOrNull('department_id'),
            (string) ($row['kind'] ?? 'recipient'),
            isset($row['referral_type']) && $row['referral_type'] !== null && $row['referral_type'] !== ''
                ? ReferralType::tryFrom((string) $row['referral_type'])
                : null,
            $intOrNull('referred_by'),
            (string) ($row['note'] ?? ''),
            isset($row['read_at']) && $row['read_at'] !== null ? (string) $row['read_at'] : null,
            (string) ($row['created_at'] ?? ''),
        );
    }
}
