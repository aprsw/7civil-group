<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\NotFoundException;
use ArtaPharma\Core\Exceptions\ValidationException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Entities\Department;
use ArtaPharma\Domain\Enums\Permission;
use ArtaPharma\Domain\Repositories\DepartmentRepositoryInterface;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Services\AccessControl;

final class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly UserRepositoryInterface $users,
        private readonly AccessControl $access,
    ) {
    }

    /** GET /api/departments — viewable by executives; also feeds admin forms. */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        if (!$this->access->can($user, Permission::DepartmentsView) && !$this->access->can($user, Permission::DepartmentsManage)) {
            throw new \ArtaPharma\Core\Exceptions\ForbiddenException('مشاهدهٔ واحدها مجاز نیست.');
        }

        $canManage = $this->access->can($user, Permission::DepartmentsManage);
        $items = array_map(fn ($dept) => [
            'id' => $dept->id,
            'name' => $dept->name,
            'code' => $dept->code,
            'description' => $dept->description,
            'staff_count' => count($this->users->byDepartment($dept->id)),
        ], $this->departments->all());

        return Response::ok(['items' => $items, 'can_manage' => $canManage]);
    }

    /** POST /api/departments */
    public function store(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::DepartmentsManage);
        $dept = $this->departments->save($this->fromRequest($request, 0));

        return Response::created($this->present($dept), 'واحد جدید ثبت شد.');
    }

    /** PUT /api/departments/{id} */
    public function update(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::DepartmentsManage);
        $id = (int) $request->attribute('id');
        $this->departments->find($id) ?? throw new NotFoundException('واحد یافت نشد.');
        $dept = $this->departments->save($this->fromRequest($request, $id));

        return Response::ok($this->present($dept), 'واحد به‌روزرسانی شد.');
    }

    /** DELETE /api/departments/{id} */
    public function destroy(Request $request): Response
    {
        $this->access->authorize($this->user($request), Permission::DepartmentsManage);
        $id = (int) $request->attribute('id');
        if (count($this->users->byDepartment($id)) > 0) {
            throw new ValidationException(['id' => 'واحد دارای کارمند است و قابل حذف نیست؛ ابتدا کارکنان را جابه‌جا کنید.']);
        }
        $this->departments->delete($id);

        return Response::ok(null, 'واحد حذف شد.');
    }

    private function fromRequest(Request $request, int $id): Department
    {
        $name = trim((string) $request->input('name', ''));
        $code = trim((string) $request->input('code', ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'نام واحد الزامی است.']);
        }
        if ($code === '') {
            throw new ValidationException(['code' => 'کد واحد الزامی است.']);
        }

        return new Department($id, $name, $code, trim((string) $request->input('description', '')));
    }

    /** @return array<string,mixed> */
    private function present(Department $d): array
    {
        return ['id' => $d->id, 'name' => $d->name, 'code' => $d->code, 'description' => $d->description, 'staff_count' => count($this->users->byDepartment($d->id))];
    }
}
