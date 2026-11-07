<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

interface UserNoteRepositoryInterface
{
    /** @return list<array<string,mixed>> notes owned by the user, soonest first */
    public function forUser(int $userId): array;

    /** @return array<string,mixed>|null */
    public function find(int $id, int $userId): ?array;

    /** @param array{title:string,description:string,note_date:string,note_gdate:string,remind_days_before:int} $data */
    public function create(int $userId, array $data): int;

    /** @param array{title:string,description:string,note_date:string,note_gdate:string,remind_days_before:int} $data */
    public function update(int $id, int $userId, array $data): void;

    public function delete(int $id, int $userId): void;
}
