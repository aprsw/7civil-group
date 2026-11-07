<?php

declare(strict_types=1);

namespace SevenCivilGroup\Core\Exceptions;

/**
 * 404 — the requested route or resource does not exist.
 */
final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'موردی یافت نشد.')
    {
        parent::__construct(404, $message);
    }
}
