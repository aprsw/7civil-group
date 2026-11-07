<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

use ArtaPharma\Domain\Entities\LetterRecipient;

interface LetterRecipientRepositoryInterface
{
    /**
     * @param array{user_id?:?int,department_id?:?int,kind?:string,referral_type?:?string,referred_by?:?int,note?:string} $data
     */
    public function add(int $letterId, array $data): LetterRecipient;

    /** @return list<LetterRecipient> */
    public function forLetter(int $letterId): array;

    /**
     * Every recipient row addressed to this user directly or via their unit.
     *
     * @return list<LetterRecipient>
     */
    public function forUser(int $userId, int $departmentId): array;

    /**
     * Mark the given letter as read by the user (first time only).
     *
     * @return int number of rows newly marked read (0 if already read / no rows)
     */
    public function markRead(int $letterId, int $userId): int;

    /** Does a recipient row exist for this exact user + kind on the letter? */
    public function exists(int $letterId, int $userId, ?string $kind = null): bool;

    /** Has any recipient opened (read) this letter? */
    public function hasBeenRead(int $letterId): bool;

    /** Remove all recipient rows for a letter (used when recalling). */
    public function deleteForLetter(int $letterId): void;
}
