<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\NotFoundException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\DepartmentRepositoryInterface;
use ArtaPharma\Domain\Repositories\MessagingRuleRepositoryInterface;
use ArtaPharma\Domain\Repositories\RoleRepositoryInterface;
use ArtaPharma\Services\AccessControl;

/**
 * Messaging permissions. The senior admin (messaging.manage) decides, for each
 * سمت (role), which سمت‌ها a normal letter may be addressed to — this drives the
 * dynamic recipient list. Per-unit rules are kept for backward compatibility.
 */
final class MessagingController extends Controller
{
    public function __construct(
        private readonly MessagingRuleRepositoryInterface $rules,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly RoleRepositoryInterface $roles,
        private readonly AccessControl $access,
    ) {
    }

    /** GET /api/messaging-rules */
    public function index(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::MessagingManage);

        return Response::ok([
            'roles' => array_map(
                static fn ($r) => ['id' => $r->id, 'name' => $r->name, 'level' => $r->level],
                $this->roles->all(),
            ),
            // from_role_id => list<to_role_id>. Missing entry = default hierarchy.
            'role_matrix' => $this->rules->roleMatrix(),
            'departments' => array_map(
                static fn ($d) => ['id' => $d->id, 'name' => $d->name, 'code' => $d->code],
                $this->departments->all(),
            ),
            'matrix' => $this->rules->matrix(),
        ]);
    }

    /** PUT /api/messaging-rules/role/{id} — allow-list of سمت‌ها for one سمت. */
    public function updateRole(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::MessagingManage);
        $fromId = (int) $request->attribute('id');
        $this->roles->find($fromId) ?? throw new NotFoundException('سمت مبدأ یافت نشد.');

        $toIds = (array) $request->input('to_role_ids', []);
        $valid = array_values(array_filter(
            array_map('intval', $toIds),
            fn ($id) => $id > 0 && $this->roles->find($id) !== null,
        ));
        $this->rules->setForRole($fromId, $valid);

        return Response::ok(['from_role_id' => $fromId, 'to_role_ids' => $valid], 'مجوزهای مکاتبه ذخیره شد.');
    }

    /** PUT /api/messaging-rules/{id} — replace the allow-list for a source unit. */
    public function update(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::MessagingManage);
        $fromId = (int) $request->attribute('id');
        $this->departments->find($fromId) ?? throw new NotFoundException('واحد مبدأ یافت نشد.');

        $toIds = (array) $request->input('to_department_ids', []);
        // Only keep valid, existing department ids.
        $valid = array_values(array_filter(
            array_map('intval', $toIds),
            fn ($id) => $id > 0 && $this->departments->find($id) !== null,
        ));

        $this->rules->setForDepartment($fromId, $valid);

        return Response::ok(['from_department_id' => $fromId, 'to_department_ids' => $valid], 'مجوزهای مکاتبه ذخیره شد.');
    }
}
