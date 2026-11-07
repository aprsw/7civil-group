<?php

declare(strict_types=1);

namespace SevenCivilGroup\Core\Http;

/**
 * A single route definition: HTTP method, compiled path pattern,
 * a [ControllerClass, method] handler and an ordered middleware stack.
 */
final class Route
{
    private string $pattern;

    /** @var list<string> route parameter names, in order */
    private array $paramNames = [];

    /**
     * @param class-string        $controller
     * @param list<class-string>  $middleware
     */
    public function __construct(
        private readonly string $method,
        string $path,
        private readonly string $controller,
        private readonly string $action,
        private array $middleware = [],
    ) {
        $this->pattern = $this->compile($path);
    }

    private function compile(string $path): string
    {
        $regex = preg_replace_callback('/\{(\w+)\}/', function (array $m): string {
            $this->paramNames[] = $m[1];

            return '([^/]+)';
        }, $path);

        return '#^' . $regex . '$#u';
    }

    /**
     * @return array<string, string>|null route params when matched, null otherwise
     */
    public function match(string $method, string $path): ?array
    {
        if ($method !== $this->method) {
            return null;
        }

        return $this->matchPath($path);
    }

    /**
     * Match the path only (ignoring HTTP method) — used to distinguish a 405
     * (path exists, wrong method) from a 404 (path unknown).
     *
     * @return array<string, string>|null
     */
    public function matchPath(string $path): ?array
    {
        if (preg_match($this->pattern, $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);

        return array_combine($this->paramNames, $matches) ?: [];
    }

    public function method(): string
    {
        return $this->method;
    }

    /** @param list<class-string> $middleware */
    public function addMiddleware(array $middleware): void
    {
        $this->middleware = [...$middleware, ...$this->middleware];
    }

    public function controller(): string
    {
        return $this->controller;
    }

    public function action(): string
    {
        return $this->action;
    }

    /** @return list<class-string> */
    public function middleware(): array
    {
        return $this->middleware;
    }
}
