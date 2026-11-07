<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

interface OfficeFileRepositoryInterface
{
    /** @return list<array<string,mixed>> */
    public function all(): array;

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array;

    /** @param array{name:string,path:string,mime:?string,size:?int,uploaded_by:?int} $data */
    public function create(array $data): int;

    public function delete(int $id): void;
}
