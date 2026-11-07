<?php

declare(strict_types=1);

namespace ArtaPharma\Core\Exceptions;

/**
 * 422 — the request was well-formed but failed validation rules.
 */
final class ValidationException extends HttpException
{
    /** @param array<string, string> $errors field => Persian message */
    public function __construct(array $errors, string $message = 'داده‌های ارسالی نامعتبر است.')
    {
        parent::__construct(422, $message, $errors);
    }
}
