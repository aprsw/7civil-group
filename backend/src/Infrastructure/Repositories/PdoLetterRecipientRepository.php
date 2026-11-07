<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Entities\LetterRecipient;
use ArtaPharma\Domain\Repositories\LetterRecipientRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoLetterRecipientRepository implements LetterRecipientRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function add(int $letterId, array $data): LetterRecipient
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO letter_recipients
             (letter_id, user_id, department_id, kind, referral_type, referred_by, note, read_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)'
        );
        $stmt->execute([
            $letterId,
            $data['user_id'] ?? null,
            $data['department_id'] ?? null,
            $data['kind'] ?? 'recipient',
            $data['referral_type'] ?? null,
            $data['referred_by'] ?? null,
            (string) ($data['note'] ?? ''),
            date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->pdo()->lastInsertId();

        return $this->find($id);
    }

    /** @return list<LetterRecipient> */
    public function forLetter(int $letterId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letter_recipients WHERE letter_id = ? ORDER BY id ASC');
        $stmt->execute([$letterId]);

        return array_map(LetterRecipient::fromRow(...), $stmt->fetchAll());
    }

    /** @return list<LetterRecipient> */
    public function forUser(int $userId, int $departmentId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM letter_recipients WHERE user_id = ? OR department_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$userId, $departmentId]);

        return array_map(LetterRecipient::fromRow(...), $stmt->fetchAll());
    }

    public function markRead(int $letterId, int $userId): int
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE letter_recipients SET read_at = ? WHERE letter_id = ? AND user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $letterId, $userId]);

        return $stmt->rowCount();
    }

    public function hasBeenRead(int $letterId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM letter_recipients WHERE letter_id = ? AND read_at IS NOT NULL');
        $stmt->execute([$letterId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function deleteForLetter(int $letterId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_recipients WHERE letter_id = ?')->execute([$letterId]);
    }

    public function exists(int $letterId, int $userId, ?string $kind = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM letter_recipients WHERE letter_id = ? AND user_id = ?';
        $params = [$letterId, $userId];
        if ($kind !== null) {
            $sql .= ' AND kind = ?';
            $params[] = $kind;
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function find(int $id): LetterRecipient
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letter_recipients WHERE id = ?');
        $stmt->execute([$id]);

        return LetterRecipient::fromRow($stmt->fetch());
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
