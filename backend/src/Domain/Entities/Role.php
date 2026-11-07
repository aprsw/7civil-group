<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Entities;

use SevenCivilGroup\Domain\Enums\Permission;

/**
 * A role carries an access level (1 = highest authority, e.g. مدیر عامل) and an
 * explicit set of permissions (RBAC). Access is checked either by permission
 * or by comparing levels.
 */
final class Role
{
    /** @param list<string> $permissions */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly int $level,
        public readonly array $permissions = [],
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $permissions = $row['permissions'] ?? '[]';
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?: [];
        }

        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            (int) $row['level'],
            array_values(array_map('strval', $permissions)),
        );
    }

    public function has(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissions, true);
    }

    /** Whether this role outranks or equals the given level (lower number = higher rank). */
    public function outranks(int $level): bool
    {
        return $this->level <= $level;
    }
}
