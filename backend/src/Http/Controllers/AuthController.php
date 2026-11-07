<?php

declare(strict_types=1);

namespace SevenCivilGroup\Http\Controllers;

use SevenCivilGroup\Core\Http\Request;
use SevenCivilGroup\Core\Http\Response;
use SevenCivilGroup\Services\AuthService;
use SevenCivilGroup\View\UserTransformer;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserTransformer $users,
    ) {
    }

    public function login(Request $request): Response
    {
        $result = $this->auth->login(
            (string) $request->input('username', ''),
            (string) $request->input('password', ''),
        );

        return Response::ok([
            'token' => $result['token'],
            'user' => $this->users->transform($result['user']),
        ], 'خوش آمدید.');
    }

    public function me(Request $request): Response
    {
        return Response::ok($this->users->transform($this->user($request)));
    }
}
