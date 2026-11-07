<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Entities;

/**
 * One logo in the organisation's logo library (کتابخانهٔ لوگو). The group has
 * several member companies, each with its own mark; the admin picks which one
 * is printed on the letterhead, shown on the login screen and in the sidebar.
 */
final class OrgLogo
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $path,
        public readonly ?string $mime,
        public readonly ?int $size,
        public readonly ?int $uploadedBy,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['path'],
            isset($row['mime']) ? (string) $row['mime'] : null,
            isset($row['size']) ? (int) $row['size'] : null,
            isset($row['uploaded_by']) && $row['uploaded_by'] !== null ? (int) $row['uploaded_by'] : null,
            (string) ($row['created_at'] ?? ''),
        );
    }

    /** Browser-reachable URL (the backend serves public/uploads directly). */
    public function url(): string
    {
        return '/uploads/logos/' . $this->path;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url(),
            'mime' => $this->mime,
            'size' => $this->size,
        ];
    }
}
