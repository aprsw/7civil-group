<?php

declare(strict_types=1);

namespace ArtaPharma\Core;

use ArtaPharma\Core\Exceptions\HttpException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Core\Http\Router;
use Throwable;

/**
 * Application kernel / front controller.
 *
 * Owns the DI container and the router, then turns an incoming Request into a
 * Response — translating any thrown exception into a structured JSON error.
 */
final class Application
{
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly bool $debug = false,
        private readonly string $corsOrigin = '*',
    ) {
    }

    public function handle(Request $request): Response
    {
        // CORS is applied globally (not as route middleware) so that pre-flight
        // OPTIONS requests — which match no route — still receive the headers a
        // browser requires before it will send the real cross-origin request.
        $origin = $this->resolveOrigin($request->header('Origin'));

        if ($request->method() === 'OPTIONS') {
            return $this->withCors(Response::noContent(), $origin);
        }

        try {
            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = Response::error($e->getMessage(), $e->status(), $e->errors());
        } catch (Throwable $e) {
            $payload = $this->debug
                ? ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]
                : [];

            $response = Response::error('خطای داخلی سرور رخ داد.', 500, $payload);
        }

        return $this->withCors($response, $origin);
    }

    /**
     * Resolve a single, valid Access-Control-Allow-Origin value. Supports '*'
     * and a comma-separated allow-list (echoing the request Origin when listed).
     */
    private function resolveOrigin(?string $requestOrigin): string
    {
        $conf = trim($this->corsOrigin);
        if ($conf === '' || $conf === '*') {
            return '*';
        }
        $list = array_map('trim', explode(',', $conf));
        if ($requestOrigin !== null && in_array($requestOrigin, $list, true)) {
            return $requestOrigin;
        }

        return $list[0];
    }

    private function withCors(Response $response, string $origin): Response
    {
        $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '86400');
        if ($origin !== '*') {
            $response->withHeader('Vary', 'Origin');
        }

        return $response;
    }

    public function container(): Container
    {
        return $this->container;
    }
}
