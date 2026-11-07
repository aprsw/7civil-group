<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Entities\Role;
use SevenCivilGroup\Domain\Repositories\RoleRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoRoleRepository implements RoleRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<Role> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM roles ORDER BY level ASC')->fetchAll();

        return array_map(Role::fromRow(...), $rows);
    }

    public function find(int $id): ?Role
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Role::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?Role
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM roles WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        return $row ? Role::fromRow($row) : null;
    }

    public function save(Role $role): Role
    {
        $perms = json_encode($role->permissions, JSON_UNESCAPED_UNICODE);
        if ($role->id > 0) {
            $stmt = $this->pdo()->prepare('UPDATE roles SET name = ?, slug = ?, level = ?, permissions = ? WHERE id = ?');
            $stmt->execute([$role->name, $role->slug, $role->level, $perms, $role->id]);

            return $this->find($role->id) ?? $role;
        }

        $stmt = $this->pdo()->prepare('INSERT INTO roles (name, slug, level, permissions) VALUES (?, ?, ?, ?)');
        $stmt->execute([$role->name, $role->slug, $role->level, $perms]);

        return $this->find((int) $this->pdo()->lastInsertId()) ?? $role;
    }

    public function delete(int $id): void
    {
        $this->pdo()->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
