<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\OrgLogo;

interface OrgLogoRepositoryInterface
{
    /** @return list<OrgLogo> */
    public function all(): array;

    public function find(int $id): ?OrgLogo;

    public function create(string $title, string $path, ?string $mime, ?int $size, ?int $uploadedBy): OrgLogo;

    public function rename(int $id, string $title): ?OrgLogo;

    public function delete(int $id): void;
}
