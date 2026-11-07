<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Repositories\FolderRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoFolderRepository implements FolderRepositoryInterface
{
    use SqlServerLimit;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        $this->ensureArchive($userId);
        $stmt = $this->pdo()->prepare('SELECT * FROM user_folders WHERE user_id = ? ORDER BY is_system DESC, id ASC');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $folderId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM user_folders WHERE id = ?');
        $stmt->execute([$folderId]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, string $name, bool $isSystem = false): int
    {
        $this->pdo()->prepare('INSERT INTO user_folders (user_id, name, is_system, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $name, $isSystem ? 1 : 0, date('Y-m-d H:i:s')]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function rename(int $folderId, string $name): void
    {
        $this->pdo()->prepare('UPDATE user_folders SET name = ? WHERE id = ?')->execute([$name, $folderId]);
    }

    public function delete(int $folderId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_folder_items WHERE folder_id = ?')->execute([$folderId]);
        $this->pdo()->prepare('DELETE FROM user_folders WHERE id = ?')->execute([$folderId]);
    }

    public function ensureArchive(int $userId): int
    {
        $stmt = $this->pdo()->prepare($this->limitToTop('SELECT id FROM user_folders WHERE user_id = ? AND is_system = 1 ORDER BY id ASC LIMIT 1'));
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        return $this->create($userId, 'بایگانی', true);
    }

    /** @return list<int> */
    public function filedLetterIds(int $userId): array
    {
        $stmt = $this->pdo()->prepare('SELECT DISTINCT letter_id FROM letter_folder_items WHERE user_id = ?');
        $stmt->execute([$userId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'letter_id'));
    }

    /** @return list<int> */
    public function letterIdsIn(int $folderId): array
    {
        $stmt = $this->pdo()->prepare('SELECT letter_id FROM letter_folder_items WHERE folder_id = ?');
        $stmt->execute([$folderId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'letter_id'));
    }

    public function fileLetter(int $folderId, int $letterId, int $userId): void
    {
        // A letter lives in exactly one folder per user — clear then set.
        $this->unfileLetter($letterId, $userId);
        $this->pdo()->prepare('INSERT INTO letter_folder_items (folder_id, letter_id, user_id) VALUES (?, ?, ?)')
            ->execute([$folderId, $letterId, $userId]);
    }

    public function unfileLetter(int $letterId, int $userId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_folder_items WHERE letter_id = ? AND user_id = ?')
            ->execute([$letterId, $userId]);
    }

    public function countIn(int $folderId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM letter_folder_items WHERE folder_id = ?');
        $stmt->execute([$folderId]);

        return (int) $stmt->fetchColumn();
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
