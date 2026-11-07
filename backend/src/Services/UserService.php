<?php

declare(strict_types=1);

namespace ArtaPharma\Services;

use ArtaPharma\Core\Exceptions\NotFoundException;
use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Domain\Entities\User;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\DepartmentRepositoryInterface;
use ArtaPharma\Domain\Repositories\RoleRepositoryInterface;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Security\Contracts\HasherInterface;

/**
 * Staff (کارکنان) management use-cases: create / update / delete with
 * validation. Passwords are hashed here so controllers never touch them.
 */
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly HasherInterface $hasher,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): User
    {
        $this->validate($data, null);

        return $this->users->create([
            'name' => trim((string) $data['name']),
            'username' => trim((string) $data['username']),
            'password_hash' => $this->hasher->hash((string) ($data['password'] ?? 'password')),
            'email' => trim((string) ($data['email'] ?? '')),
            'role_id' => (int) $data['role_id'],
            'department_id' => (int) $data['department_id'],
            'avatar_color' => (string) ($data['avatar_color'] ?? 'brand'),
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data, int $currentUserId = 0): User
    {
        $existing = $this->users->find($id) ?? throw new NotFoundException('کارمند یافت نشد.');
        $this->validate($data, $existing);

        // Never let the last administrator strip their own (or the last) admin
        // access — that would lock everyone out of user management.
        $newRole = $this->roles->find((int) $data['role_id']);
        $wasAdmin = $existing->role?->has(Permission::UsersManage) ?? false;
        $willBeAdmin = $newRole?->has(Permission::UsersManage) ?? false;
        if ($wasAdmin && !$willBeAdmin && $this->adminCount() <= 1) {
            throw new ValidationException(['role_id' => 'حداقل یک مدیر با دسترسی مدیریت کارکنان باید باقی بماند.']);
        }

        $fields = [
            'name' => trim((string) $data['name']),
            'username' => trim((string) $data['username']),
            'email' => trim((string) ($data['email'] ?? '')),
            'role_id' => (int) $data['role_id'],
            'department_id' => (int) $data['department_id'],
            'avatar_color' => (string) ($data['avatar_color'] ?? $existing->avatarColor),
        ];
        if (!empty($data['password'])) {
            $fields['password_hash'] = $this->hasher->hash((string) $data['password']);
        }
        // Optional profile + status fields (managed by the admin).
        foreach (['birth_date', 'address', 'mobile', 'phone', 'description'] as $k) {
            if (array_key_exists($k, $data)) {
                $fields[$k] = trim((string) $data[$k]);
            }
        }
        if (array_key_exists('active', $data)) {
            // Guard: never deactivate the last administrator.
            $wantActive = filter_var($data['active'], FILTER_VALIDATE_BOOL);
            if (!$wantActive && ($existing->role?->has(Permission::UsersManage) ?? false) && $this->activeAdminCount() <= 1) {
                throw new ValidationException(['active' => 'حداقل یک مدیرِ فعال با دسترسی مدیریت کارکنان باید باقی بماند.']);
            }
            $fields['active'] = $wantActive ? 1 : 0;
        }

        return $this->users->update($id, $fields) ?? $existing;
    }

    public function delete(int $id, int $currentUserId): void
    {
        $user = $this->users->find($id) ?? throw new NotFoundException('کارمند یافت نشد.');
        if ($user->id === $currentUserId) {
            throw new ValidationException(['id' => 'حذف حساب کاربری خودتان مجاز نیست.']);
        }
        if (($user->role?->has(Permission::UsersManage) ?? false) && $this->adminCount() <= 1) {
            throw new ValidationException(['id' => 'حذف آخرین مدیر با دسترسی مدیریت کارکنان مجاز نیست.']);
        }
        $this->users->delete($id);
    }

    private function adminCount(): int
    {
        return count(array_filter(
            $this->users->all(),
            static fn (User $u) => $u->role?->has(Permission::UsersManage) ?? false,
        ));
    }

    private function activeAdminCount(): int
    {
        return count(array_filter(
            $this->users->all(),
            static fn (User $u) => $u->active && ($u->role?->has(Permission::UsersManage) ?? false),
        ));
    }

    /** @param array<string,mixed> $data */
    private function validate(array $data, ?User $existing): void
    {
        $errors = [];
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'نام الزامی است.';
        }
        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            $errors['username'] = 'نام کاربری الزامی است.';
        } else {
            $byName = $this->users->findByUsername($username);
            if ($byName !== null && $byName->id !== ($existing->id ?? 0)) {
                $errors['username'] = 'این نام کاربری قبلاً استفاده شده است.';
            }
        }
        if ($existing === null && trim((string) ($data['password'] ?? '')) === '') {
            $errors['password'] = 'رمز عبور برای کاربر جدید الزامی است.';
        }
        $roleId = (int) ($data['role_id'] ?? 0);
        if ($roleId <= 0 || $this->roles->find($roleId) === null) {
            $errors['role_id'] = 'نقش معتبر انتخاب کنید.';
        }
        $deptId = (int) ($data['department_id'] ?? 0);
        if ($deptId <= 0 || $this->departments->find($deptId) === null) {
            $errors['department_id'] = 'واحد معتبر انتخاب کنید.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
