<?php

declare(strict_types=1);

namespace SevenCivilGroup\Core\Exceptions;

/**
 * 401 — authentication is required or the supplied token is invalid.
 */
final class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'برای دسترسی باید وارد شوید.')
    {
        parent::__construct(401, $message);
    }
}
