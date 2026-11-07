<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\LetterAction;
use SevenCivilGroup\Domain\Enums\LetterActionType;

interface LetterActionRepositoryInterface
{
    public function record(
        int $letterId,
        ?int $userId,
        LetterActionType $action,
        ?int $fromStage,
        ?int $toStage,
        string $note = '',
    ): LetterAction;

    /** @return list<LetterAction> chronological */
    public function forLetter(int $letterId): array;
}
