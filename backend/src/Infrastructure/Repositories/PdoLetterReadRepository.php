<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Repositories\LetterReadRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoLetterReadRepository implements LetterReadRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function markRead(int $letterId, int $userId): bool
    {
        if ($this->isReadBy($letterId, $userId)) {
            return false;
        }
        $this->pdo()->prepare('INSERT INTO letter_reads (letter_id, user_id, read_at) VALUES (?, ?, ?)')
            ->execute([$letterId, $userId, date('Y-m-d H:i:s')]);

        return true;
    }

    public function clearRead(int $letterId, int $userId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_reads WHERE letter_id = ? AND user_id = ?')
            ->execute([$letterId, $userId]);
    }

    public function isReadBy(int $letterId, int $userId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM letter_reads WHERE letter_id = ? AND user_id = ?');
        $stmt->execute([$letterId, $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function anyRead(int $letterId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM letter_reads WHERE letter_id = ?');
        $stmt->execute([$letterId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return list<int> */
    public function readersOf(int $letterId): array
    {
        $stmt = $this->pdo()->prepare('SELECT user_id FROM letter_reads WHERE letter_id = ?');
        $stmt->execute([$letterId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }

    public function deleteForLetter(int $letterId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_reads WHERE letter_id = ?')->execute([$letterId]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
