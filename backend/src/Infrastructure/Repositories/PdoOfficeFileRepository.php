<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Repositories\OfficeFileRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoOfficeFileRepository implements OfficeFileRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT id, name, path, mime, size, uploaded_by, created_at FROM office_files ORDER BY id DESC')->fetchAll();

        return array_map([$this, 'present'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT id, name, path, mime, size, uploaded_by, created_at FROM office_files WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->present($row) : null;
    }

    /** @param array{name:string,path:string,mime:?string,size:?int,uploaded_by:?int} $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO office_files (name, path, mime, size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['path'],
            $data['mime'] ?? null,
            $data['size'] ?? null,
            $data['uploaded_by'] ?? null,
            date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->pdo()->prepare('DELETE FROM office_files WHERE id = ?')->execute([$id]);
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function present(array $r): array
    {
        $name = (string) $r['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return [
            'id' => (int) $r['id'],
            'name' => $name,
            'path' => (string) $r['path'],
            'mime' => $r['mime'] !== null ? (string) $r['mime'] : null,
            'size' => $r['size'] !== null ? (int) $r['size'] : null,
            'ext' => $ext,
            'kind' => in_array($ext, ['xlsx', 'xls', 'csv'], true) ? 'excel'
                : (in_array($ext, ['docx', 'doc', 'rtf'], true) ? 'word' : 'other'),
            'created_at' => (string) $r['created_at'],
        ];
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
