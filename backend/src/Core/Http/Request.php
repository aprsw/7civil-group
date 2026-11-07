<?php

declare(strict_types=1);

namespace ArtaPharma\Core\Http;

/**
 * Immutable-ish value object wrapping the incoming HTTP request.
 * Controllers read from this instead of touching PHP superglobals directly
 * (Single Responsibility + testability).
 */
final class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     * @param array<string, string> $attributes route params / auth context
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private array $query = [],
        private array $body = [],
        private array $headers = [],
        private array $attributes = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';

        $raw = file_get_contents('php://input') ?: '';
        $body = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }
        if ($body === [] && !empty($_POST)) {
            $body = $_POST;
        }

        return new self($method, $path, $_GET, $body, self::readHeaders());
    }

    /** @return array<string, string> */
    private static function readHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        return $headers;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[$name] ?? $this->headers[ucwords($name, '-')] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization') ?? '';

        return preg_match('/Bearer\s+(\S+)/i', $auth, $m) === 1 ? $m[1] : null;
    }

    /**
     * An uploaded file (multipart) by field name, as PHP's $_FILES entry.
     *
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
     */
    public function file(string $field): ?array
    {
        $file = $_FILES[$field] ?? null;

        return is_array($file) ? $file : null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
