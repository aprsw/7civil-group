<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Repositories\SecretariatFolderRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoSecretariatFolderRepository implements SecretariatFolderRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array{id:int,name:string}> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT id, name FROM secretariat_folders ORDER BY name ASC')->fetchAll();

        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $rows);
    }

    public function create(string $name, ?int $userId): int
    {
        $stmt = $this->pdo()->prepare('INSERT INTO secretariat_folders (name, created_by, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$name, $userId, date('Y-m-d H:i:s')]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        $this->pdo()->prepare('UPDATE secretariat_folders SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    public function delete(int $id): void
    {
        // Unfile any letters in this folder, then remove it.
        $this->pdo()->prepare('UPDATE letters SET secretariat_folder_id = NULL WHERE secretariat_folder_id = ?')->execute([$id]);
        $this->pdo()->prepare('DELETE FROM secretariat_folders WHERE id = ?')->execute([$id]);
    }

    /** @return array<int,int> letterId ⇒ folderId */
    public function letterFolderMap(): array
    {
        $out = [];
        foreach ($this->pdo()->query('SELECT id, secretariat_folder_id FROM letters WHERE secretariat_folder_id IS NOT NULL')->fetchAll() as $r) {
            $out[(int) $r['id']] = (int) $r['secretariat_folder_id'];
        }

        return $out;
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
