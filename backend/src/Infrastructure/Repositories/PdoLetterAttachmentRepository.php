<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Entities\LetterAttachment;
use ArtaPharma\Domain\Repositories\LetterAttachmentRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoLetterAttachmentRepository implements LetterAttachmentRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function add(int $letterId, array $data): LetterAttachment
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO letter_attachments (letter_id, name, path, mime, size, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $letterId,
            (string) $data['name'],
            (string) $data['path'],
            $data['mime'] ?? null,
            $data['size'] ?? null,
            date('Y-m-d H:i:s'),
        ]);

        return $this->find((int) $this->pdo()->lastInsertId());
    }

    /** @return list<LetterAttachment> */
    public function forLetter(int $letterId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letter_attachments WHERE letter_id = ? ORDER BY id ASC');
        $stmt->execute([$letterId]);

        return array_map(LetterAttachment::fromRow(...), $stmt->fetchAll());
    }

    public function find(int $id): ?LetterAttachment
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letter_attachments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? LetterAttachment::fromRow($row) : null;
    }

    public function deleteForLetter(int $letterId): void
    {
        $this->pdo()->prepare('DELETE FROM letter_attachments WHERE letter_id = ?')->execute([$letterId]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
