<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Entities;

/**
 * A single step in a department workflow. `roleId === null` means the step is
 * handled by the registry / system (دبیرخانه / سیستم).
 */
final class WorkflowStep
{
    public function __construct(
        public readonly int $position,
        public readonly string $label,
        public readonly ?int $roleId = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row, int $position): self
    {
        $roleId = $row['role_id'] ?? $row['roleId'] ?? null;

        return new self(
            $position,
            (string) ($row['label'] ?? 'مرحله'),
            $roleId === null ? null : (int) $roleId,
        );
    }

    /** @return array{position:int,label:string,role_id:?int} */
    public function toArray(): array
    {
        return ['position' => $this->position, 'label' => $this->label, 'role_id' => $this->roleId];
    }
}
