<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Repositories\LetterApproverRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoLetterApproverRepository implements LetterApproverRepositoryInterface
{
    use SqlServerLimit;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function setChain(int $letterId, array $userIds): void
    {
        $pdo = $this->pdo();
        $pdo->prepare('DELETE FROM letter_approvers WHERE letter_id = ?')->execute([$letterId]);
        $stmt = $pdo->prepare('INSERT INTO letter_approvers (letter_id, position, user_id, status) VALUES (?, ?, ?, ?)');
        $pos = 0;
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0) {
                $stmt->execute([$letterId, $pos++, $uid, 'pending']);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function forLetter(int $letterId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letter_approvers WHERE letter_id = ? ORDER BY position ASC');
        $stmt->execute([$letterId]);

        return $stmt->fetchAll();
    }

    public function hasChain(int $letterId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM letter_approvers WHERE letter_id = ?');
        $stmt->execute([$letterId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<string,mixed>|null */
    public function currentPending(int $letterId): ?array
    {
        $stmt = $this->pdo()->prepare($this->limitToTop("SELECT * FROM letter_approvers WHERE letter_id = ? AND status = 'pending' ORDER BY position ASC LIMIT 1"));
        $stmt->execute([$letterId]);

        return $stmt->fetch() ?: null;
    }

    public function markApproved(int $rowId, string $note = ''): void
    {
        $this->pdo()->prepare('UPDATE letter_approvers SET status = ?, note = ?, acted_at = ? WHERE id = ?')
            ->execute(['approved', $note, date('Y-m-d H:i:s'), $rowId]);
    }

    public function markRejected(int $rowId, string $note = ''): void
    {
        $this->pdo()->prepare('UPDATE letter_approvers SET status = ?, note = ?, acted_at = ? WHERE id = ?')
            ->execute(['rejected', $note, date('Y-m-d H:i:s'), $rowId]);
    }

    public function deleteForLetter(int $letterId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_approvers WHERE letter_id = ?')->execute([$letterId]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
