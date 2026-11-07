<?php

declare(strict_types=1);

namespace SevenCivilGroup\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for exceptions that map cleanly onto an HTTP status code.
 * The front controller renders these as structured JSON error responses.
 */
class HttpException extends RuntimeException
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        private readonly int $status,
        string $message,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function errors(): array
    {
        return $this->errors;
    }
}
