<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Entities;

/**
 * A department workflow — the ordered chain a manager arranges in the builder.
 */
final class Workflow
{
    /**
     * @param list<WorkflowStep> $steps
     * @param list<int> $approverIds ordered, admin-defined fixed approver chain
     *                               (empty ⇒ the sender defines the chain)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $departmentId,
        public readonly array $steps = [],
        public readonly ?int $createdBy = null,
        public readonly string $description = '',
        public readonly array $approverIds = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $rawSteps = $row['steps'] ?? '[]';
        if (is_string($rawSteps)) {
            $rawSteps = json_decode($rawSteps, true) ?: [];
        }

        $steps = [];
        foreach (array_values($rawSteps) as $i => $step) {
            $steps[] = WorkflowStep::fromArray($step, $i);
        }

        $rawApprovers = $row['approver_ids'] ?? '[]';
        if (is_string($rawApprovers)) {
            $rawApprovers = json_decode($rawApprovers, true) ?: [];
        }
        $approverIds = array_values(array_filter(array_map('intval', (array) $rawApprovers)));

        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (int) $row['department_id'],
            $steps,
            isset($row['created_by']) ? (int) $row['created_by'] : null,
            (string) ($row['description'] ?? ''),
            $approverIds,
        );
    }
}
