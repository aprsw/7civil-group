<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Entities;

/**
 * An organisation-wide event (رویداد) — a dated announcement/happening
 * managed by users holding events.manage.
 */
final class Event
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $eventDate, // Jalali date string as entered (e.g. ۱۴۰۵/۰۴/۱۵)
        public readonly string $location,
        public readonly ?int $createdBy,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            (string) ($row['description'] ?? ''),
            (string) ($row['event_date'] ?? ''),
            (string) ($row['location'] ?? ''),
            isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            (string) ($row['created_at'] ?? ''),
        );
    }
}
