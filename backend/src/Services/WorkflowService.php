<?php

declare(strict_types=1);

namespace ArtaPharma\Services;

use ArtaPharma\Core\Exceptions\NotFoundException;
use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Domain\Entities\User;
use ArtaPharma\Domain\Entities\Workflow;
use ArtaPharma\Domain\Entities\WorkflowStep;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\RoleRepositoryInterface;
use ArtaPharma\Domain\Repositories\WorkflowRepositoryInterface;

/**
 * Workflow builder use-cases. Only department managers (WorkflowManage) may
 * arrange the chain; the service enforces that and validates the step list.
 */
final class WorkflowService
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly AccessControl $access,
        private readonly RoleRepositoryInterface $roles,
    ) {
    }

    /**
     * Sanity-check a chain against the org chart. Returns human-readable
     * warnings (non-blocking) so misconfigurations surface before use.
     *
     * @param list<WorkflowStep> $steps
     * @return list<string>
     */
    public function validateChart(array $steps): array
    {
        $warnings = [];
        if (count($steps) < 2) {
            $warnings[] = 'گردش کار بهتر است حداقل دو مرحله داشته باشد (مثلاً تنظیم و تأیید).';
        }
        $prevRole = -999;
        foreach ($steps as $s) {
            if ($s->roleId !== null) {
                $role = $this->roles->find($s->roleId);
                if ($role === null) {
                    $warnings[] = 'مرحلهٔ «' . $s->label . '»: نقش انتخاب‌شده یافت نشد.';
                } elseif (!$role->has(Permission::LettersApprove) && !$role->has(Permission::LettersSign)) {
                    $warnings[] = 'مرحلهٔ «' . $s->label . '» به نقش «' . $role->name . '» سپرده شده که مجوز «تأیید نامه» ندارد؛ این نقش نمی‌تواند در این مرحله اقدام کند.';
                }
                if ($s->roleId === $prevRole) {
                    $warnings[] = 'دو مرحلهٔ پیاپی به یک نقش سپرده شده‌اند؛ ممکن است غیرضروری باشد.';
                }
            }
            $prevRole = $s->roleId ?? -999;
        }

        return $warnings;
    }

    public function delete(int $id, User $manager): void
    {
        $this->access->authorize($manager, Permission::WorkflowManage);
        $wf = $this->workflows->find($id) ?? throw new NotFoundException('گردش کار یافت نشد.');
        $isExecutive = $manager->role?->has(Permission::DepartmentsView) ?? false;
        if (!$isExecutive && $wf->departmentId !== $manager->departmentId) {
            throw new ValidationException(['id' => 'فقط می‌توانید گردش کار واحد خودتان را حذف کنید.']);
        }
        $this->workflows->delete($id);
    }

    /**
     * Create or update a workflow arrangement.
     *
     * @param array<string, mixed> $data
     */
    public function save(?int $id, array $data, User $manager): Workflow
    {
        $this->access->authorize($manager, Permission::WorkflowManage);

        $isExecutive = $manager->role?->has(Permission::DepartmentsView) ?? false;

        // A department manager may only touch their own department's workflows;
        // executives (مدیر عامل) may edit any.
        if ($id !== null) {
            $existing = $this->workflows->find($id) ?? throw new NotFoundException('گردش کار یافت نشد.');
            if (!$isExecutive && $existing->departmentId !== $manager->departmentId) {
                throw new ValidationException(['id' => 'فقط می‌توانید گردش کار واحد خودتان را ویرایش کنید.']);
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'عنوان گردش کار الزامی است.']);
        }

        // Workflows are now just a name + description (the per-letter approver
        // chain defines routing). Optional legacy steps are still accepted.
        $description = trim((string) ($data['description'] ?? ''));
        $steps = [];
        foreach (array_values((array) ($data['steps'] ?? [])) as $i => $step) {
            $label = trim((string) ($step['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $roleId = $step['role_id'] ?? null;
            $steps[] = new WorkflowStep($i, $label, $roleId === null ? null : (int) $roleId);
        }

        // Non-executives can only assign the workflow to their own department.
        $departmentId = $isExecutive
            ? (int) ($data['department_id'] ?? $manager->departmentId)
            : $manager->departmentId;

        // Optional admin-defined fixed approver chain (specific users, in order).
        // When present, every letter using this workflow follows exactly this
        // chain regardless of who sends it.
        $approverIds = array_values(array_filter(
            array_map('intval', (array) ($data['approver_ids'] ?? [])),
            static fn (int $uid) => $uid > 0,
        ));

        $workflow = new Workflow($id ?? 0, $name, $departmentId, $steps, $manager->id, $description, $approverIds);

        return $this->workflows->save($workflow);
    }

    public function find(int $id): Workflow
    {
        return $this->workflows->find($id) ?? throw new NotFoundException('گردش کار یافت نشد.');
    }
}
