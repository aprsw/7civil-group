<?php

declare(strict_types=1);

namespace ArtaPharma\View;

use ArtaPharma\Domain\Entities\Role;
use ArtaPharma\Domain\Entities\Workflow;

final class WorkflowTransformer
{
    /**
     * @param list<Role> $roles used to resolve a readable label per step
     * @return array<string, mixed>
     */
    public function transform(Workflow $workflow, array $roles = []): array
    {
        $roleById = [];
        foreach ($roles as $role) {
            $roleById[$role->id] = $role;
        }

        $steps = array_map(static function ($step) use ($roleById): array {
            $role = $step->roleId !== null ? ($roleById[$step->roleId] ?? null) : null;

            return [
                'position' => $step->position,
                'label' => $step->label,
                'role_id' => $step->roleId,
                'role_label' => $role !== null ? $role->name : 'دبیرخانه / سیستم',
            ];
        }, $workflow->steps);

        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'department_id' => $workflow->departmentId,
            'created_by' => $workflow->createdBy,
            'steps' => $steps,
            'approver_ids' => $workflow->approverIds,
            'predefined' => $workflow->approverIds !== [],
        ];
    }

    /**
     * @param list<Workflow> $workflows
     * @param list<Role>     $roles
     * @return list<array<string, mixed>>
     */
    public function collection(array $workflows, array $roles = []): array
    {
        return array_map(fn ($w) => $this->transform($w, $roles), $workflows);
    }
}
