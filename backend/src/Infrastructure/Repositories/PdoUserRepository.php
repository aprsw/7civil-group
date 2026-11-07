<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Entities\User;
use SevenCivilGroup\Domain\Repositories\DepartmentRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\RoleRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\UserRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

/**
 * Users are hydrated together with their Role and Department (composition via
 * the other repositories — Dependency Inversion, no cross-table SQL coupling).
 */
final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RoleRepositoryInterface $roles,
        private readonly DepartmentRepositoryInterface $departments,
    ) {
    }

    /** @return list<User> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    public function find(int $id): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /** @return list<User> */
    public function byDepartment(int $departmentId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE department_id = ? ORDER BY id ASC');
        $stmt->execute([$departmentId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): User
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (name, username, password_hash, email, role_id, department_id, avatar_color)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['username'], $data['password_hash'], $data['email'] ?? '',
            $data['role_id'], $data['department_id'], $data['avatar_color'] ?? 'brand',
        ]);

        return $this->find((int) $this->pdo()->lastInsertId());
    }

    public function touchLastSeen(int $id): void
    {
        $stmt = $this->pdo()->prepare('UPDATE users SET last_seen_at = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    /** @param array<string,mixed> $fields */
    public function update(int $id, array $fields): ?User
    {
        $allowed = ['name', 'username', 'password_hash', 'email', 'role_id', 'department_id', 'avatar_color',
            'birth_date', 'address', 'mobile', 'phone', 'description', 'photo_path', 'signature_path', 'active'];
        $sets = [];
        $values = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = ?";
                $values[] = $fields[$col];
            }
        }
        if ($sets === []) {
            return $this->find($id);
        }
        $values[] = $id;
        $stmt = $this->pdo()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return User::fromRow(
            $row,
            $this->roles->find((int) $row['role_id']),
            $this->departments->find((int) $row['department_id']),
        );
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
