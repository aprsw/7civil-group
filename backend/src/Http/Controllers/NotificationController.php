<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Repositories\LetterRepositoryInterface;
use SevenCivilGroup\Services\LetterAccess;
use SevenCivilGroup\Services\LetterService;
use SevenCivilGroup\Support\Jalali;

/**
 * Real notifications = letters awaiting *this user's* action (their current
 * workflow step). Powers the header bell.
 */
final class NotificationController extends Controller
{
    public function __construct(
        private readonly LetterRepositoryInterface $letters,
        private readonly LetterAccess $visibility,
        private readonly LetterService $service,
    ) {
    }

    /** GET /api/notifications */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $visible = $this->visibility->filter($user, $this->letters->all());

        $items = [];
        foreach ($visible as $l) {
            // Notifications cover letters addressed to me (received).
            if (!$this->service->isRecipientOf($user, $l)) {
                continue;
            }
            $read = $this->service->isReadByUser($user, $l);
            $myTurn = $this->service->isMyTurn($user, $l);
            $j = Jalali::fromGregorian($l->createdAt);
            $items[] = [
                'id' => $l->id,
                'letter_id' => $l->id,
                'title' => $myTurn ? 'در انتظار اقدام شما' : ($read ? 'نامهٔ شما' : 'نامهٔ جدید'),
                'subject' => $l->subject,
                'from' => $l->fromLabel,
                'is_read' => $read,
                'is_my_turn' => $myTurn,
                'tone' => $l->priority->tone(),
                'date' => $j->format('Y/m/d') . ' — ' . $j->format('H:i'),
                'ts' => $l->createdAt,
            ];
        }
        // Unread first, then newest.
        usort($items, static fn ($a, $b) => ($a['is_read'] <=> $b['is_read']) ?: strcmp($b['ts'], $a['ts']));
        $unread = count(array_filter($items, static fn ($i) => !$i['is_read']));

        return Response::ok(['count' => $unread, 'items' => $items]);
    }
}
