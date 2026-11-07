<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\ForbiddenException;
use ArtaPharma\Core\Exceptions\NotFoundException;
use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\ChatRepositoryInterface;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Services\AccessControl;
use ArtaPharma\Support\Jalali;

/**
 * Staff chat (گفتگو). Direct 1:1 conversations for any chat.use holder; groups
 * created/removed by the senior admin. Messages and files older than
 * RETENTION_DAYS are purged automatically (lazily, on access).
 */
final class ChatController extends Controller
{
    private const RETENTION_DAYS = 60; // ~۲ ماه

    public function __construct(
        private readonly ChatRepositoryInterface $chat,
        private readonly UserRepositoryInterface $users,
        private readonly AccessControl $access,
        private readonly string $uploadDir,
    ) {
    }

    /** GET /api/chat/contacts — people the user can start a chat with. */
    public function contacts(Request $request): Response
    {
        $me = $this->gate($request);
        $list = array_values(array_filter(
            $this->users->all(),
            static fn ($u) => $u->id !== $me->id && $u->active,
        ));

        return Response::ok([
            'contacts' => array_map(static fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'role' => $u->role?->name, 'department' => $u->department?->name,
            ], $list),
            'can_create_group' => $this->access->can($me, Permission::DepartmentsView),
            'retention_days' => self::RETENTION_DAYS,
        ]);
    }

    /** GET /api/chat/conversations */
    public function index(Request $request): Response
    {
        $me = $this->gate($request);
        $this->purge();

        $items = array_map(function ($c) use ($me) {
            $last = $this->chat->lastMessage((int) $c['id']);

            return $this->present($c, $me->id) + [
                'last_message' => $last ? $this->trim((string) ($last['body'] ?? ''), (bool) ($last['file_path'] ?? false)) : null,
                'last_at' => $last ? Jalali::fromGregorian((string) $last['created_at'])->format('Y/m/d — H:i') : null,
            ];
        }, $this->chat->forUser($me->id));

        return Response::ok([
            'items' => $items,
            'can_create_group' => $this->access->can($me, Permission::DepartmentsView),
            'retention_days' => self::RETENTION_DAYS,
        ]);
    }

    /** POST /api/chat/conversations — direct (user_id) or group (title + member_ids). */
    public function store(Request $request): Response
    {
        $me = $this->gate($request);
        $type = $request->input('type') === 'group' ? 'group' : 'direct';

        if ($type === 'group') {
            if (!$this->access->can($me, Permission::DepartmentsView)) {
                throw new ForbiddenException('ایجاد گروه فقط توسط کاربر ارشد مجاز است.');
            }
            $title = trim((string) $request->input('title', ''));
            if ($title === '') {
                throw new ValidationException(['title' => 'نام گروه الزامی است.']);
            }
            $members = $this->validMembers((array) $request->input('member_ids', []));
            $members[] = $me->id;
            $cid = $this->chat->create('group', $title, $me->id, $members);

            return Response::created($this->present($this->chat->find($cid), $me->id), 'گروه ایجاد شد.');
        }

        $targetId = (int) $request->input('user_id', 0);
        $target = $this->users->find($targetId);
        if ($target === null || $targetId === $me->id) {
            throw new ValidationException(['user_id' => 'مخاطب معتبر انتخاب کنید.']);
        }
        $existing = $this->chat->findDirect($me->id, $targetId);
        $cid = $existing ?? $this->chat->create('direct', null, $me->id, [$me->id, $targetId]);

        return Response::ok($this->present($this->chat->find($cid), $me->id), 'گفتگو آماده است.');
    }

    /** DELETE /api/chat/conversations/{id} */
    public function destroy(Request $request): Response
    {
        $me = $this->gate($request);
        $id = (int) $request->attribute('id');
        $conv = $this->chat->find($id) ?? throw new NotFoundException('گفتگو یافت نشد.');
        $isAdmin = $this->access->can($me, Permission::DepartmentsView);
        $isCreator = (int) ($conv['created_by'] ?? 0) === $me->id;
        if (($conv['type'] === 'group' && !$isAdmin && !$isCreator)
            || ($conv['type'] === 'direct' && !$this->chat->isMember($id, $me->id) && !$isAdmin)) {
            throw new ForbiddenException('اجازهٔ حذف این گفتگو را ندارید.');
        }
        $this->chat->delete($id);

        return Response::ok(null, 'گفتگو حذف شد.');
    }

    /** GET /api/chat/conversations/{id}/messages */
    public function messages(Request $request): Response
    {
        $me = $this->gate($request);
        $this->purge();
        $id = (int) $request->attribute('id');
        $this->requireMember($id, $me->id);

        $msgs = array_map(function ($m) use ($me) {
            $j = Jalali::fromGregorian((string) $m['created_at']);

            return [
                'id' => (int) $m['id'],
                'user_id' => (int) $m['user_id'],
                'user_name' => $this->users->find((int) $m['user_id'])?->name ?? 'کاربر',
                'is_mine' => (int) $m['user_id'] === $me->id,
                'body' => (string) ($m['body'] ?? ''),
                'file_name' => $m['file_name'] ?? null,
                'has_file' => !empty($m['file_path']),
                'date' => $j->format('Y/m/d — H:i'),
            ];
        }, $this->chat->messages($id));

        return Response::ok(['messages' => $msgs, 'retention_days' => self::RETENTION_DAYS]);
    }

    /** POST /api/chat/conversations/{id}/messages */
    public function send(Request $request): Response
    {
        $me = $this->gate($request);
        $id = (int) $request->attribute('id');
        $this->requireMember($id, $me->id);

        $body = trim((string) $request->input('body', ''));
        $filePath = trim((string) $request->input('file_path', ''));
        if ($body === '' && $filePath === '') {
            throw new ValidationException(['body' => 'پیام خالی است.']);
        }
        $mid = $this->chat->addMessage($id, $me->id, [
            'body' => $body,
            'file_path' => $filePath !== '' ? $filePath : null,
            'file_name' => $request->input('file_name'),
            'file_mime' => $request->input('file_mime'),
            'file_size' => $request->input('file_size') !== null ? (int) $request->input('file_size') : null,
        ]);

        return Response::created(['id' => $mid], 'ارسال شد.');
    }

    /** GET /api/chat/messages/{id}/download */
    public function download(Request $request): Response
    {
        $me = $this->gate($request);
        $mid = (int) $request->attribute('id');
        // Find the message across the user's conversations.
        foreach ($this->chat->forUser($me->id) as $conv) {
            foreach ($this->chat->messages((int) $conv['id']) as $m) {
                if ((int) $m['id'] === $mid && !empty($m['file_path'])) {
                    $path = $this->uploadDir . DIRECTORY_SEPARATOR . basename((string) $m['file_path']);
                    if (!is_readable($path)) {
                        throw new NotFoundException('فایل یافت نشد.');
                    }

                    return Response::file($path, (string) ($m['file_name'] ?? 'file'), (string) ($m['file_mime'] ?? 'application/octet-stream'));
                }
            }
        }
        throw new NotFoundException('پیوست یافت نشد.');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function gate(Request $request): \ArtaPharma\Domain\Entities\User
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::ChatUse);

        return $me;
    }

    private function requireMember(int $conversationId, int $userId): void
    {
        if (!$this->chat->isMember($conversationId, $userId)) {
            throw new ForbiddenException('شما عضو این گفتگو نیستید.');
        }
    }

    /** @param array<int,mixed> $ids @return list<int> */
    private function validMembers(array $ids): array
    {
        $out = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id > 0 && $this->users->find($id) !== null) {
                $out[] = $id;
            }
        }
        if ($out === []) {
            throw new ValidationException(['member_ids' => 'حداقل یک عضو برای گروه انتخاب کنید.']);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $conv
     * @return array<string,mixed>
     */
    private function present(array $conv, int $meId): array
    {
        $isGroup = ($conv['type'] ?? 'direct') === 'group';
        $memberIds = $this->chat->members((int) $conv['id']);
        if ($isGroup) {
            $title = (string) ($conv['title'] ?? 'گروه');
        } else {
            $otherId = array_values(array_filter($memberIds, static fn ($x) => $x !== $meId))[0] ?? $meId;
            $title = $this->users->find($otherId)?->name ?? 'گفتگو';
        }

        return [
            'id' => (int) $conv['id'],
            'type' => $conv['type'] ?? 'direct',
            'is_group' => $isGroup,
            'title' => $title,
            'members' => array_map(fn ($id) => $this->users->find($id)?->name ?? 'کاربر', $memberIds),
            'member_count' => count($memberIds),
        ];
    }

    private function trim(string $body, bool $hasFile): string
    {
        if ($body === '' && $hasFile) {
            return '📎 پیوست';
        }

        return mb_strlen($body) > 42 ? mb_substr($body, 0, 42) . '…' : $body;
    }

    private function purge(): void
    {
        foreach ($this->chat->purgeOlderThan(self::RETENTION_DAYS) as $path) {
            $full = $this->uploadDir . DIRECTORY_SEPARATOR . basename($path);
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }
}
