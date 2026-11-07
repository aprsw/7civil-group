<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Entities;

use SevenCivilGroup\Domain\Enums\PaperSize;

/**
 * A reusable letter template (قالب) managed in the template builder.
 */
final class Template
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
        public readonly PaperSize $paperSize,
        public readonly ?int $departmentId = null,
        public readonly ?int $createdBy = null,
        public readonly ?string $category = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['body'],
            PaperSize::from((string) ($row['paper_size'] ?? 'a4')),
            isset($row['department_id']) && $row['department_id'] !== null ? (int) $row['department_id'] : null,
            isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            isset($row['category']) && $row['category'] !== null && $row['category'] !== '' ? (string) $row['category'] : null,
        );
    }
}
