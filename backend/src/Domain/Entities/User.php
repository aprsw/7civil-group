<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Entities;

/**
 * A staff member. Belongs to exactly one role (position/tier) and one
 * department, and carries an optional HR profile.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly int $roleId,
        public readonly int $departmentId,
        public readonly string $email = '',
        public readonly string $avatarColor = 'brand',
        public readonly ?Role $role = null,
        public readonly ?Department $department = null,
        public readonly string $birthDate = '',
        public readonly string $address = '',
        public readonly string $mobile = '',
        public readonly string $phone = '',
        public readonly string $description = '',
        public readonly ?string $photoPath = null,
        public readonly ?string $signaturePath = null,
        public readonly bool $active = true,
        public readonly ?string $lastSeenAt = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row, ?Role $role = null, ?Department $department = null): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['username'],
            (string) ($row['password_hash'] ?? ''),
            (int) $row['role_id'],
            (int) $row['department_id'],
            (string) ($row['email'] ?? ''),
            (string) ($row['avatar_color'] ?? 'brand'),
            $role,
            $department,
            (string) ($row['birth_date'] ?? ''),
            (string) ($row['address'] ?? ''),
            (string) ($row['mobile'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['description'] ?? ''),
            isset($row['photo_path']) ? ($row['photo_path'] ?: null) : null,
            isset($row['signature_path']) ? ($row['signature_path'] ?: null) : null,
            (int) ($row['active'] ?? 1) === 1,
            isset($row['last_seen_at']) ? ($row['last_seen_at'] ?: null) : null,
        );
    }

    public function withRelations(?Role $role, ?Department $department): self
    {
        return new self(
            $this->id, $this->name, $this->username, $this->passwordHash, $this->roleId,
            $this->departmentId, $this->email, $this->avatarColor, $role, $department,
            $this->birthDate, $this->address, $this->mobile, $this->phone, $this->description,
            $this->photoPath, $this->signaturePath, $this->active, $this->lastSeenAt,
        );
    }

    /** Tier in the org hierarchy (1 = مدیر عامل … 5 = کارشناس). */
    public function tier(): int
    {
        return $this->role?->level ?? 99;
    }

    public function isOnline(): bool
    {
        if ($this->lastSeenAt === null) {
            return false;
        }

        return (time() - strtotime($this->lastSeenAt)) < 300; // seen within 5 min
    }
}
