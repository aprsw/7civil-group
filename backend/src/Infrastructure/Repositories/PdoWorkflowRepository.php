<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Entities\Workflow;
use SevenCivilGroup\Domain\Repositories\WorkflowRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoWorkflowRepository implements WorkflowRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<Workflow> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM workflows ORDER BY id ASC')->fetchAll();

        return array_map(Workflow::fromRow(...), $rows);
    }

    /** @return list<Workflow> */
    public function byDepartment(int $departmentId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM workflows WHERE department_id = ? ORDER BY id ASC');
        $stmt->execute([$departmentId]);

        return array_map(Workflow::fromRow(...), $stmt->fetchAll());
    }

    public function find(int $id): ?Workflow
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM workflows WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Workflow::fromRow($row) : null;
    }

    public function save(Workflow $workflow): Workflow
    {
        $steps = json_encode(
            array_map(static fn ($s) => $s->toArray(), $workflow->steps),
            JSON_UNESCAPED_UNICODE,
        );
        $approverIds = json_encode(array_values($workflow->approverIds));

        if ($workflow->id > 0 && $this->find($workflow->id) !== null) {
            $stmt = $this->pdo()->prepare('UPDATE workflows SET name = ?, description = ?, department_id = ?, steps = ?, approver_ids = ? WHERE id = ?');
            $stmt->execute([$workflow->name, $workflow->description, $workflow->departmentId, $steps, $approverIds, $workflow->id]);

            return $this->find($workflow->id) ?? $workflow;
        }

        $stmt = $this->pdo()->prepare('INSERT INTO workflows (name, description, department_id, steps, approver_ids, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$workflow->name, $workflow->description, $workflow->departmentId, $steps, $approverIds, $workflow->createdBy]);

        return $this->find((int) $this->pdo()->lastInsertId()) ?? $workflow;
    }

    public function delete(int $id): void
    {
        // Detach any letters bound to this workflow so they don't dangle.
        $this->pdo()->prepare('UPDATE letters SET workflow_id = NULL WHERE workflow_id = ?')->execute([$id]);
        $this->pdo()->prepare('DELETE FROM workflows WHERE id = ?')->execute([$id]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
