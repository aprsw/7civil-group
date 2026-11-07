<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

interface SecretariatFolderRepositoryInterface
{
    /** @return list<array{id:int,name:string}> */
    public function all(): array;

    public function create(string $name, ?int $userId): int;

    public function rename(int $id, string $name): void;

    public function delete(int $id): void;

    /** @return array<int,int> letterId ⇒ folderId */
    public function letterFolderMap(): array;
}
