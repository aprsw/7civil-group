<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\Event;

interface EventRepositoryInterface
{
    /** @return list<Event> */
    public function all(): array;

    public function find(int $id): ?Event;

    /** @param array{title:string,description:string,event_date:string,location:string,created_by:?int} $data */
    public function create(array $data): Event;

    /** @param array{title:string,description:string,event_date:string,location:string} $data */
    public function update(int $id, array $data): ?Event;

    public function delete(int $id): void;
}
