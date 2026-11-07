<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\UserPermissionRepositoryInterface;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Services\AccessControl;

/**
 * Per-user access grants. The admin may hand an individual capability to any
 * user on top of their role — chiefly دبیرخانه (secretariat.use), but any of the
 * curated grantable permissions below.
 */
final class PermissionGrantController extends Controller
{
    /** Permissions the admin can grant individually (value ⇒ label). */
    private const GRANTABLE = [
        Permission::SecretariatUse,
        Permission::SecretariatManage,
        Permission::ChatUse,
        Permission::TemplatesManage,
        Permission::WorkflowManage,
        Permission::LettersArchive,
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPermissionRepositoryInterface $grants,
        private readonly AccessControl $access,
    ) {
    }

    /** GET /api/permission-grants — users + their extra grants (admin only). */
    public function index(Request $request): Response
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::RolesManage);

        $map = $this->grants->grantsMap();
        $users = array_values(array_map(static fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role?->name,
            'department' => $u->department?->name,
            // Only the directly-granted (extra) permissions are editable here.
            'grants' => $map[$u->id] ?? [],
            // Permissions the role already provides are shown as inherited/locked.
            'role_permissions' => $u->role?->permissions ?? [],
        ], array_filter($this->users->all(), static fn ($u) => $u->active)));

        $grantable = array_map(static fn (Permission $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ], self::GRANTABLE);

        return Response::ok(['users' => $users, 'grantable' => $grantable]);
    }

    /** PUT /api/permission-grants/user/{id} — set a user's extra grants (admin). */
    public function update(Request $request): Response
    {
        $me = $this->user($request);
        $this->access->authorize($me, Permission::RolesManage);

        $userId = (int) $request->attribute('id');
        $allowed = array_map(static fn (Permission $p) => $p->value, self::GRANTABLE);
        $incoming = array_values(array_filter(
            array_map('strval', (array) $request->input('permissions', [])),
            static fn (string $p) => in_array($p, $allowed, true),
        ));

        $this->grants->setForUser($userId, $incoming);

        return Response::ok(['user_id' => $userId, 'grants' => $incoming], 'دسترسی‌های کاربر به‌روزرسانی شد.');
    }
}
