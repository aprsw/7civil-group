<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Entities\Department;
use ArtaPharma\Domain\Repositories\DepartmentRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoDepartmentRepository implements DepartmentRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<Department> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM departments ORDER BY id ASC')->fetchAll();

        return array_map(Department::fromRow(...), $rows);
    }

    public function find(int $id): ?Department
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM departments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Department::fromRow($row) : null;
    }

    public function save(Department $department): Department
    {
        if ($department->id > 0 && $this->find($department->id) !== null) {
            $stmt = $this->pdo()->prepare('UPDATE departments SET name = ?, code = ?, description = ? WHERE id = ?');
            $stmt->execute([$department->name, $department->code, $department->description, $department->id]);

            return $this->find($department->id) ?? $department;
        }

        $stmt = $this->pdo()->prepare('INSERT INTO departments (name, code, description) VALUES (?, ?, ?)');
        $stmt->execute([$department->name, $department->code, $department->description]);

        return $this->find((int) $this->pdo()->lastInsertId()) ?? $department;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM departments WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
