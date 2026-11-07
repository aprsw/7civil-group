<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Entities;

final class LetterAttachment
{
    public function __construct(
        public readonly int $id,
        public readonly int $letterId,
        public readonly string $name,
        public readonly string $path,
        public readonly ?string $mime,
        public readonly ?int $size,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['letter_id'],
            (string) $row['name'],
            (string) $row['path'],
            isset($row['mime']) ? (string) $row['mime'] : null,
            isset($row['size']) && $row['size'] !== null ? (int) $row['size'] : null,
            (string) ($row['created_at'] ?? ''),
        );
    }
}
