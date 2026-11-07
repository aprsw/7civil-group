<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Exceptions\UnauthorizedException;
use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Domain\Entities\User;

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
