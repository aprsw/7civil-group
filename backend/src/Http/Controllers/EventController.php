<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\NotFoundException;
use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Entities\Event;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\EventRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\UserRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;

/**
 * Organisation-wide events (رویدادها). Visible to everyone authenticated;
 * create / edit / delete require events.manage.
 */
final class EventController extends Controller
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly UserRepositoryInterface $users,
        private readonly AccessControl $access,
    ) {
    }

    /** GET /api/events */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $canManage = $this->access->can($user, Permission::EventsManage);

        return Response::ok([
            'items' => array_map($this->present(...), $this->events->all()),
            'can_manage' => $canManage,
        ]);
    }

    /** POST /api/events */
    public function store(Request $request): Response
    {
        $user = $this->user($request);
        $this->access->authorize($user, Permission::EventsManage);
        $event = $this->events->create($this->validated($request) + ['created_by' => $user->id]);

        return Response::created($this->present($event), 'رویداد ثبت شد.');
    }

    /** PUT /api/events/{id} */
    public function update(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::EventsManage);
        $id = (int) $request->attribute('id');
        $this->events->find($id) ?? throw new NotFoundException('رویداد یافت نشد.');
        $event = $this->events->update($id, $this->validated($request));

        return Response::ok($this->present($event), 'رویداد به‌روزرسانی شد.');
    }

    /** DELETE /api/events/{id} */
    public function destroy(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::EventsManage);
        $this->events->delete((int) $request->attribute('id'));

        return Response::ok(null, 'رویداد حذف شد.');
    }

    /** @return array{title:string,description:string,event_date:string,location:string} */
    private function validated(Request $request): array
    {
        $errors = [];
        $title = trim((string) $request->input('title', ''));
        $date = trim((string) $request->input('event_date', ''));
        if ($title === '') {
            $errors['title'] = 'عنوان رویداد الزامی است.';
        }
        if ($date === '') {
            $errors['event_date'] = 'تاریخ رویداد الزامی است.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'title' => $title,
            'description' => trim((string) $request->input('description', '')),
            'event_date' => $date,
            'location' => trim((string) $request->input('location', '')),
        ];
    }

    /** @return array<string,mixed> */
    private function present(Event $e): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'event_date' => $e->eventDate,
            'location' => $e->location,
            'created_by' => $e->createdBy !== null ? ($this->users->find($e->createdBy)?->name ?? null) : null,
        ];
    }
}
