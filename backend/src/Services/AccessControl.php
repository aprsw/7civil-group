<?php

declare(strict_types=1);

namespace SevenCivilGroup\Services;

use SevenCivilGroup\Core\Exceptions\ForbiddenException;
use SevenCivilGroup\Domain\Entities\User;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\UserPermissionRepositoryInterface;

/**
 * Central RBAC decision point. Controllers ask this service whether the
 * authenticated user may perform an action (Single Responsibility: authorization
 * lives here, not scattered across controllers).
 *
 * A user's capabilities come from their role, plus any per-user grants the admin
 * assigned directly (e.g. giving دبیرخانه access to a specific person).
 */
final class AccessControl
{
    /** @var array<int,list<string>>|null cached per-user grants for the request */
    private ?array $grants = null;

    public function __construct(
        private readonly ?UserPermissionRepositoryInterface $userPermissions = null,
    ) {
    }

    public function can(User $user, Permission $permission): bool
    {
        if ($user->role?->has($permission) ?? false) {
            return true;
        }

        return in_array($permission->value, $this->grantsFor($user->id), true);
    }

    /** @return list<string> */
    private function grantsFor(int $userId): array
    {
        if ($this->userPermissions === null) {
            return [];
        }
        if ($this->grants === null) {
            $this->grants = $this->userPermissions->grantsMap();
        }

        return $this->grants[$userId] ?? [];
    }

    public function authorize(User $user, Permission $permission): void
    {
        if (!$this->can($user, $permission)) {
            throw new ForbiddenException(
                'برای «' . $permission->label() . '» سطح دسترسی لازم را ندارید.'
            );
        }
    }

    /** True when the user's role level is at or above (<=) the given level. */
    public function hasLevel(User $user, int $level): bool
    {
        return $user->role?->outranks($level) ?? false;
    }
}
