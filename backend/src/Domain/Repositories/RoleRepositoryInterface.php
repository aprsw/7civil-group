<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\Role;

interface RoleRepositoryInterface
{
    /** @return list<Role> */
    public function all(): array;

    public function find(int $id): ?Role;

    public function findBySlug(string $slug): ?Role;

    /** Insert (id = 0) or update a role; returns the persisted role. */
    public function save(Role $role): Role;

    public function delete(int $id): void;
}
