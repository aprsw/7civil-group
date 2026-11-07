<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Entities\Event;
use SevenCivilGroup\Domain\Repositories\EventRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoEventRepository implements EventRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<Event> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM events ORDER BY event_date DESC, id DESC')->fetchAll();

        return array_map(Event::fromRow(...), $rows);
    }

    public function find(int $id): ?Event
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Event::fromRow($row) : null;
    }

    public function create(array $data): Event
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO events (title, description, event_date, location, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['title'], $data['description'], $data['event_date'],
            $data['location'], $data['created_by'] ?? null, date('Y-m-d H:i:s'),
        ]);

        return $this->find((int) $this->pdo()->lastInsertId());
    }

    public function update(int $id, array $data): ?Event
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE events SET title = ?, description = ?, event_date = ?, location = ? WHERE id = ?'
        );
        $stmt->execute([$data['title'], $data['description'], $data['event_date'], $data['location'], $id]);

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->pdo()->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
