<?php

declare(strict_types=1);

namespace SevenCivilGroup\Core\Exceptions;

/**
 * 403 — the authenticated user lacks the required access level / permission.
 */
final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'سطح دسترسی شما برای این عملیات کافی نیست.')
    {
        parent::__construct(403, $message);
    }
}
