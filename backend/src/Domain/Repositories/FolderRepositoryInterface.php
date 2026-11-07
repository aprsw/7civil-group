<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

interface FolderRepositoryInterface
{
    /** @return list<array<string,mixed>> the user's folders (system بایگانی first) */
    public function forUser(int $userId): array;

    public function find(int $folderId): ?array;

    public function create(int $userId, string $name, bool $isSystem = false): int;

    public function rename(int $folderId, string $name): void;

    public function delete(int $folderId): void;

    /** Ensure the user has their system «بایگانی» folder; returns its id. */
    public function ensureArchive(int $userId): int;

    /** @return list<int> letter ids filed anywhere by the user */
    public function filedLetterIds(int $userId): array;

    /** @return list<int> letter ids in a specific folder */
    public function letterIdsIn(int $folderId): array;

    public function fileLetter(int $folderId, int $letterId, int $userId): void;

    /** Remove a letter from all of the user's folders. */
    public function unfileLetter(int $letterId, int $userId): void;

    public function countIn(int $folderId): int;
}
