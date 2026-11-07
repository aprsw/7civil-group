<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

use ArtaPharma\Domain\Entities\Workflow;

interface WorkflowRepositoryInterface
{
    /** @return list<Workflow> */
    public function all(): array;

    /** @return list<Workflow> */
    public function byDepartment(int $departmentId): array;

    public function find(int $id): ?Workflow;

    public function save(Workflow $workflow): Workflow;

    public function delete(int $id): void;
}
