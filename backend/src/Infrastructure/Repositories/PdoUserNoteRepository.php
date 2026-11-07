<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Repositories\UserNoteRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoUserNoteRepository implements UserNoteRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, title, description, note_date, note_gdate, remind_days_before
             FROM user_notes WHERE user_id = ? ORDER BY note_gdate ASC, id ASC'
        );
        $stmt->execute([$userId]);

        return array_map([$this, 'present'], $stmt->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, title, description, note_date, note_gdate, remind_days_before
             FROM user_notes WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row ? $this->present($row) : null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_notes (user_id, title, description, note_date, note_gdate, remind_days_before, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId, $data['title'], $data['description'], $data['note_date'],
            $data['note_gdate'], $data['remind_days_before'], date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE user_notes SET title = ?, description = ?, note_date = ?, note_gdate = ?, remind_days_before = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['title'], $data['description'], $data['note_date'],
            $data['note_gdate'], $data['remind_days_before'], $id, $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $this->pdo()->prepare('DELETE FROM user_notes WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function present(array $r): array
    {
        $gdate = (string) $r['note_gdate'];
        $remind = (int) $r['remind_days_before'];
        $daysRemaining = null;
        if ($gdate !== '') {
            $today = new \DateTimeImmutable('today');
            $target = \DateTimeImmutable::createFromFormat('Y-m-d', $gdate);
            if ($target !== false) {
                $daysRemaining = (int) $today->diff($target->setTime(0, 0))->format('%r%a');
            }
        }
        // "Due" = today is within the reminder window and the date hasn't passed.
        $isDue = $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= $remind;

        return [
            'id' => (int) $r['id'],
            'title' => (string) $r['title'],
            'description' => (string) ($r['description'] ?? ''),
            'note_date' => (string) $r['note_date'],
            'note_gdate' => $gdate,
            'remind_days_before' => $remind,
            'days_remaining' => $daysRemaining,
            'is_due' => $isDue,
            'is_today' => $daysRemaining === 0,
            'is_past' => $daysRemaining !== null && $daysRemaining < 0,
        ];
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
