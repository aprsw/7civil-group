<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

interface UserPermissionRepositoryInterface
{
    /** @return list<string> permission strings granted directly to the user */
    public function forUser(int $userId): array;

    /** @param list<string> $permissions replace the user's direct grants */
    public function setForUser(int $userId, array $permissions): void;

    /** @return array<int,list<string>> userId ⇒ granted permissions */
    public function grantsMap(): array;
}
