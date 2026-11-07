<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\Department;

interface DepartmentRepositoryInterface
{
    /** @return list<Department> */
    public function all(): array;

    public function find(int $id): ?Department;

    public function save(Department $department): Department;

    public function delete(int $id): bool;
}
