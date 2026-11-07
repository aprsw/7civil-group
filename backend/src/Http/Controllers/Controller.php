<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Exceptions\UnauthorizedException;
use ArtaPharma\Core\Http\Request;
use ArtaPharma\Domain\Entities\User;

/**
 * Base controller — shared helper to read the authenticated user placed on the
 * request by AuthenticateMiddleware.
 */
abstract class Controller
{
    protected function user(Request $request): User
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        return $user;
    }
}
