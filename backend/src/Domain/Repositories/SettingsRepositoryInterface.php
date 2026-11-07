<?php

declare(strict_types=1);

namespace ArtaPharma\Domain\Repositories;

interface SettingsRepositoryInterface
{
    public function get(string $key, ?string $default = null): ?string;

    /** @return array<string,string> */
    public function all(): array;

    public function set(string $key, string $value): void;
}
