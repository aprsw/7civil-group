<?php

declare(strict_types=1);

namespace ArtaPharma\Services;

use ArtaPharma\Domain\Entities\Letter;
use ArtaPharma\Domain\Enums\LetterDirection;
use ArtaPharma\Domain\Enums\LetterStatus;
use ArtaPharma\Support\Jalali;

/**
 * Aggregates dashboard KPIs from the letters the current user may see. The
 * "در انتظار اقدام" figure is the user's own to-do count (their workflow turns).
 */
final class DashboardService
{
    /**
     * @param list<Letter> $visible letters the viewer is allowed to see
     * @param list<array<string,mixed>> $workflowBars prebuilt progress bars (per-workflow percentages)
     * @return array{stats: list<array<string,mixed>>, workflows: list<array<string,mixed>>}
     */
    public function summary(array $visible, int $myTurnCount, array $workflowBars = []): array
    {
        $outgoing = array_filter($visible, static fn ($l) => $l->direction === LetterDirection::Outgoing);
        $incoming = array_values(array_filter($visible, static fn ($l) => $l->direction === LetterDirection::Incoming));

        $nearDeadline = count(array_filter(
            $visible,
            static fn ($l) => $l->priority->value === 'urgent'
                && in_array($l->status, [LetterStatus::Pending, LetterStatus::Reviewing], true),
        ));
        $completed = count(array_filter(
            $visible,
            static fn ($l) => in_array($l->status, [LetterStatus::Approved, LetterStatus::Sent, LetterStatus::Archived], true),
        ));
        $onTime = count($visible) > 0 ? (int) round($completed / count($visible) * 100) : 0;

        $stats = [
            ['key' => 'mine', 'tone' => 'teal', 'icon' => 'inbox', 'value' => $this->fa($myTurnCount), 'label' => 'در انتظار اقدام من', 'delta' => '', 'trend' => 'flat'],
            ['key' => 'out', 'tone' => 'brand', 'icon' => 'send', 'value' => $this->fa(count($outgoing)), 'label' => 'نامهٔ صادره', 'delta' => '', 'trend' => 'up'],
            ['key' => 'urgent', 'tone' => 'gold', 'icon' => 'clock', 'value' => $this->fa($nearDeadline), 'label' => 'فوری و باز', 'delta' => '', 'trend' => 'down'],
            ['key' => 'done', 'tone' => 'ink', 'icon' => 'check', 'value' => $this->fa($onTime) . '٪', 'label' => 'نرخ تکمیل', 'delta' => '', 'trend' => 'flat'],
        ];

        return ['stats' => $stats, 'workflows' => $workflowBars];
    }

    private function fa(int $n): string
    {
        return Jalali::toPersianDigits((string) $n);
    }
}
