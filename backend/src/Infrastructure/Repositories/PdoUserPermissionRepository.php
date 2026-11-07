<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Repositories\UserPermissionRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoUserPermissionRepository implements UserPermissionRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<string> */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare('SELECT permission FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$userId]);

        return array_map(static fn ($r) => (string) $r['permission'], $stmt->fetchAll());
    }

    /** @param list<string> $permissions */
    public function setForUser(int $userId, array $permissions): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
            $stmt = $pdo->prepare('INSERT INTO user_permissions (user_id, permission) VALUES (?, ?)');
            foreach (array_values(array_unique(array_filter(array_map('strval', $permissions)))) as $perm) {
                if ($perm !== '') {
                    $stmt->execute([$userId, $perm]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int,list<string>> */
    public function grantsMap(): array
    {
        $out = [];
        foreach ($this->pdo()->query('SELECT user_id, permission FROM user_permissions')->fetchAll() as $r) {
            $out[(int) $r['user_id']][] = (string) $r['permission'];
        }

        return $out;
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
