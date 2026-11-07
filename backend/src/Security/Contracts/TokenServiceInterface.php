<?php

declare(strict_types=1);

namespace SevenCivilGroup\Security\Contracts;

interface TokenServiceInterface
{
    /** @param array<string, mixed> $claims */
    public function issue(array $claims): string;

    /**
     * @return array<string, mixed> the validated payload
     * @throws \SevenCivilGroup\Core\Exceptions\UnauthorizedException on invalid/expired tokens
     */
    public function verify(string $token): array;
}
