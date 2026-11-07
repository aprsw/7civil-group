<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\RoleRepositoryInterface;
use ArtaPharma\Domain\Repositories\WorkflowRepositoryInterface;
use ArtaPharma\Services\AccessControl;
use ArtaPharma\Services\WorkflowService;
use ArtaPharma\View\WorkflowTransformer;

final class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
        private readonly RoleRepositoryInterface $roles,
        private readonly WorkflowService $service,
        private readonly WorkflowTransformer $transformer,
        private readonly AccessControl $access,
        private readonly \ArtaPharma\Domain\Repositories\UserRepositoryInterface $users,
    ) {
    }

    /** GET /api/workflows */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $roles = $this->roles->all();

        // Every active user — used by the admin's fixed-chain (approver) builder.
        $users = array_values(array_map(static fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role?->name,
            'department' => $u->department?->name,
            'photo_url' => $u->photoPath !== null && $u->photoPath !== '' ? '/uploads/photos/' . $u->photoPath : null,
        ], array_filter($this->users->all(), static fn ($u) => $u->active)));

        return Response::ok([
            'items' => $this->transformer->collection($this->workflows->all(), $roles),
            'users' => $users,
            // The UI unlocks the builder only for managers (workflow.manage).
            'can_manage' => $this->access->can($user, Permission::WorkflowManage),
        ]);
    }

    /** POST /api/workflows | PUT /api/workflows/{id} */
    public function save(Request $request): Response
    {
        $user = $this->user($request);
        $id = $request->attribute('id') !== null ? (int) $request->attribute('id') : null;

        $workflow = $this->service->save($id, $request->all(), $user);
        $payload = $this->transformer->transform($workflow, $this->roles->all());
        $payload['warnings'] = $this->service->validateChart($workflow->steps);

        return Response::ok(
            $payload,
            'گردش کار «' . $workflow->name . '» ذخیره و اعمال شد.',
        );
    }

    /** DELETE /api/workflows/{id} */
    public function destroy(Request $request): Response
    {
        $this->service->delete((int) $request->attribute('id'), $this->user($request));

        return Response::ok(null, 'گردش کار حذف شد.');
    }
}
