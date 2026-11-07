<?php

declare(strict_types=1);

namespace SevenCivilGroup\Core\Http;

use Closure;

/**
 * Middleware contract — Chain of Responsibility.
 *
 * Each middleware may inspect/mutate the request, short-circuit with its own
 * response, or delegate to the next handler via $next($request).
 */
interface Middleware
{
    public function handle(Request $request, Closure $next): Response;
}
