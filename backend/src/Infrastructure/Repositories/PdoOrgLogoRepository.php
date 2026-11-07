<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use PDO;
use SevenCivilGroup\Domain\Entities\OrgLogo;
use SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;

final class PdoOrgLogoRepository implements OrgLogoRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<OrgLogo> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM org_logos ORDER BY id ASC')->fetchAll();

        return array_map(OrgLogo::fromRow(...), $rows);
    }

    public function find(int $id): ?OrgLogo
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM org_logos WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? OrgLogo::fromRow($row) : null;
    }

    public function create(string $title, string $path, ?string $mime, ?int $size, ?int $uploadedBy): OrgLogo
    {
        $pdo = $this->pdo();
        $pdo->prepare('INSERT INTO org_logos (title, path, mime, size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$title, $path, $mime, $size, $uploadedBy, date('Y-m-d H:i:s')]);

        return $this->find((int) $pdo->lastInsertId()) ?? OrgLogo::fromRow([
            'id' => 0, 'title' => $title, 'path' => $path, 'mime' => $mime,
            'size' => $size, 'uploaded_by' => $uploadedBy, 'created_at' => '',
        ]);
    }

    public function rename(int $id, string $title): ?OrgLogo
    {
        $this->pdo()->prepare('UPDATE org_logos SET title = ? WHERE id = ?')->execute([$title, $id]);

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $this->pdo()->prepare('DELETE FROM org_logos WHERE id = ?')->execute([$id]);
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
