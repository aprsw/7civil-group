<?php

declare(strict_types=1);

namespace SevenCivilGroup\Domain\Repositories;

use SevenCivilGroup\Domain\Entities\Template;

interface TemplateRepositoryInterface
{
    /** @return list<Template> */
    public function all(): array;

    public function find(int $id): ?Template;

    public function save(Template $template): Template;

    public function delete(int $id): bool;
}
