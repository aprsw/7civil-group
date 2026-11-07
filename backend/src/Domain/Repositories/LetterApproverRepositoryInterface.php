<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

interface LetterApproverRepositoryInterface
{
    /** Replace the ordered approver chain for a letter. @param list<int> $userIds */
    public function setChain(int $letterId, array $userIds): void;

    /** @return list<array<string,mixed>> rows ordered by position */
    public function forLetter(int $letterId): array;

    public function hasChain(int $letterId): bool;

    /** The first still-pending approver row, or null when the chain is done. @return array<string,mixed>|null */
    public function currentPending(int $letterId): ?array;

    public function markApproved(int $rowId, string $note = ''): void;

    public function markRejected(int $rowId, string $note = ''): void;

    public function deleteForLetter(int $letterId): void;
}
