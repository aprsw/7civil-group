<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\User;

interface UserRepositoryInterface
{
    /** @return list<User> */
    public function all(): array;

    public function find(int $id): ?User;

    public function findByUsername(string $username): ?User;

    /** @return list<User> */
    public function byDepartment(int $departmentId): array;

    public function count(): int;

    /** @param array<string,mixed> $data */
    public function create(array $data): User;

    /** @param array<string,mixed> $fields */
    public function update(int $id, array $fields): ?User;

    public function delete(int $id): bool;

    public function touchLastSeen(int $id): void;
}
