<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

interface LetterReadRepositoryInterface
{
    /** Record that a user opened a letter (first time only). @return bool newly-read */
    public function markRead(int $letterId, int $userId): bool;

    public function clearRead(int $letterId, int $userId): void;

    public function isReadBy(int $letterId, int $userId): bool;

    /** Has any user read this letter? (senders are never recorded here) */
    public function anyRead(int $letterId): bool;

    /** @return list<int> user ids who have read the letter */
    public function readersOf(int $letterId): array;

    public function deleteForLetter(int $letterId): void;
}
