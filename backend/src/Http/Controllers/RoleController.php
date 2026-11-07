<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\ForbiddenException;
use SevenCivilGroup\Core\Exceptions\NotFoundException;
use SevenCivilGroup\Core\Exceptions\ValidationException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Domain\Entities\Role;
use SevenCivilGroup\Domain\Enums\Permission;
use SevenCivilGroup\Domain\Repositories\RoleRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\UserRepositoryInterface;
use SevenCivilGroup\Services\AccessControl;
use SevenCivilGroup\Support\Jalali;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly UserRepositoryInterface $users,
        private readonly AccessControl $access,
    ) {
    }

    /**
     * GET /api/roles — list. Managers of roles/users/workflows may read it.
     * The permission matrix + user counts are only exposed to roles.manage.
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $canManage = $this->access->can($user, Permission::RolesManage);
        if (!$canManage
            && !$this->access->can($user, Permission::UsersManage)
            && !$this->access->can($user, Permission::WorkflowManage)) {
            throw new ForbiddenException('دسترسی به فهرست نقش‌ها مجاز نیست.');
        }

        $items = array_map(function ($role) use ($canManage): array {
            $data = [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'level' => $role->level,
                'level_label' => 'سطح ' . Jalali::toPersianDigits((string) $role->level),
            ];
            if ($canManage) {
                $data['permissions'] = $role->permissions;
                $data['user_count'] = count(array_filter($this->users->all(), static fn ($u) => $u->role?->id === $role->id));
            }

            return $data;
        }, $this->roles->all());

        return Response::ok(['items' => $items, 'can_manage' => $canManage]);
    }

    /** GET /api/roles/permissions — the full permission catalogue (roles.manage). */
    public function permissions(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::RolesManage);

        return Response::ok(array_map(
            static fn (Permission $p) => ['value' => $p->value, 'label' => $p->label()],
            Permission::cases(),
        ));
    }

    /** POST /api/roles */
    public function store(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::RolesManage);
        $role = $this->roles->save($this->fromRequest($request, null));

        return Response::created($this->present($role), 'نقش جدید ثبت شد.');
    }

    /** PUT /api/roles/{id} */
    public function update(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::RolesManage);
        $id = (int) $request->attribute('id');
        $existing = $this->roles->find($id) ?? throw new NotFoundException('نقش یافت نشد.');

        // Never let the last role that can manage users lose that permission.
        $newPerms = $this->cleanPermissions((array) $request->input('permissions', []));
        $wasAdmin = in_array(Permission::UsersManage->value, $existing->permissions, true);
        $willBeAdmin = in_array(Permission::UsersManage->value, $newPerms, true);
        if ($wasAdmin && !$willBeAdmin && $this->adminRoleCount() <= 1) {
            throw new ValidationException(['permissions' => 'حداقل یک نقش با دسترسی «مدیریت کارکنان» باید باقی بماند.']);
        }

        $role = $this->roles->save($this->fromRequest($request, $existing));

        return Response::ok($this->present($role), 'نقش به‌روزرسانی شد.');
    }

    /** DELETE /api/roles/{id} */
    public function destroy(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::RolesManage);
        $id = (int) $request->attribute('id');
        $role = $this->roles->find($id) ?? throw new NotFoundException('نقش یافت نشد.');

        $inUse = count(array_filter($this->users->all(), static fn ($u) => $u->role?->id === $id));
        if ($inUse > 0) {
            throw new ValidationException(['id' => 'این نقش به کاربران تخصیص یافته و قابل حذف نیست؛ ابتدا نقشِ آن کاربران را تغییر دهید.']);
        }
        if (in_array(Permission::UsersManage->value, $role->permissions, true) && $this->adminRoleCount() <= 1) {
            throw new ValidationException(['id' => 'حذف آخرین نقش مدیریتی مجاز نیست.']);
        }

        $this->roles->delete($id);

        return Response::ok(null, 'نقش حذف شد.');
    }

    private function fromRequest(Request $request, ?Role $existing): Role
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'نام نقش الزامی است.']);
        }
        $level = (int) $request->input('level', 0);
        if ($level < 1 || $level > 20) {
            throw new ValidationException(['level' => 'سطح دسترسی باید بین ۱ تا ۲۰ باشد.']);
        }
        $permissions = $this->cleanPermissions((array) $request->input('permissions', []));

        // Keep a stable slug on edit; derive a unique one for new roles.
        $slug = $existing?->slug ?? $this->uniqueSlug($name);

        return new Role($existing?->id ?? 0, $name, $slug, $level, $permissions);
    }

    /**
     * @param array<int,mixed> $raw
     * @return list<string>
     */
    private function cleanPermissions(array $raw): array
    {
        $valid = array_map(static fn (Permission $p) => $p->value, Permission::cases());

        return array_values(array_unique(array_filter(
            array_map('strval', $raw),
            static fn ($p) => in_array($p, $valid, true),
        )));
    }

    private function uniqueSlug(string $name): string
    {
        $base = 'role-' . substr(md5($name . microtime()), 0, 8);
        while ($this->roles->findBySlug($base) !== null) {
            $base = 'role-' . substr(md5($name . random_int(1, 999999)), 0, 8);
        }

        return $base;
    }

    private function adminRoleCount(): int
    {
        return count(array_filter(
            $this->roles->all(),
            static fn ($r) => in_array(Permission::UsersManage->value, $r->permissions, true),
        ));
    }

    /** @return array<string,mixed> */
    private function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'level' => $role->level,
            'level_label' => 'سطح ' . Jalali::toPersianDigits((string) $role->level),
            'permissions' => $role->permissions,
            'user_count' => count(array_filter($this->users->all(), static fn ($u) => $u->role?->id === $role->id)),
        ];
    }
}
