<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

interface ChatRepositoryInterface
{
    /** @return array<string,mixed>|null the conversation row */
    public function find(int $id): ?array;

    /** @return list<int> member user ids */
    public function members(int $conversationId): array;

    public function isMember(int $conversationId, int $userId): bool;

    /**
     * Direct conversation between two users, if one already exists.
     */
    public function findDirect(int $userA, int $userB): ?int;

    /**
     * @param list<int> $memberIds
     * @return int new conversation id
     */
    public function create(string $type, ?string $title, int $createdBy, array $memberIds): int;

    public function delete(int $conversationId): void;

    /** @return list<array<string,mixed>> conversation rows the user belongs to */
    public function forUser(int $userId): array;

    /** @return array<string,mixed>|null the most recent message of a conversation */
    public function lastMessage(int $conversationId): ?array;

    /** @return list<array<string,mixed>> messages, oldest first */
    public function messages(int $conversationId): array;

    /**
     * @param array{body?:string,file_path?:?string,file_name?:?string,file_mime?:?string,file_size?:?int} $data
     */
    public function addMessage(int $conversationId, int $userId, array $data): int;

    /**
     * Purge messages (and report their file paths) older than $days. Empty
     * direct conversations may remain; group metadata is kept.
     *
     * @return list<string> stored file paths to unlink
     */
    public function purgeOlderThan(int $days): array;
}
