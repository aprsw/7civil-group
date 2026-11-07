<?php

declare(strict_types=1);

namespace ArtaPharma\View;

use ArtaPharma\Domain\Entities\User;
use ArtaPharma\Domain\Repositories\UserPermissionRepositoryInterface;
use ArtaPharma\Support\Jalali;

final class UserTransformer
{
    /** @var array<int,list<string>>|null cached per-user grants */
    private ?array $grants = null;

    public function __construct(
        private readonly ?UserPermissionRepositoryInterface $userPermissions = null,
    ) {
    }

    /**
     * @param bool $includeProfile whether to expose personal profile fields
     * @return array<string, mixed>
     */
    public function transform(User $user, bool $includeProfile = false): array
    {
        // Effective permissions = role permissions ∪ per-user admin grants.
        $rolePerms = $user->role?->permissions ?? [];
        $permissions = array_values(array_unique(array_merge($rolePerms, $this->grantsFor($user->id))));

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar_color' => $user->avatarColor,
            'photo_url' => $user->photoPath !== null ? '/uploads/photos/' . $user->photoPath : null,
            'signature_url' => $user->signaturePath !== null ? '/uploads/signatures/' . $user->signaturePath : null,
            'has_signature' => $user->signaturePath !== null,
            'active' => $user->active,
            'online' => $user->isOnline(),
            'tier' => $user->tier(),
            'role' => $user->role === null ? null : [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'slug' => $user->role->slug,
                'level' => $user->role->level,
                'level_label' => 'سطح ' . Jalali::toPersianDigits((string) $user->role->level),
                // Includes per-user grants so the UI unlocks granted features.
                'permissions' => $permissions,
            ],
            'extra_permissions' => array_values($this->grantsFor($user->id)),
            'department' => $user->department === null ? null : [
                'id' => $user->department->id,
                'name' => $user->department->name,
                'code' => $user->department->code,
            ],
        ];

        if ($includeProfile) {
            $data['profile'] = [
                'birth_date' => $user->birthDate,
                'address' => $user->address,
                'mobile' => $user->mobile,
                'phone' => $user->phone,
                'description' => $user->description,
            ];
        }

        return $data;
    }

    /** @return list<string> */
    private function grantsFor(int $userId): array
    {
        if ($this->userPermissions === null) {
            return [];
        }
        if ($this->grants === null) {
            $this->grants = $this->userPermissions->grantsMap();
        }

        return $this->grants[$userId] ?? [];
    }

    /**
     * @param list<User> $users
     * @return list<array<string, mixed>>
     */
    public function collection(array $users, bool $includeProfile = false): array
    {
        return array_map(fn ($u) => $this->transform($u, $includeProfile), $users);
    }
}
