<?php

declare(strict_types=1);

namespace ArtaPharma\Security;

use ArtaPharma\Security\Contracts\HasherInterface;

/**
 * Bcrypt password hasher (uses PHP's native password_* — no external library).
 */
final class BcryptHasher implements HasherInterface
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
