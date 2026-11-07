<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Middleware;

use ArtaPharma\Core\Exceptions\UnauthorizedException;
use ArtaPharma\Core\Http\Middleware;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Services\AuthService;
use Closure;

/**
 * Guards protected routes: resolves the bearer token to a User, records the
 * user's last-seen time (for the online list), and exposes it to controllers
 * via the `user` request attribute.
 */
final class AuthenticateMiddleware implements Middleware
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new UnauthorizedException('توکن دسترسی ارسال نشده است.');
        }

        $user = $this->auth->userFromToken($token);
        if (!$user->active) {
            throw new UnauthorizedException('حساب کاربری شما غیرفعال شده است.');
        }
        $this->users->touchLastSeen($user->id);
        $request->setAttribute('user', $user);

        return $next($request);
    }
}
