<?php

declare(strict_types=1);

namespace ArtaPharma\Security\Contracts;

interface TokenServiceInterface
{
    /** @param array<string, mixed> $claims */
    public function issue(array $claims): string;

    /**
     * @return array<string, mixed> the validated payload
     * @throws \ArtaPharma\Core\Exceptions\UnauthorizedException on invalid/expired tokens
     */
    public function verify(string $token): array;
}
