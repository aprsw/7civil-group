<?php

declare(strict_types=1);

namespace ArtaPharma\Infrastructure\Repositories;

use ArtaPharma\Domain\Entities\Template;
use ArtaPharma\Domain\Repositories\TemplateRepositoryInterface;
use ArtaPharma\Infrastructure\Database\Connection;
use PDO;

final class PdoTemplateRepository implements TemplateRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<Template> */
    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM templates ORDER BY id ASC')->fetchAll();

        return array_map(Template::fromRow(...), $rows);
    }

    public function find(int $id): ?Template
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM templates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Template::fromRow($row) : null;
    }

    public function save(Template $template): Template
    {
        if ($template->id > 0 && $this->find($template->id) !== null) {
            $stmt = $this->pdo()->prepare('UPDATE templates SET title = ?, body = ?, category = ?, paper_size = ?, department_id = ? WHERE id = ?');
            $stmt->execute([$template->title, $template->body, $template->category, $template->paperSize->value, $template->departmentId, $template->id]);

            return $this->find($template->id) ?? $template;
        }

        $stmt = $this->pdo()->prepare('INSERT INTO templates (title, body, category, paper_size, department_id, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$template->title, $template->body, $template->category, $template->paperSize->value, $template->departmentId, $template->createdBy]);

        return $this->find((int) $this->pdo()->lastInsertId()) ?? $template;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM templates WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
