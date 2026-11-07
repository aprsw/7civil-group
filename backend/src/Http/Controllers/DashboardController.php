<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Enums\LetterDirection;
use SevenCivilGroup\Domain\Repositories\EventRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterRepositoryInterface;
use SevenCivilGroup\Services\DashboardService;
use SevenCivilGroup\Services\LetterAccess;
use SevenCivilGroup\Services\LetterService;
use SevenCivilGroup\Support\Jalali;
use SevenCivilGroup\View\LetterTransformer;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly LetterRepositoryInterface $letters,
        private readonly LetterTransformer $transformer,
        private readonly LetterAccess $visibility,
        private readonly LetterService $service,
        private readonly EventRepositoryInterface $events,
        private readonly \SevenCivilGroup\Domain\Repositories\UserNoteRepositoryInterface $userNotes,
    ) {
    }

    /** GET /api/dashboard */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $visible = $this->visibility->filter($user, $this->letters->all());
        $myTurn = array_values(array_filter($visible, fn ($l) => $this->service->isMyTurn($user, $l)));

        // Progress bars use each letter's OWN workflow step count (not a fixed divisor).
        $tones = ['brand', 'teal', 'gold'];
        $active = array_values(array_filter($visible, static fn ($l) => $l->direction === LetterDirection::Incoming));
        $workflowBars = [];
        foreach (array_slice($active, 0, 3) as $i => $letter) {
            $pct = $this->service->progressPercent($letter);
            $workflowBars[] = [
                'name' => $letter->subject,
                'pct' => $pct,
                'pctLabel' => Jalali::toPersianDigits((string) $pct) . '٪',
                'tone' => $tones[$i] ?? 'brand',
            ];
        }

        $summary = $this->dashboard->summary($visible, count($myTurn), $workflowBars);

        // Requested KPI set: وارده (received) / صادره (sent) / مشاهده‌نشده / منتظر اقدام.
        $received = array_values(array_filter($visible, fn ($l) => $this->service->isRecipientOf($user, $l)));
        $sent = array_values(array_filter($visible, fn ($l) => $l->senderId === $user->id && $l->status->value !== 'draft'));
        $unseen = array_values(array_filter($received, fn ($l) => !$this->service->isReadByUser($user, $l)));
        $fa = static fn ($n) => Jalali::toPersianDigits((string) $n);
        $summary['stats'] = [
            ['key' => 'in', 'tone' => 'brand', 'icon' => 'inbox', 'value' => $fa(count($received)), 'label' => 'نامه‌های وارده', 'delta' => '', 'trend' => 'flat'],
            ['key' => 'out', 'tone' => 'teal', 'icon' => 'send', 'value' => $fa(count($sent)), 'label' => 'نامه‌های صادره', 'delta' => '', 'trend' => 'flat'],
            ['key' => 'unseen', 'tone' => 'gold', 'icon' => 'inbox', 'value' => $fa(count($unseen)), 'label' => 'مشاهده‌نشده', 'delta' => '', 'trend' => 'flat'],
            ['key' => 'mine', 'tone' => 'ink', 'icon' => 'clock', 'value' => $fa(count($myTurn)), 'label' => 'در انتظار اقدام', 'delta' => '', 'trend' => 'flat'],
        ];

        // Recent = the user's own to-do items first, then the NEWEST visible
        // letters (highest id first) — «چهار نامهٔ آخر».
        $newestFirst = $visible;
        usort($newestFirst, static fn ($a, $b) => $b->id <=> $a->id);
        $recent = $myTurn;
        foreach ($newestFirst as $l) {
            if (count($recent) >= 4) {
                break;
            }
            if (!in_array($l, $recent, true)) {
                $recent[] = $l;
            }
        }
        $recent = array_slice($recent, 0, 4);

        // Today's events (رویدادهای امروز) — match the current Jalali date.
        $today = Jalali::fromGregorian(date('Y-m-d H:i:s'))->format('Y/m/d');
        $todayEvents = array_values(array_map(
            static fn ($e) => ['id' => $e->id, 'title' => $e->title, 'location' => $e->location, 'event_date' => $e->eventDate],
            array_filter($this->events->all(), static fn ($e) => trim($e->eventDate) === $today),
        ));

        // This week's events (رویدادهای هفته) — today + the next 6 days, each
        // tagged with its Jalali weekday so the dashboard can group by day.
        $weekMap = [];
        for ($i = 0; $i < 7; $i++) {
            $j = Jalali::fromGregorian(date('Y-m-d H:i:s', strtotime("+{$i} day")));
            $weekMap[$j->format('Y/m/d')] = $j->format('l'); // e.g. شنبه
        }
        $weekEvents = array_values(array_map(
            static fn ($e) => [
                'id' => $e->id, 'title' => $e->title, 'location' => $e->location,
                'event_date' => $e->eventDate, 'weekday' => $weekMap[trim($e->eventDate)] ?? '',
                'is_today' => trim($e->eventDate) === $today,
            ],
            array_filter($this->events->all(), static fn ($e) => isset($weekMap[trim($e->eventDate)])),
        ));

        // Personal reminders due now — defensive: a partial/older schema (e.g.
        // a missing user_notes table) must never break the whole dashboard.
        $myReminders = [];
        try {
            $myReminders = array_values(array_filter(
                $this->userNotes->forUser($user->id),
                static fn ($n) => $n['is_due'] ?? false,
            ));
        } catch (\Throwable $e) {
            $myReminders = [];
        }

        return Response::ok([
            'greeting_name' => explode(' ', $user->name)[0] ?? $user->name,
            'stats' => $summary['stats'],
            'workflows' => $summary['workflows'],
            'recent' => $this->transformer->collection($recent),
            'today_events' => $todayEvents,
            'week_events' => $weekEvents,
            'my_reminders' => $myReminders,
            'today' => $today,
        ]);
    }
}
