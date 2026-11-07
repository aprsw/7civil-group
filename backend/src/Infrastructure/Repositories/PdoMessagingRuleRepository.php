<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Repositories\MessagingRuleRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoMessagingRuleRepository implements MessagingRuleRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<int,list<int>> */
    public function matrix(): array
    {
        $out = [];
        foreach ($this->pdo()->query('SELECT from_department_id, to_department_id FROM messaging_rules')->fetchAll() as $row) {
            $from = (int) $row['from_department_id'];
            $out[$from][] = (int) $row['to_department_id'];
        }

        return $out;
    }

    public function setForDepartment(int $fromId, array $toIds): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM messaging_rules WHERE from_department_id = ?')->execute([$fromId]);
            $stmt = $pdo->prepare('INSERT INTO messaging_rules (from_department_id, to_department_id) VALUES (?, ?)');
            foreach (array_values(array_unique(array_map('intval', $toIds))) as $to) {
                if ($to > 0) {
                    $stmt->execute([$fromId, $to]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int,list<int>> */
    public function roleMatrix(): array
    {
        $out = [];
        foreach ($this->pdo()->query('SELECT from_role_id, to_role_id FROM role_messaging_rules')->fetchAll() as $row) {
            $out[(int) $row['from_role_id']][] = (int) $row['to_role_id'];
        }

        return $out;
    }

    public function setForRole(int $fromRoleId, array $toRoleIds): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM role_messaging_rules WHERE from_role_id = ?')->execute([$fromRoleId]);
            $stmt = $pdo->prepare('INSERT INTO role_messaging_rules (from_role_id, to_role_id) VALUES (?, ?)');
            foreach (array_values(array_unique(array_map('intval', $toRoleIds))) as $to) {
                if ($to > 0) {
                    $stmt->execute([$fromRoleId, $to]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
