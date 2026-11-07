<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

interface MessagingRuleRepositoryInterface
{
    /**
     * The whole matrix: from_department_id => list<to_department_id>.
     *
     * @return array<int,list<int>>
     */
    public function matrix(): array;

    /** Replace the allowed target units for one source unit. @param list<int> $toIds */
    public function setForDepartment(int $fromId, array $toIds): void;

    /**
     * Role-to-role matrix: from_role_id => list<to_role_id>.
     *
     * @return array<int,list<int>>
     */
    public function roleMatrix(): array;

    /** Replace the allowed target roles for one source role. @param list<int> $toRoleIds */
    public function setForRole(int $fromRoleId, array $toRoleIds): void;
}
