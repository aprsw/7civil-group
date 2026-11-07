<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\UserRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;
use SevenCivilGroup\Services\UserService;
use SevenCivilGroup\View\UserTransformer;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserTransformer $transformer,
        private readonly UserService $service,
        private readonly AccessControl $access,
    ) {
    }

    /** GET /api/users */
    public function index(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::UsersView);

        return Response::ok($this->transformer->collection($this->users->all(), true));
    }

    /** GET /api/users/online — who is currently online (any authenticated user). */
    public function online(Request $request): Response
    {
        $this->user($request);
        $online = array_values(array_filter($this->users->all(), static fn ($u) => $u->isOnline()));

        return Response::ok(array_map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'role' => $u->role?->name,
            'department' => $u->department?->name, 'online' => true,
        ], $online));
    }

    /** POST /api/users */
    public function store(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::UsersManage);
        $user = $this->service->create($request->all());

        return Response::created($this->transformer->transform($user), 'کارمند جدید ثبت شد.');
    }

    /** PUT /api/users/{id} */
    public function update(Request $request): Response
    {
        $current = $this->user($request);
        $this->access->authorize($current, Permission::UsersManage);
        $user = $this->service->update((int) $request->attribute('id'), $request->all(), $current->id);

        return Response::ok($this->transformer->transform($user), 'اطلاعات کارمند به‌روزرسانی شد.');
    }

    /** DELETE /api/users/{id} */
    public function destroy(Request $request): Response
    {
        $current = $this->user($request);
        $this->access->authorize($current, Permission::UsersManage);
        $this->service->delete((int) $request->attribute('id'), $current->id);

        return Response::ok(null, 'کارمند حذف شد.');
    }
}
