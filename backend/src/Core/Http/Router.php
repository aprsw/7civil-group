<?php

declare(strict_types=1);

namespace SevenCivilGroup\Core\Http;

use SevenCivilGroup\Core\Container;
use SevenCivilGroup\Core\Exceptions\MethodNotAllowedException;
use SevenCivilGroup\Core\Exceptions\NotFoundException;
use Closure;

/**
 * Front-controller router.
 *
 * Registers routes, supports nested groups (shared prefix + middleware), and
 * dispatches a matched route through its middleware pipeline (Chain of
 * Responsibility) terminating in the controller action.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array{prefix: string, middleware: list<class-string>} */
    private array $groupStack = ['prefix' => '', 'middleware' => []];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param class-string $controller */
    public function get(string $path, string $controller, string $action, array $middleware = []): void
    {
        $this->add('GET', $path, $controller, $action, $middleware);
    }

    /** @param class-string $controller */
    public function post(string $path, string $controller, string $action, array $middleware = []): void
    {
        $this->add('POST', $path, $controller, $action, $middleware);
    }

    /** @param class-string $controller */
    public function put(string $path, string $controller, string $action, array $middleware = []): void
    {
        $this->add('PUT', $path, $controller, $action, $middleware);
    }

    /** @param class-string $controller */
    public function patch(string $path, string $controller, string $action, array $middleware = []): void
    {
        $this->add('PATCH', $path, $controller, $action, $middleware);
    }

    /** @param class-string $controller */
    public function delete(string $path, string $controller, string $action, array $middleware = []): void
    {
        $this->add('DELETE', $path, $controller, $action, $middleware);
    }

    /**
     * @param array{prefix?: string, middleware?: list<class-string>} $attributes
     */
    public function group(array $attributes, Closure $routes): void
    {
        $previous = $this->groupStack;
        $this->groupStack = [
            'prefix' => $previous['prefix'] . ($attributes['prefix'] ?? ''),
            'middleware' => [...$previous['middleware'], ...($attributes['middleware'] ?? [])],
        ];

        $routes($this);

        $this->groupStack = $previous;
    }

    private function add(string $method, string $path, string $controller, string $action, array $middleware): void
    {
        $route = new Route(
            $method,
            $this->groupStack['prefix'] . $path,
            $controller,
            $action,
            [...$this->groupStack['middleware'], ...$middleware],
        );
        $this->routes[] = $route;
    }

    public function dispatch(Request $request): Response
    {
        $pathExists = false;

        foreach ($this->routes as $route) {
            $params = $route->match($request->method(), $request->path());
            if ($params === null) {
                // Track whether the path matches under a different method (→ 405).
                if ($route->matchPath($request->path()) !== null) {
                    $pathExists = true;
                }
                continue;
            }

            foreach ($params as $key => $value) {
                $request->setAttribute($key, $value);
            }

            return $this->runPipeline($route, $request);
        }

        if ($pathExists) {
            throw new MethodNotAllowedException();
        }

        throw new NotFoundException('مسیر درخواستی وجود ندارد.');
    }

    private function runPipeline(Route $route, Request $request): Response
    {
        // Terminal handler: resolve controller from the container and call the action.
        $core = function (Request $req) use ($route): Response {
            $controller = $this->container->get($route->controller());

            return $controller->{$route->action()}($req);
        };

        // Wrap middlewares around the core, last-registered runs outermost.
        $pipeline = array_reduce(
            array_reverse($route->middleware()),
            function (Closure $next, string $middlewareClass): Closure {
                return function (Request $req) use ($next, $middlewareClass): Response {
                    /** @var Middleware $middleware */
                    $middleware = $this->container->get($middlewareClass);

                    return $middleware->handle($req, $next);
                };
            },
            $core,
        );

        return $pipeline($request);
    }
}
