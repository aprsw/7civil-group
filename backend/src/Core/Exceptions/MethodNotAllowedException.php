<?php

declare(strict_types=1);

namespace ArtaPharma\Core\Exceptions;

/**
 * 405 — the path exists but not for the requested HTTP method.
 */
final class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'متد درخواستی برای این مسیر مجاز نیست.')
    {
        parent::__construct(405, $message);
    }
}
