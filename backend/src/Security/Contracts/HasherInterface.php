<?php

declare(strict_types=1);

namespace ArtaPharma\Security\Contracts;

interface HasherInterface
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;
}
