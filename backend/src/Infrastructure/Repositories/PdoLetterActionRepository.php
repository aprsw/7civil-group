<?php

declare(strict_types=1);

namespace SevenCivilGroup\Infrastructure\Repositories;

use SevenCivilGroup\Domain\Entities\LetterAction;
use SevenCivilGroup\Domain\Enums\LetterActionType;
use SevenCivilGroup\Domain\Repositories\LetterActionRepositoryInterface;
use SevenCivilGroup\Infrastructure\Database\Connection;
use PDO;

final class PdoLetterActionRepository implements LetterActionRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(
        int $letterId,
        ?int $userId,
        LetterActionType $action,
        ?int $fromStage,
        ?int $toStage,
        string $note = '',
    ): LetterAction {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO letter_actions (letter_id, user_id, action, from_stage, to_stage, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$letterId, $userId, $action->value, $fromStage, $toStage, $note, date('Y-m-d H:i:s')]);

        $id = (int) $this->pdo()->lastInsertId();
        $find = $this->pdo()->prepare('SELECT * FROM letter_actions WHERE id = ?');
        $find->execute([$id]);

        return LetterAction::fromRow($find->fetch());
    }

    /** @return list<LetterAction> */
    public function forLetter(int $letterId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM letter_actions WHERE letter_id = ? ORDER BY id ASC');
        $stmt->execute([$letterId]);

        return array_map(LetterAction::fromRow(...), $stmt->fetchAll());
    }

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}
