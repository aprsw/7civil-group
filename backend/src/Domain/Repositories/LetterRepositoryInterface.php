<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\Letter;
use SevenCivilGroup\Domain\Enums\LetterDirection;

interface LetterRepositoryInterface
{
    /** @return list<Letter> */
    public function all(): array;

    /** @return list<Letter> */
    public function byDirection(LetterDirection $direction): array;

    public function find(int $id): ?Letter;

    /** @return list<Letter> most recent first */
    public function recent(int $limit): array;

    /** @return list<Letter> */
    public function archived(): array;

    public function create(Letter $letter): Letter;

    public function updateStatus(int $id, string $status, int $stage): ?Letter;

    /**
     * Update whitelisted content fields (used for editing / finalizing drafts).
     *
     * @param array<string,mixed> $fields
     */
    public function update(int $id, array $fields): ?Letter;

    /** Permanently remove a letter (used to recall/discard). */
    public function delete(int $id): void;

    public function countByDirection(LetterDirection $direction): int;

    /**
     * Is any of the given registration numbers already taken? (Callers pass
     * every digit-form of the same number so Persian/Latin digits can't collide.)
     *
     * @param list<string> $numbers
     */
    public function numberExists(array $numbers): bool;

    /**
     * The registration number of the most recently registered دبیرخانه letter
     * (optionally restricted to the incoming/outgoing box); null when none.
     */
    public function lastSecretariatNumber(?string $box = null): ?string;

    /** @return list<Letter> every letter in the thread, oldest first */
    public function thread(int $threadId): array;
}
