<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Entities;

/**
 * Organisational unit (واحد / دپارتمان).
 */
final class Department
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string $description = '',
        public readonly ?int $managerId = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['code'],
            (string) ($row['description'] ?? ''),
            isset($row['manager_id']) ? (int) $row['manager_id'] : null,
        );
    }
}
